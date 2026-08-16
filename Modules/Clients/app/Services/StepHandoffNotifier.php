<?php

namespace Modules\Clients\Services;

use App\Models\User;
use Carbon\Carbon;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\Concerns\SendsStaffNotifications;
use Modules\Communications\Services\MessageService;

/**
 * Fires when one workflow step's "Complete and assign" hands the case to a
 * specific chosen staff member for the next step (Admin Summary -> Application
 * Unit, Application Unit -> Correction Unit): that person is emailed/WhatsApp'd
 * with a View/Start link, and every Admin/Super Admin is notified the handoff
 * happened - regardless of who completed the step.
 */
class StepHandoffNotifier
{
    use SendsStaffNotifications;

    public function __construct(private MessageService $messages)
    {
    }

    /**
     * @return array{assignee: array{user_id:int,name:string,channels_sent:string[]}, admins_notified: int}
     */
    public function notifyHandoff(
        Client $client,
        User $assignee,
        string $completedStepLabel,
        string $nextStepLabel,
        ?string $deadlineAt,
        int $actingUserId,
        string $workflowStep,
    ): array {
        $viewLink = config('app.frontend_url')."/clients?open={$client->id}&tab=workflow";
        $startLink = config('app.frontend_url')."/tasks/my?client={$client->id}";
        $deadlineText = $deadlineAt ? ' Deadline: '.Carbon::parse($deadlineAt)->format('d M Y H:i').'.' : '';

        $subject = "{$nextStepLabel} assigned - {$client->full_name}";
        $body = "Hi {$assignee->name},\n\n{$completedStepLabel} has been completed for {$client->full_name} ({$client->reference_no}). "
            ."Please follow up on {$nextStepLabel}.{$deadlineText}\n\nView: {$viewLink}\nStart: {$startLink}";

        $channelsSent = $this->sendToUser($assignee, $client, $subject, $body, $actingUserId, $workflowStep);
        $this->recordNotification($assignee, $client, 'step_handoff_assignment', "{$nextStepLabel} assigned", $body);

        $admins = $this->admins();
        $adminBody = "{$completedStepLabel} completed for {$client->full_name} ({$client->reference_no}). "
            ."{$nextStepLabel} assigned to {$assignee->name}.\n\nView: {$viewLink}";

        foreach ($admins as $admin) {
            $this->sendToUser($admin, $client, "{$completedStepLabel} completed - {$client->full_name}", $adminBody, $actingUserId, $workflowStep);
            $this->recordNotification($admin, $client, 'step_handoff_admin', "{$completedStepLabel} completed", $adminBody);
        }

        return [
            'assignee' => ['user_id' => $assignee->id, 'name' => $assignee->name, 'channels_sent' => $channelsSent],
            'admins_notified' => $admins->count(),
        ];
    }
}
