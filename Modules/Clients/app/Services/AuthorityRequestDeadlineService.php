<?php

namespace Modules\Clients\Services;

use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\AuthorityRequest;
use Modules\Communications\Services\AlertDispatcher;

/**
 * Reminds and escalates open authority requests (embassy/VFS follow-up asks)
 * as their due date approaches or passes, mirroring
 * DocumentationTaskDeadlineService's reminder/overdue split.
 */
class AuthorityRequestDeadlineService
{
    /** How many days out a request starts reminding. */
    private const REMINDER_WINDOW_DAYS = 2;

    public function __construct(private AlertDispatcher $alerts)
    {
    }

    public function process(): array
    {
        return DB::transaction(function () {
            $now = now();

            $reminded = AuthorityRequest::query()
                ->whereNotIn('status', AuthorityRequest::RESOLVED_STATUSES)
                ->whereNotNull('due_at')
                ->whereNull('reminded_at')
                ->whereDate('due_at', '<=', $now->copy()->addDays(self::REMINDER_WINDOW_DAYS))
                ->lockForUpdate()
                ->get();

            $reminded->each(function (AuthorityRequest $request) use ($now) {
                $request->update(['reminded_at' => $now]);
                $this->trigger($request, 'deadline_near', 'reminder');
            });

            $goingOverdue = AuthorityRequest::query()
                ->whereNotIn('status', [...AuthorityRequest::RESOLVED_STATUSES, 'overdue'])
                ->whereNotNull('due_at')
                ->whereDate('due_at', '<', $now)
                ->get();

            $goingOverdue->each(function (AuthorityRequest $request) {
                $request->update(['status' => 'overdue']);
                $this->trigger($request, 'overdue', 'overdue');
            });

            return [
                'reminded' => $reminded->count(),
                'overdue' => $goingOverdue->count(),
            ];
        });
    }

    private function trigger(AuthorityRequest $request, string $event, string $dedupeSuffix): void
    {
        $request->loadMissing('client');

        $this->alerts->trigger($event, [
            'client_id' => $request->client_id,
            'client_reference' => $request->client?->reference_no,
            'client_name' => $request->client?->full_name,
            'authority' => $request->authority,
            // Shares the `task_title`/`due_at` placeholder names with the
            // documentation-task deadline triggers so the default alert body
            // (and any admin-configured message template) reads the same way
            // regardless of which source raised deadline_near/overdue.
            'task_title' => $request->authority.' - '.$request->title,
            'due_at' => optional($request->due_at)->format('d M Y'),
        ], "authority-request-{$request->id}-{$dedupeSuffix}");
    }
}
