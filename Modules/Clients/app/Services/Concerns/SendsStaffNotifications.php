<?php

namespace Modules\Clients\Services\Concerns;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\System\Models\Notification;

/** Shared by the assignment notifiers: email+WhatsApp a staff member and log
 * an in-app Notification, keyed to a client. */
trait SendsStaffNotifications
{
    /** @return string[] channels actually sent on */
    private function sendToUser(User $user, Client $client, string $subject, string $body, int $senderId, string $workflowStep): array
    {
        $sent = [];

        foreach (['email' => $user->email, 'whatsapp' => $user->phone] as $channel => $recipient) {
            if (! $recipient) {
                continue;
            }

            $this->messages->send([
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'client_id' => $client->id,
                'workflow_step' => $workflowStep,
            ], $senderId);

            $sent[] = $channel;
        }

        return $sent;
    }

    private function recordNotification(User $user, Client $client, string $type, string $title, string $body): void
    {
        Notification::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function admins()
    {
        return User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['Admin', 'Super Admin']))->get();
    }
}
