<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;

class CaseStepResetTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(string $stage = 'admin_summary'): Client
    {
        return Client::create([
            'reference_no' => 'DF-710-2026',
            'full_name' => 'Reset Case Client',
            'passport_no' => 'P710',
            'phone' => '+94760000710',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 200000,
            'paid_amount' => 0,
            'current_stage' => $stage,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_reset_a_completed_step_and_cascade_resets_later_steps(): void
    {
        $user = $this->user(['clients.view', 'clients.edit', 'case-steps.reset']);
        $client = $this->client('admin_summary');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $adminStep = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$adminStep->id}/advance")->assertOk();
        $this->assertSame('application_unit', $client->refresh()->current_stage);

        $response = $this->actingAs($user)->postJson("/api/v1/case-steps/{$adminStep->id}/reset", [
            'reason' => 'Supervisor field was wrong, redoing it.',
        ]);

        $response->assertOk();

        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->value('status'));
        $this->assertSame('pending', CaseStep::where('client_id', $client->id)->where('key', 'application_unit')->value('status'));
        $this->assertSame('admin_summary', $client->refresh()->current_stage);
    }

    public function test_manager_cannot_reset_a_completed_step(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('Manager');

        $admin = $this->user(['clients.view', 'clients.edit', 'case-steps.reset']);
        $client = $this->client('admin_summary');
        $this->actingAs($admin)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $adminStep = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();
        $this->actingAs($admin)->postJson("/api/v1/case-steps/{$adminStep->id}/advance")->assertOk();

        $this->actingAs($manager)->postJson("/api/v1/case-steps/{$adminStep->id}/reset", [
            'reason' => 'Trying anyway.',
        ])->assertForbidden();
    }

    public function test_reset_rejects_a_step_that_was_never_completed(): void
    {
        $user = $this->user(['clients.view', 'clients.edit', 'case-steps.reset']);
        $client = $this->client('admin_summary');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $adminStep = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$adminStep->id}/reset", [
            'reason' => 'Not completed yet.',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_reset_requires_a_reason(): void
    {
        $user = $this->user(['clients.view', 'clients.edit', 'case-steps.reset']);
        $client = $this->client('admin_summary');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $adminStep = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$adminStep->id}/advance")->assertOk();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$adminStep->id}/reset", [])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
    }
}
