<?php

namespace Modules\Clients\Services;

use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\DocumentationTask;
use Modules\Communications\Services\AlertDispatcher;
use Modules\System\Models\Notification;

class DocumentationTaskDeadlineService
{
    private const STAFF_COUNTED_STATUSES = ['pending', 'assigned', 'in_progress'];

    public function __construct(private AlertDispatcher $alerts)
    {
    }

    public function process(): array
    {
        return DB::transaction(function () {
            $now = now();

            $reminded = DocumentationTask::query()
                ->whereIn('status', self::STAFF_COUNTED_STATUSES)
                ->whereNotNull('reminder_at')
                ->whereNull('reminded_at')
                ->where('reminder_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            $reminded->each(function (DocumentationTask $task) use ($now) {
                $task->update(['reminded_at' => $now, 'updated_at' => $now]);
                $this->recordNotification($task, 'documentation_task_reminder');
                $this->alerts->trigger('deadline_near', $this->alertContext($task), "task-{$task->id}-reminder");
            });

            $escalated = DocumentationTask::query()
                ->whereIn('status', self::STAFF_COUNTED_STATUSES)
                ->whereNotNull('escalation_at')
                ->whereNull('escalated_at')
                ->where('escalation_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            $escalated->each(function (DocumentationTask $task) use ($now) {
                $task->update(['escalated_at' => $now, 'updated_at' => $now]);
                $this->recordNotification($task, 'documentation_task_escalation');
                $this->alerts->trigger('overdue', $this->alertContext($task), "task-{$task->id}-escalation");
            });

            $goingOverdue = DocumentationTask::query()
                ->whereIn('status', self::STAFF_COUNTED_STATUSES)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $now)
                ->get();

            $overdue = DocumentationTask::query()
                ->whereIn('status', self::STAFF_COUNTED_STATUSES)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $now)
                ->update(['status' => 'overdue', 'updated_at' => $now]);

            // Dedupe key is per task, so a task only ever raises `overdue` once
            // even though this command runs every five minutes.
            $goingOverdue->each(
                fn (DocumentationTask $task) => $this->alerts->trigger('overdue', $this->alertContext($task), "task-{$task->id}-overdue")
            );

            return [
                'reminded' => $reminded->count(),
                'escalated' => $escalated->count(),
                'overdue' => $overdue,
            ];
        });
    }

    public function summary(): array
    {
        $today = now();

        return [
            'documentation_tasks' => [
                'active' => DocumentationTask::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'due_today' => DocumentationTask::whereNotIn('status', ['completed', 'cancelled'])
                    ->whereBetween('due_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                    ->count(),
                'overdue' => DocumentationTask::where(function ($query) use ($today) {
                    $query->where('status', 'overdue')
                        ->orWhere(function ($nested) use ($today) {
                            $nested->whereIn('status', self::STAFF_COUNTED_STATUSES)
                                ->whereNotNull('due_at')
                                ->where('due_at', '<', $today);
                        });
                })->count(),
                'reminders_ready' => DocumentationTask::whereNotIn('status', ['completed', 'cancelled'])
                    ->whereNotNull('reminder_at')
                    ->whereNull('reminded_at')
                    ->where('reminder_at', '<=', $today)
                    ->count(),
                'escalations_ready' => DocumentationTask::whereNotIn('status', ['completed', 'cancelled'])
                    ->whereNotNull('escalation_at')
                    ->whereNull('escalated_at')
                    ->where('escalation_at', '<=', $today)
                    ->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function alertContext(DocumentationTask $task): array
    {
        $task->loadMissing('client');

        return [
            'client_id' => $task->client_id,
            'client_reference' => $task->client?->reference_no,
            'client_name' => $task->client?->full_name,
            'task_id' => $task->id,
            'task_title' => $task->title,
            'priority' => $task->priority,
            'status' => $task->status,
            'assigned_role' => $task->assigned_role,
            'due_at' => $task->due_at?->format('d M Y H:i'),
        ];
    }

    private function recordNotification(DocumentationTask $task, string $type): void
    {
        $task->loadMissing('client');
        $isEscalation = $type === 'documentation_task_escalation';

        Notification::create([
            'user_id' => $isEscalation ? $task->supervisor_id : $task->assigned_user_id,
            'role' => $isEscalation
                ? ($task->supervisor_id ? null : 'Supervisor')
                : ($task->assigned_user_id ? null : $task->assigned_role),
            'client_id' => $task->client_id,
            'documentation_task_id' => $task->id,
            'type' => $type,
            'title' => $isEscalation ? 'Documentation task escalated' : 'Documentation task reminder',
            'body' => trim(sprintf(
                '%s for %s%s.',
                $task->title,
                $task->client?->reference_no ?? 'client',
                $task->due_at ? ' is due '.$task->due_at->format('d M Y H:i') : ''
            )),
            'metadata' => [
                'priority' => $task->priority,
                'status' => $task->status,
                'due_at' => $task->due_at?->toISOString(),
            ],
        ]);
    }
}
