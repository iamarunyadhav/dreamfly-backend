<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;
use Tests\TestCase;

class DocumentPrepUnitTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(array $overrides = []): Client
    {
        $client = Client::create([
            'reference_no' => 'DF-140-2026',
            'full_name' => 'Document Prep Client',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'current_stage' => 'application_unit',
            'status' => 'active',
            ...$overrides,
        ]);
        app(CaseStepService::class)->initializeForClient($client);

        return $client;
    }

    /** Advances a freshly-initialized client all the way to document_prep_unit. */
    private function atDocumentPrepUnit(array $overrides = []): Client
    {
        $client = $this->client($overrides);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo(['clients.edit']);

        // client() already starts at 'application_unit', so admin_summary is
        // pre-completed by initializeForClient() - only these two need advancing.
        foreach (['application_unit', 'documentation_unit'] as $key) {
            $step = CaseStep::where('client_id', $client->id)->where('key', $key)->first();
            $this->actingAs($admin)->postJson("/api/v1/case-steps/{$step->id}/advance")->assertOk();
        }

        return $client->refresh();
    }

    public function test_generate_summary_creates_a_pdf_in_the_documentation_unit_folder(): void
    {
        $client = $this->atDocumentPrepUnit();
        DocumentationTask::create(['client_id' => $client->id, 'title' => 'Police clearance', 'status' => 'completed']);

        $response = $this->actingAs($this->staff('document-prep-unit.view'))
            ->postJson("/api/v1/clients/{$client->id}/document-prep-unit/generate-summary");

        $response->assertStatus(201);
        $this->assertSame('application/pdf', $response->json('data.mime_type'));
        $this->assertDatabaseHas('files', ['id' => $response->json('data.id'), 'client_id' => $client->id]);
        $this->assertDatabaseHas('folders', ['name' => 'Documentation Unit']);
    }

    public function test_complete_requires_the_document_prep_unit_stage(): void
    {
        $client = $this->client(); // still at application_unit

        $this->actingAs($this->staff('document-prep-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/document-prep-unit/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_stage');
    }

    public function test_complete_advances_the_case_and_notifies_the_chosen_next_assignee(): void
    {
        $client = $this->atDocumentPrepUnit();
        $mano = User::factory()->create(['status' => 'active', 'name' => 'Mano', 'email' => 'mano@dreamfly.test', 'phone' => '94770000021']);

        $response = $this->actingAs($this->staff('document-prep-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/document-prep-unit/complete", [
                'next_assigned_user_id' => $mano->id,
            ]);

        $response->assertOk();
        $this->assertSame('upload_team', $client->refresh()->current_stage);
        $response->assertJsonPath('data.handoff.assignee.user_id', $mano->id);
        $this->assertDatabaseHas('messages', ['client_id' => $client->id, 'recipient' => 'mano@dreamfly.test']);
    }

    public function test_complete_falls_back_to_supervisor_when_no_one_chosen(): void
    {
        $supervisor = User::factory()->create(['status' => 'active', 'email' => 'sup2@dreamfly.test']);
        $client = $this->atDocumentPrepUnit(['assigned_supervisor_id' => $supervisor->id]);

        $response = $this->actingAs($this->staff('document-prep-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/document-prep-unit/complete");

        $response->assertOk();
        $response->assertJsonPath('data.handoff.assignee.user_id', $supervisor->id);
    }

    public function test_user_without_permission_cannot_complete_document_prep_unit(): void
    {
        $client = $this->atDocumentPrepUnit();

        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->postJson("/api/v1/clients/{$client->id}/document-prep-unit/complete")
            ->assertStatus(403);
    }
}
