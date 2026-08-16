<?php

namespace Modules\Clients\Services;

use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\Clients\Services\Concerns\SendsStaffNotifications;
use Modules\Communications\Services\MessageService;

/**
 * Fires the moment a task is put on hold with a blocker reason: the client's
 * assigned Supervisor and every Admin/Super Admin are emailed/WhatsApp'd
 * immediately, regardless of who raised the blocker. Unlike the generic
 * stage-lifecycle events, recipients here are fixed (this client's own
 * supervisor + admins) so this goes out directly rather than through the
 * operator-configured AlertDispatcher.
 */
class DocumentationTaskBlockerNotifier
{
    use SendsStaffNotifications;

    public function __construct(private MessageService $messages)
    {
    }

    public function notifyBlocked(Client $client, DocumentationTask $task, int $raisedByUserId): void
    {
        $link = config('app.frontend_url')."/clients?open={$client->id}&tab=workflow";
        $subject = "Blocker on \"{$task->title}\" - {$client->full_name}";
        $body = "\"{$task->title}\" for {$client->full_name} ({$client->reference_no}) was put on hold.\n\n"
            ."Reason: {$task->hold_reason}\n\nView: {$link}";

        $recipients = collect([$client->supervisor])->merge($this->admins())->filter()->unique('id');

        foreach ($recipients as $recipient) {
            $this->sendToUser($recipient, $client, $subject, $body, $raisedByUserId, 'documentation_unit');
            $this->recordNotification($recipient, $client, 'documentation_task_blocked', $subject, $body);
        }
    }
}
