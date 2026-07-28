<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\System\Models\Notification;
use Tests\TestCase;

class DocumentationTaskDeadlineAutomationTest extends TestCase
{
    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-121-2026',
            'full_name' => 'Deadline Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
        ]);
    }

    public function test_deadline_command_marks_reminders_escalations_and_overdue_without_penalizing_external_waiting(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        $client = $this->client();

        $task = DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Police clearance',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_at' => now()->subHour(),
            'reminder_at' => now()->subHours(2),
            'escalation_at' => now()->subMinutes(10),
        ]);

        $externalWaiting = DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Embassy response',
            'status' => 'waiting_third_party',
            'due_at' => now()->subDay(),
            'reminder_at' => now()->subDay(),
            'escalation_at' => now()->subDay(),
        ]);

        $this->artisan('documentation-tasks:process-deadlines')->assertSuccessful();

        $task->refresh();
        $externalWaiting->refresh();

        $this->assertSame('overdue', $task->status);
        $this->assertNotNull($task->reminded_at);
        $this->assertNotNull($task->escalated_at);
        $this->assertSame('waiting_third_party', $externalWaiting->status);
        $this->assertNull($externalWaiting->reminded_at);
        $this->assertNull($externalWaiting->escalated_at);

        $this->assertDatabaseHas('notifications', [
            'documentation_task_id' => $task->id,
            'type' => 'documentation_task_reminder',
            'title' => 'Documentation task reminder',
        ]);
        $this->assertDatabaseHas('notifications', [
            'documentation_task_id' => $task->id,
            'type' => 'documentation_task_escalation',
            'title' => 'Documentation task escalated',
        ]);
    }

    public function test_deadline_notifications_are_created_once_per_event(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        $client = $this->client();

        $task = DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Jewel valuation',
            'status' => 'assigned',
            'assigned_role' => 'Documentation Unit Staff',
            'reminder_at' => now()->subMinute(),
            'escalation_at' => now()->subMinute(),
        ]);

        $this->artisan('documentation-tasks:process-deadlines')->assertSuccessful();
        $this->artisan('documentation-tasks:process-deadlines')->assertSuccessful();

        $this->assertSame(1, Notification::where('documentation_task_id', $task->id)->where('type', 'documentation_task_reminder')->count());
        $this->assertSame(1, Notification::where('documentation_task_id', $task->id)->where('type', 'documentation_task_escalation')->count());
    }

    public function test_dashboard_summary_surfaces_documentation_task_counts(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        $user = User::factory()->create(['status' => 'active']);
        $client = $this->client();

        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'House valuation',
            'status' => 'assigned',
            'due_at' => now()->subHour(),
            'reminder_at' => now()->subMinutes(30),
            'escalation_at' => now()->addHour(),
        ]);

        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Appointment',
            'status' => 'pending',
            'due_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/summary');

        $response->assertOk();
        $response->assertJsonPath('data.documentation_tasks.active', 2);
        $response->assertJsonPath('data.documentation_tasks.due_today', 2);
        $response->assertJsonPath('data.documentation_tasks.overdue', 1);
        $response->assertJsonPath('data.documentation_tasks.reminders_ready', 1);
    }
}
