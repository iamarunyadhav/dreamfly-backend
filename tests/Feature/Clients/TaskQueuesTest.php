<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Tests\TestCase;

class TaskQueuesTest extends TestCase
{
    private function client(array $overrides = []): Client
    {
        return Client::create([
            'reference_no' => 'DF-130-2026',
            'full_name' => 'Queue Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_my_tasks_include_direct_and_role_assignments(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Documentation Unit Staff');
        $user->givePermissionTo('documentation-unit.view');
        $client = $this->client();

        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Direct task',
            'status' => 'assigned',
            'assigned_user_id' => $user->id,
        ]);
        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Role task',
            'status' => 'pending',
            'assigned_role' => 'Documentation Unit Staff',
        ]);
        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Completed task',
            'status' => 'completed',
            'assigned_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/tasks/my');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['title' => 'Direct task']);
        $response->assertJsonFragment(['title' => 'Role task']);
        $response->assertJsonMissing(['title' => 'Completed task']);
    }

    /** Lets a "Start" notification link land someone on their own tasks for one client only. */
    public function test_my_tasks_can_be_filtered_to_one_client(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('documentation-unit.view');
        $clientA = $this->client();
        $clientB = $this->client(['reference_no' => 'DF-131-2026']);

        DocumentationTask::create(['client_id' => $clientA->id, 'title' => 'Task A', 'status' => 'assigned', 'assigned_user_id' => $user->id]);
        DocumentationTask::create(['client_id' => $clientB->id, 'title' => 'Task B', 'status' => 'assigned', 'assigned_user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/tasks/my?client={$clientA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['title' => 'Task A']);
        $response->assertJsonMissing(['title' => 'Task B']);
    }

    public function test_pending_and_overdue_task_queues_filter_active_work(): void
    {
        Carbon::setTestNow('2026-07-22 12:00:00');
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('documentation-unit.view');
        $client = $this->client();

        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Pending task',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);
        DocumentationTask::create([
            'client_id' => $client->id,
            'title' => 'Late task',
            'status' => 'assigned',
            'due_at' => now()->subHour(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/tasks/pending')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)->getJson('/api/v1/tasks/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Late task']);
    }
}
