<?php

namespace Modules\Clients\Services;

use Modules\Clients\Models\Client;
use Modules\Clients\Services\Concerns\SendsStaffNotifications;
use Modules\Communications\Services\MessageService;

/**
 * Fires when a Correction Unit's (documentation_unit) task assignments are
 * confirmed: every staff member with a task on this client is emailed/WhatsApp'd
 * a summary of their own tasks, and every Admin/Super Admin is notified that the
 * assignment round is confirmed - regardless of who did the confirming.
 */
class DocumentationTaskAssignmentNotifier
{
    use SendsStaffNotifications;

    public function __construct(private MessageService $messages)
    {
    }

    /**
     * @return array{staff: array<int, array{user_id:int,name:string,channels_sent:string[]}>, admins_notified: int}
     */
    public function notifyAssignmentsConfirmed(Client $client, int $confirmedByUserId): array
    {
        $tasksByAssignee = $client->documentationTasks()
            ->whereNotNull('assigned_user_id')
            ->with('assignedUser')
            ->get()
            ->groupBy('assigned_user_id');

        $viewLink = config('app.frontend_url')."/clients?open={$client->id}&tab=workflow";
        $startLink = config('app.frontend_url')."/tasks/my?client={$client->id}";
        $staffSummary = [];

        foreach ($tasksByAssignee as $tasks) {
            $user = $tasks->first()->assignedUser;
            if (! $user) {
                continue;
            }

            $lines = $tasks->map(function ($task) {
                $due = $task->due_at ? ' (due '.$task->due_at->format('d M Y').')' : '';

                return "- {$task->title}{$due}";
            })->implode("\n");

            $subject = "Correction Unit tasks assigned - {$client->full_name}";
            $body = "Hi {$user->name},\n\nYou have been assigned the following Correction Unit task(s) for "
                ."{$client->full_name} ({$client->reference_no}):\n{$lines}\n\nView: {$viewLink}\nStart: {$startLink}";

            $channelsSent = $this->sendToUser($user, $client, $subject, $body, $confirmedByUserId, 'documentation_unit');
            $this->recordNotification($user, $client, 'documentation_unit_assignment', 'Correction Unit tasks assigned', $body);

            $staffSummary[] = ['user_id' => $user->id, 'name' => $user->name, 'channels_sent' => $channelsSent];
        }

        $admins = $this->admins();
        $assignedNames = collect($staffSummary)->pluck('name')->implode(', ');
        $adminBody = "Correction Unit task assignments confirmed for {$client->full_name} ({$client->reference_no})."
            .($assignedNames !== '' ? " Assigned to: {$assignedNames}." : ' No tasks are currently assigned to anyone.')
            ."\n\nView: {$viewLink}";

        foreach ($admins as $admin) {
            $this->sendToUser($admin, $client, "Correction Unit assignments confirmed - {$client->full_name}", $adminBody, $confirmedByUserId, 'documentation_unit');
            $this->recordNotification($admin, $client, 'documentation_unit_assignment_admin', 'Correction Unit assignments confirmed', $adminBody);
        }

        return ['staff' => $staffSummary, 'admins_notified' => $admins->count()];
    }
}
