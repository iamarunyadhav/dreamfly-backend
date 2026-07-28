<?php

namespace Modules\Communications\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Clients\Models\Client;
use Modules\Communications\Models\AlertDispatch;
use Modules\Communications\Models\AlertTemplate;
use Modules\System\Models\Notification;
use Throwable;

/**
 * Turns a domain event into the alerts an operator configured for it.
 *
 * `trigger()` queues one dispatch row per matching enabled template (honouring
 * `delay_minutes`), and `flushDue()` — driven by the `alerts:dispatch` schedule —
 * actually sends them. Queueing rather than sending inline is what makes delays
 * and repeats work, and keeps a record of every alert that fired.
 */
class AlertDispatcher
{
    public function __construct(private MessageService $messages)
    {
    }

    /**
     * Queue every enabled alert configured for this trigger whose conditions the
     * context satisfies. Safe to call from anywhere; never throws into the caller.
     *
     * @param  array<string, mixed>  $context  flat-ish payload the conditions and
     *                                         message placeholders read from
     * @return int number of dispatches queued
     */
    public function trigger(string $trigger, array $context = [], ?string $dedupeKey = null): int
    {
        try {
            $templates = AlertTemplate::where('trigger', $trigger)->where('is_enabled', true)->get();

            $queued = 0;
            foreach ($templates as $template) {
                if (! $this->matchesConditions($template, $context)) {
                    continue;
                }

                $dispatch = AlertDispatch::firstOrCreate(
                    [
                        'alert_template_id' => $template->id,
                        'dedupe_key' => $dedupeKey,
                    ],
                    [
                        'trigger' => $trigger,
                        'client_id' => $context['client_id'] ?? null,
                        'context' => $context,
                        'due_at' => now()->addMinutes((int) $template->delay_minutes),
                        'status' => 'pending',
                    ],
                );

                if ($dispatch->wasRecentlyCreated) {
                    $queued++;
                }
            }

            return $queued;
        } catch (Throwable $e) {
            // An alert must never break the business action that raised it.
            Log::warning('Alert trigger failed.', ['trigger' => $trigger, 'error' => $e->getMessage()]);

            return 0;
        }
    }

    /**
     * Send every pending dispatch that is due.
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function flushDue(): array
    {
        $due = AlertDispatch::with('template.messageTemplate')
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->limit(200)
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($due as $dispatch) {
            $template = $dispatch->template;

            if (! $template || ! $template->is_enabled) {
                $dispatch->update(['status' => 'skipped', 'failure_reason' => 'Template removed or disabled.']);
                $skipped++;

                continue;
            }

            try {
                $count = $this->send($dispatch, $template);
                $dispatch->update([
                    'status' => $count > 0 ? 'sent' : 'skipped',
                    'sent_at' => $count > 0 ? now() : null,
                    'recipients_notified' => $count,
                    'failure_reason' => $count > 0 ? null : 'No recipients resolved.',
                ]);
                $count > 0 ? $sent++ : $skipped++;
            } catch (Throwable $e) {
                $dispatch->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
                $failed++;
                Log::warning('Alert dispatch failed.', ['dispatch_id' => $dispatch->id, 'error' => $e->getMessage()]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /** @return int recipients actually notified */
    private function send(AlertDispatch $dispatch, AlertTemplate $template): int
    {
        $context = $dispatch->context ?? [];
        $subject = $this->render($template->messageTemplate?->subject ?? $template->name, $context);
        $body = $this->render(
            $template->messageTemplate?->body ?? $this->defaultBody($dispatch->trigger),
            $context,
        );

        $notified = 0;

        foreach ($template->channels ?? [] as $channel) {
            $notified += $channel === 'internal'
                ? $this->notifyInternal($template, $dispatch, $subject, $body)
                : $this->notifyExternal($channel, $template, $dispatch, $subject, $body);
        }

        return $notified;
    }

    private function notifyInternal(AlertTemplate $template, AlertDispatch $dispatch, string $subject, string $body): int
    {
        $rules = $template->recipient_rules ?? [];
        $count = 0;

        foreach ((array) ($rules['users'] ?? []) as $userId) {
            Notification::create([
                'user_id' => (int) $userId,
                'client_id' => $dispatch->client_id,
                'type' => 'alert.'.$dispatch->trigger,
                'title' => $subject,
                'body' => $body,
                'metadata' => ['alert_template_id' => $template->id, 'trigger' => $dispatch->trigger],
            ]);
            $count++;
        }

        // A role recipient becomes one role-targeted notification, which the
        // existing notification API already fans out to everyone in that role.
        foreach ((array) ($rules['roles'] ?? []) as $role) {
            Notification::create([
                'role' => (string) $role,
                'client_id' => $dispatch->client_id,
                'type' => 'alert.'.$dispatch->trigger,
                'title' => $subject,
                'body' => $body,
                'metadata' => ['alert_template_id' => $template->id, 'trigger' => $dispatch->trigger],
            ]);
            $count++;
        }

        return $count;
    }

    private function notifyExternal(string $channel, AlertTemplate $template, AlertDispatch $dispatch, string $subject, string $body): int
    {
        $count = 0;

        foreach ($this->externalRecipients($channel, $template, $dispatch) as $recipient) {
            $this->messages->send([
                'channel' => $channel,
                'recipient' => $recipient,
                'client_id' => $dispatch->client_id,
                'subject' => $subject,
                'body' => $body,
            ], $template->created_by);
            $count++;
        }

        return $count;
    }

    /** @return Collection<int, string> */
    private function externalRecipients(string $channel, AlertTemplate $template, AlertDispatch $dispatch): Collection
    {
        $rules = $template->recipient_rules ?? [];
        $recipients = collect();
        $wantsEmail = $channel === 'email';

        if (! empty($rules['client']) && $dispatch->client_id) {
            $client = Client::find($dispatch->client_id);
            $recipients->push($wantsEmail ? $client?->email : $client?->phone);
        }

        $userIds = collect((array) ($rules['users'] ?? []))->filter()->all();
        $roles = collect((array) ($rules['roles'] ?? []))->filter()->all();

        if ($userIds || $roles) {
            $recipients = $recipients->merge(
                User::query()
                    ->when($userIds && ! $roles, fn ($q) => $q->whereIn('id', $userIds))
                    ->when($roles && ! $userIds, fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles)))
                    ->when($roles && $userIds, fn ($q) => $q->where(function ($outer) use ($userIds, $roles) {
                        $outer->whereIn('id', $userIds)
                            ->orWhereHas('roles', fn ($r) => $r->whereIn('name', $roles));
                    }))
                    ->get()
                    ->map(fn (User $user) => $wantsEmail ? $user->email : ($user->phone ?? null)),
            );
        }

        // Explicit addresses configured on the rule, used as-is.
        $recipients = $recipients->merge((array) ($rules['addresses'] ?? []));

        return $recipients->filter()->unique()->values();
    }

    /**
     * Conditions are matched as "every key must match". A scalar means equality;
     * an array means "any of". Missing context keys never match.
     */
    private function matchesConditions(AlertTemplate $template, array $context): bool
    {
        foreach ($template->conditions ?? [] as $key => $expected) {
            $actual = data_get($context, $key);

            $matched = is_array($expected)
                ? in_array($actual, $expected, false)
                : $actual == $expected;

            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    /** Replaces {{key}} placeholders from the trigger context. */
    private function render(string $text, array $context): string
    {
        return preg_replace_callback(
            '/\{\{\s*([\w.]+)\s*\}\}/',
            fn (array $match) => (string) (data_get($context, $match[1]) ?? ''),
            $text,
        ) ?? $text;
    }

    private function defaultBody(string $trigger): string
    {
        return match ($trigger) {
            'client_converted' => 'Lead converted to client {{client_reference}} ({{client_name}}).',
            'payment_received' => 'Payment of LKR {{amount}} recorded for {{client_reference}}.',
            'invoice_generated' => 'Invoice {{invoice_reference}} issued for {{client_reference}}.',
            'invoice_overdue' => 'Invoice {{invoice_reference}} for {{client_reference}} is overdue.',
            'stage_assigned' => 'Case {{client_reference}} has moved to {{stage_name}}.',
            'stage_completed' => 'Step {{stage_name}} completed for {{client_reference}}.',
            'case_closed' => 'Case {{client_reference}} is now closed.',
            'deadline_near' => 'Task "{{task_title}}" for {{client_reference}} is due {{due_at}}.',
            'overdue' => 'Task "{{task_title}}" for {{client_reference}} is overdue.',
            'agreement_generated' => 'Agreement {{agreement_reference}} generated.',
            'agreement_shared' => 'Agreement {{agreement_reference}} shared with the client.',
            default => 'Dream Fly alert: '.str_replace('_', ' ', $trigger).' for {{client_reference}}.',
        };
    }

    /** Remove dispatch history older than the retention window. */
    public function prune(int $days = 90): int
    {
        return DB::table('alert_dispatches')->where('created_at', '<', now()->subDays($days))->delete();
    }
}
