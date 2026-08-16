<?php

namespace Tests\Feature\Workflows;

use App\Models\User;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientCaseClosure;
use Modules\Clients\Models\ClientResponsibilityNotice;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;

class CaseStepRuntimeTest extends TestCase
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
            'reference_no' => 'DF-700-2026',
            'full_name' => 'Case Client',
            'passport_no' => 'P700',
            'phone' => '+94760000700',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 200000,
            'paid_amount' => 0,
            'current_stage' => $stage,
            'status' => 'active',
        ]);
    }

    public function test_initialize_creates_default_steps_aligned_to_current_stage(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('documentation_unit');

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $response->assertCreated();
        $steps = $response->json('data.steps');
        $this->assertCount(11, $steps);

        $byKey = collect($steps)->keyBy('key');
        $this->assertSame('completed', $byKey['admin_summary']['status']);
        $this->assertSame('completed', $byKey['application_unit']['status']);
        $this->assertSame('in_progress', $byKey['documentation_unit']['status']);
        $this->assertSame('pending', $byKey['document_prep_unit']['status']);
        $this->assertSame('pending', $byKey['supervisor_review']['status']);
        $this->assertTrue($byKey['documentation_unit']['requires_checklist']);
        $this->assertTrue($byKey['responsibility_notice']['requires_acknowledgement']);
    }

    public function test_unacknowledged_responsibility_notice_blocks_its_step(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('responsibility_notice');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $step = CaseStep::where('client_id', $client->id)->where('key', 'responsibility_notice')->first();

        // No notice at all - blocked.
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$step->id}/advance")
            ->assertStatus(422)
            ->assertJsonValidationErrors('acknowledgement');

        // A generated but unacknowledged notice is still blocked.
        $notice = ClientResponsibilityNotice::create([
            'client_id' => $client->id,
            'status' => 'shared',
            'acknowledged' => false,
        ]);

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$step->id}/advance")
            ->assertStatus(422)
            ->assertJsonValidationErrors('acknowledgement');

        // Once acknowledged the step completes and the case moves on.
        $notice->forceFill(['acknowledged' => true, 'acknowledged_at' => now(), 'status' => 'acknowledged'])->save();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$step->id}/advance")->assertOk();
        $this->assertSame('invoice', $client->refresh()->current_stage);
    }

    public function test_advance_completes_step_and_moves_client_forward(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('admin_summary');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $adminStep = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();
        $response = $this->actingAs($user)->postJson("/api/v1/case-steps/{$adminStep->id}/advance", ['notes' => 'Done']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
        $this->assertSame('application_unit', $client->refresh()->current_stage);
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'application_unit')->value('status'));
    }

    public function test_cannot_advance_out_of_order(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('admin_summary');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $laterStep = CaseStep::where('client_id', $client->id)->where('key', 'supervisor_review')->first();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$laterStep->id}/advance")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_required_checklist_blocks_step_completion(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('documentation_unit');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        // An outstanding required checklist item for the case.
        CaseChecklistItem::create([
            'client_id' => $client->id,
            'owner' => 'applicant',
            'source_index' => 0,
            'title' => 'Passport copy',
            'status' => 'missing',
            'is_required' => true,
            'document_required' => true,
        ]);

        $docStep = CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->first();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$docStep->id}/advance")
            ->assertStatus(422)
            ->assertJsonValidationErrors('checklist');
    }

    /**
     * A rejected-but-required document must also block completion, not just a
     * never-uploaded one - Correction Unit is responsible for re-verifying it
     * before this stage (and its handoff to the next stage) can proceed.
     */
    public function test_rejected_required_checklist_item_blocks_step_completion(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('documentation_unit');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        CaseChecklistItem::create([
            'client_id' => $client->id,
            'owner' => 'applicant',
            'source_index' => 0,
            'title' => 'Passport copy',
            'status' => 'rejected',
            'is_required' => true,
            'document_required' => true,
            'rejection_reason' => 'Blurry scan',
        ]);

        $docStep = CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->first();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$docStep->id}/advance")
            ->assertStatus(422)
            ->assertJsonValidationErrors('checklist');

        // Once the item is verified, the step can complete.
        CaseChecklistItem::where('client_id', $client->id)->update(['status' => 'verified']);

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$docStep->id}/advance")->assertOk();
        $this->assertSame('document_prep_unit', $client->refresh()->current_stage);
    }

    public function test_hold_and_resume_extends_due_date(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('admin_summary');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $step = CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->first();
        $originalDue = $step->due_at;
        $this->assertNotNull($originalDue);

        // Simulate the step having been held for a day.
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$step->id}/hold", ['reason' => 'Waiting for client'])
            ->assertOk()->assertJsonPath('data.status', 'on_hold');

        $step->refresh()->forceFill(['held_at' => now()->subDay()])->save();

        $this->actingAs($user)->postJson("/api/v1/case-steps/{$step->id}/resume")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->assertTrue($step->refresh()->due_at->greaterThan($originalDue));
    }

    public function test_completing_last_step_closes_case(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('visa_result');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        // visa_result is current; complete it, then complete the final closed step.
        $visaResult = CaseStep::where('client_id', $client->id)->where('key', 'visa_result')->first();
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$visaResult->id}/advance")->assertOk();

        $this->completeClosureRecord($client);

        $closed = CaseStep::where('client_id', $client->id)->where('key', 'closed')->first();
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$closed->id}/advance")->assertOk();

        $client->refresh();
        $this->assertSame('closed', $client->current_stage);
        $this->assertSame('closed', $client->status);
    }

    public function test_unrecorded_case_closure_blocks_the_closed_step(): void
    {
        $user = $this->user(['clients.view', 'clients.edit']);
        $client = $this->client('visa_result');
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);
        $this->actingAs($user)
            ->postJson('/api/v1/case-steps/'.CaseStep::where('client_id', $client->id)->where('key', 'visa_result')->value('id').'/advance')
            ->assertOk();

        $closed = CaseStep::where('client_id', $client->id)->where('key', 'closed')->first();

        // Nothing recorded at all.
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$closed->id}/advance")
            ->assertStatus(422)->assertJsonValidationErrors('closure');

        // Recorded but not archived.
        ClientCaseClosure::create(['client_id' => $client->id, 'handover_checklist' => [['title' => 'Original passport', 'returned' => true]]]);
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$closed->id}/advance")
            ->assertStatus(422)->assertJsonValidationErrors('closure');

        // Archived but not signed off (completed_at) yet.
        ClientCaseClosure::where('client_id', $client->id)->update(['archived' => true, 'archived_at' => now()]);
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$closed->id}/advance")
            ->assertStatus(422)->assertJsonValidationErrors('closure');

        // Fully completed - the step can now close.
        ClientCaseClosure::where('client_id', $client->id)->update(['completed_at' => now()]);
        $this->actingAs($user)->postJson("/api/v1/case-steps/{$closed->id}/advance")->assertOk();
        $this->assertSame('closed', $client->refresh()->current_stage);
    }

    private function completeClosureRecord(Client $client): void
    {
        ClientCaseClosure::create([
            'client_id' => $client->id,
            'handover_checklist' => [['title' => 'Original passport', 'returned' => true]],
            'archived' => true,
            'archived_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
