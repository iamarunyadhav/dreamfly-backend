<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\System\Models\Notification;
use Tests\TestCase;

class DocumentationTasksTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-120-2026',
            'full_name' => 'Documentation Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
        ]);
    }

    public function test_documentation_task_can_be_created_with_assignment_deadline_and_escalation_fields(): void
    {
        $user = $this->staff('documentation-unit.create');
        $assignee = User::factory()->create(['status' => 'active']);
        $supervisor = User::factory()->create(['status' => 'active']);
        $client = $this->client();

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/documentation-tasks", [
            'title' => 'Police clearance',
            'description' => 'Prepare police clearance before submission.',
            'assigned_user_id' => $assignee->id,
            'assigned_role' => 'Documentation Unit Staff',
            'supervisor_id' => $supervisor->id,
            'priority' => 'high',
            'status' => 'assigned',
            'start_at' => '2026-07-22 09:00:00',
            'due_at' => '2026-07-24 17:30:00',
            'reminder_at' => '2026-07-24 09:00:00',
            'escalation_at' => '2026-07-25 09:00:00',
            'notes' => 'Client must bring original NIC.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Police clearance');
        $response->assertJsonPath('data.status', 'assigned');
        $response->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('documentation_tasks', [
            'client_id' => $client->id,
            'assigned_user_id' => $assignee->id,
            'supervisor_id' => $supervisor->id,
            'status' => 'assigned',
            'created_by' => $user->id,
        ]);
    }

    public function test_documentation_task_update_sets_completed_timestamp(): void
    {
        $user = $this->staff('documentation-unit.update');
        $client = $this->client();
        $task = DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'House valuation',
            'status' => 'in_progress',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/documentation-tasks/{$task->id}", [
            'status' => 'completed',
            'notes' => 'Report collected.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($task->refresh()->completed_at);
    }

    public function test_user_without_permission_cannot_create_documentation_task(): void
    {
        $response = $this->actingAs(User::factory()->create(['status' => 'active']))
            ->postJson("/api/v1/clients/{$this->client()->id}/documentation-tasks", [
                'title' => 'Police clearance',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('documentation_tasks', 0);
    }

    public function test_file_can_be_attached_to_a_task_independent_of_its_status(): void
    {
        $user = $this->staff('documentation-unit.update');
        $client = $this->client();
        $task = DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'House valuation',
            'status' => 'in_progress',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/clients/{$client->id}/documentation-tasks/{$task->id}/file",
            ['file' => UploadedFile::fake()->create('valuation.pdf', 200, 'application/pdf')],
        );

        $response->assertOk();
        $this->assertSame('in_progress', $response->json('data.status'));
        $fileId = $response->json('data.file_id');
        $this->assertNotNull($fileId);
        $this->assertSame('valuation.pdf', $response->json('data.file.name'));
        $this->assertDatabaseHas('files', ['id' => $fileId, 'client_id' => $client->id]);

        // A second upload supersedes the first as a new version rather than orphaning it.
        $second = $this->actingAs($user)->postJson(
            "/api/v1/clients/{$client->id}/documentation-tasks/{$task->id}/file",
            ['file' => UploadedFile::fake()->create('valuation-corrected.pdf', 200, 'application/pdf')],
        );

        $second->assertOk();
        $this->assertNotSame($fileId, $second->json('data.file_id'));
        $this->assertDatabaseHas('files', ['id' => $fileId, 'is_current' => false]);
    }

    public function test_putting_a_task_on_hold_notifies_supervisor_and_admin_once(): void
    {
        $user = $this->staff('documentation-unit.update');
        $supervisor = User::factory()->create(['status' => 'active', 'email' => 'super@dreamfly.test', 'phone' => '94770000010']);
        $admin = User::factory()->create(['status' => 'active', 'email' => 'admin2@dreamfly.test', 'phone' => '94770000011']);
        $admin->assignRole('Admin');

        $client = Client::create([
            'reference_no' => 'DF-121-2026',
            'full_name' => 'Blocker Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
            'assigned_supervisor_id' => $supervisor->id,
        ]);
        $task = DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'House valuation',
            'status' => 'in_progress',
            'priority' => 'normal',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/documentation-tasks/{$task->id}", [
            'status' => 'on_hold',
            'hold_reason' => 'Waiting on the valuer to confirm a date.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('messages', ['recipient' => 'super@dreamfly.test', 'channel' => 'email']);
        $this->assertDatabaseHas('messages', ['recipient' => 'admin2@dreamfly.test', 'channel' => 'email']);
        $this->assertSame(1, Notification::where('user_id', $supervisor->id)->where('type', 'documentation_task_blocked')->count());
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'documentation_task_blocked')->count());

        // Re-saving the already-on_hold task (e.g. editing notes) must not re-notify.
        $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/documentation-tasks/{$task->id}", [
            'notes' => 'Still waiting.',
        ])->assertOk();

        $this->assertSame(1, Notification::where('user_id', $supervisor->id)->where('type', 'documentation_task_blocked')->count());
    }
}
