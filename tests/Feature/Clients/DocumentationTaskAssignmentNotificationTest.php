<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\System\Models\Notification;
use Tests\TestCase;

class DocumentationTaskAssignmentNotificationTest extends TestCase
{
    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-200-2026',
            'full_name' => 'Notify Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'documentation_unit',
            'status' => 'active',
        ]);
    }

    public function test_confirming_assignments_notifies_each_assignee_and_every_admin(): void
    {
        $operator = User::factory()->create(['status' => 'active']);
        $operator->givePermissionTo('clients.edit');

        $admin = User::factory()->create(['status' => 'active', 'email' => 'admin@dreamfly.test', 'phone' => '94770000001']);
        $admin->assignRole('Admin');

        $nila = User::factory()->create(['status' => 'active', 'name' => 'Nila', 'email' => 'nila@dreamfly.test', 'phone' => '94770000002']);
        $mano = User::factory()->create(['status' => 'active', 'name' => 'Mano', 'email' => 'mano@dreamfly.test', 'phone' => '94770000003']);

        $client = $this->client();

        DocumentationTask::create(['client_id' => $client->id, 'title' => 'House valuation', 'assigned_user_id' => $nila->id, 'status' => 'assigned', 'priority' => 'high', 'created_by' => $operator->id]);
        DocumentationTask::create(['client_id' => $client->id, 'title' => 'Quotation', 'assigned_user_id' => $nila->id, 'status' => 'assigned', 'priority' => 'high', 'created_by' => $operator->id]);
        DocumentationTask::create(['client_id' => $client->id, 'title' => 'Appointment booking', 'assigned_user_id' => $mano->id, 'status' => 'assigned', 'priority' => 'high', 'created_by' => $operator->id]);
        // Unassigned tasks must not blow up the grouping or get anyone notified.
        DocumentationTask::create(['client_id' => $client->id, 'title' => 'Unassigned task', 'status' => 'pending', 'priority' => 'normal', 'created_by' => $operator->id]);

        $response = $this->actingAs($operator)->postJson("/api/v1/clients/{$client->id}/documentation-tasks/confirm-assignments");

        $response->assertOk();
        $staff = collect($response->json('data.staff'));
        $this->assertCount(2, $staff);
        $this->assertSame(['email', 'whatsapp'], $staff->firstWhere('user_id', $nila->id)['channels_sent']);
        $this->assertSame(['email', 'whatsapp'], $staff->firstWhere('user_id', $mano->id)['channels_sent']);
        $this->assertSame(1, $response->json('data.admins_notified'));

        $this->assertDatabaseHas('messages', ['recipient' => 'nila@dreamfly.test', 'channel' => 'email']);
        $this->assertDatabaseHas('messages', ['recipient' => '94770000002', 'channel' => 'whatsapp']);
        $this->assertDatabaseHas('messages', ['recipient' => 'mano@dreamfly.test', 'channel' => 'email']);
        $this->assertDatabaseHas('messages', ['recipient' => 'admin@dreamfly.test', 'channel' => 'email']);

        $this->assertSame(1, Notification::where('user_id', $nila->id)->where('type', 'documentation_unit_assignment')->count());
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'documentation_unit_assignment_admin')->count());

        $nilaMessage = \Modules\Communications\Models\Message::where('recipient', 'nila@dreamfly.test')->first();
        $this->assertStringContainsString('House valuation', $nilaMessage->body);
        $this->assertStringContainsString('Quotation', $nilaMessage->body);
        $this->assertStringNotContainsString('Appointment booking', $nilaMessage->body);
    }

    public function test_confirming_assignments_requires_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = $this->client();

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/documentation-tasks/confirm-assignments")
            ->assertStatus(403);
    }
}
