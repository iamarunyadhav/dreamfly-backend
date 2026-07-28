<?php

namespace App\Http\Controllers;

use App\Support\Http\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\Finance\Models\LedgerEntry;
use Modules\Workflows\Models\CaseStep;

class ReportsController extends Controller
{
    use ApiResponse;

    private const EXTERNAL_WAITING = ['waiting_client', 'waiting_third_party'];

    public function overview(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        return $this->ok([
            'staff_performance' => $this->staffPerformance($from, $to),
            'stage_ageing' => $this->stageAgeing(),
            'revenue_trend' => $this->revenueTrend((int) ($validated['months'] ?? 6), $from, $to),
            'outcomes' => $this->outcomes($from, $to),
            'processing_time' => $this->processingTime($from, $to),
        ]);
    }

    /**
     * Per-staff documentation-task performance. External-waiting tasks are
     * counted separately and never treated as overdue against the staff member.
     */
    private function staffPerformance(?string $from, ?string $to): Collection
    {
        $tasks = DocumentationTask::with('assignedUser')
            ->whereNotNull('assigned_user_id')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->get();

        return $tasks->groupBy('assigned_user_id')->map(function (Collection $group) {
            $completed = $group->where('status', 'completed');
            $onTime = $completed->filter(fn ($t) => $t->due_at && $t->completed_at && $t->completed_at->lte($t->due_at));
            $externalWaiting = $group->whereIn('status', self::EXTERNAL_WAITING);
            $overdue = $group->filter(fn ($t) => $t->status === 'overdue'
                || ($t->due_at && $t->due_at->isPast() && $t->status !== 'completed' && ! in_array($t->status, self::EXTERNAL_WAITING, true)));

            $completionDays = $completed
                ->filter(fn ($t) => $t->completed_at)
                ->map(fn ($t) => ($t->start_at ?? $t->created_at)->diffInDays($t->completed_at));

            $first = $group->first();

            return [
                'user_id' => $first->assigned_user_id,
                'name' => $first->assignedUser?->name ?? 'User #'.$first->assigned_user_id,
                'assigned' => $group->count(),
                'completed' => $completed->count(),
                'on_time' => $onTime->count(),
                'overdue' => $overdue->count(),
                'external_waiting' => $externalWaiting->count(),
                'avg_completion_days' => $completionDays->isNotEmpty() ? round($completionDays->avg(), 1) : null,
            ];
        })->sortByDesc('completed')->values();
    }

    /**
     * How long active case steps have been sitting, grouped by step.
     */
    private function stageAgeing(): Collection
    {
        $steps = CaseStep::whereIn('status', ['in_progress', 'on_hold', 'waiting'])->get();

        return $steps->groupBy('key')->map(function (Collection $group) {
            $ages = $group->filter(fn ($s) => $s->started_at)->map(fn ($s) => $s->started_at->diffInDays(now()));
            $overdue = $group->filter(fn ($s) => $s->due_at && $s->due_at->isPast());

            return [
                'key' => $group->first()->key,
                'name' => $group->first()->name,
                'active_count' => $group->count(),
                'avg_age_days' => $ages->isNotEmpty() ? round($ages->avg(), 1) : 0,
                'oldest_age_days' => $ages->isNotEmpty() ? (int) $ages->max() : 0,
                'overdue_count' => $overdue->count(),
            ];
        })->sortByDesc('active_count')->values();
    }

    /**
     * Monthly income/expense/net from the ledger.
     */
    private function revenueTrend(int $months, ?string $from, ?string $to): Collection
    {
        $start = $from ? Carbon::parse($from)->startOfMonth() : now()->subMonths($months - 1)->startOfMonth();
        $end = $to ? Carbon::parse($to)->endOfMonth() : now()->endOfMonth();

        $entries = LedgerEntry::whereDate('entry_date', '>=', $start->toDateString())
            ->whereDate('entry_date', '<=', $end->toDateString())
            ->get();

        $byMonth = $entries->groupBy(fn ($e) => $e->entry_date->format('Y-m'));

        $result = collect();
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $group = $byMonth->get($key, collect());
            $income = (int) $group->where('type', 'income')->sum('amount');
            $expense = (int) $group->where('type', 'expense')->sum('amount');
            $result->push([
                'month' => $key,
                'label' => $cursor->format('M Y'),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ]);
        }

        return $result;
    }

    private function outcomes(?string $from, ?string $to): array
    {
        $query = Client::query()
            ->when($from, fn ($q) => $q->whereDate('outcome_recorded_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('outcome_recorded_at', '<=', $to));

        $approved = (clone $query)->where('visa_outcome', 'approved')->count();
        $refused = (clone $query)->where('visa_outcome', 'refused')->count();
        $withdrawn = (clone $query)->where('visa_outcome', 'withdrawn')->count();
        $decided = $approved + $refused + $withdrawn;
        $pending = Client::whereNull('visa_outcome')->orWhere('visa_outcome', 'pending')->count();

        return [
            'approved' => $approved,
            'refused' => $refused,
            'withdrawn' => $withdrawn,
            'pending' => $pending,
            'decided' => $decided,
            'approval_rate' => $decided > 0 ? round(($approved / $decided) * 100, 1) : null,
        ];
    }

    /**
     * Average end-to-end processing time from a case's first step to its
     * completed "closed" step.
     */
    private function processingTime(?string $from, ?string $to): array
    {
        $closedSteps = CaseStep::where('key', 'closed')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->when($from, fn ($q) => $q->whereDate('completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('completed_at', '<=', $to))
            ->get();

        if ($closedSteps->isEmpty()) {
            return ['avg_days' => null, 'closed_cases' => 0];
        }

        $firstStartByClient = CaseStep::whereIn('client_id', $closedSteps->pluck('client_id'))
            ->whereNotNull('started_at')
            ->get()
            ->groupBy('client_id')
            ->map(fn (Collection $group) => $group->min('started_at'));

        $durations = $closedSteps->map(function ($step) use ($firstStartByClient) {
            $start = $firstStartByClient->get($step->client_id);

            return $start ? Carbon::parse($start)->diffInDays($step->completed_at) : null;
        })->filter(fn ($d) => $d !== null);

        return [
            'avg_days' => $durations->isNotEmpty() ? round($durations->avg(), 1) : null,
            'closed_cases' => $closedSteps->count(),
        ];
    }
}
