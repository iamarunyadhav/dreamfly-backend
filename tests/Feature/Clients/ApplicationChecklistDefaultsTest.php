<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Clients\Models\Client;
use Modules\Services\Models\Service;
use Tests\TestCase;

class ApplicationChecklistDefaultsTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(array $overrides = []): Client
    {
        return Client::create([
            'reference_no' => 'DF-200-2026',
            'full_name' => 'Nadeesha P',
            'passport_no' => 'N1122334',
            'phone' => '0771112222',
            'country' => 'United Kingdom',
            'visa_type' => 'Family Visit',
            'service_category' => 'visit_visa',
            'current_stage' => 'application_unit',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_defaults_come_from_the_global_library_when_no_service_is_configured(): void
    {
        ChecklistTemplate::create(['title' => 'Passport copy', 'owner' => 'applicant', 'category' => 'client_documents', 'is_required' => true, 'document_required' => true, 'status' => 'published', 'is_active' => true]);
        ChecklistTemplate::create(['title' => 'Draft item, not shown', 'owner' => 'applicant', 'category' => 'client_documents', 'is_required' => true, 'document_required' => true, 'status' => 'draft', 'is_active' => true]);
        ChecklistTemplate::create(['title' => 'Inactive item, not shown', 'owner' => 'applicant', 'category' => 'client_documents', 'is_required' => true, 'document_required' => true, 'status' => 'published', 'is_active' => false]);
        ChecklistTemplate::create(['title' => 'Invitation letter', 'owner' => 'inviter', 'category' => 'inviter', 'is_required' => true, 'document_required' => true, 'status' => 'published', 'is_active' => true]);
        ChecklistTemplate::create(['title' => 'Cover letter prepared', 'owner' => 'internal', 'category' => 'application_processing', 'is_required' => false, 'document_required' => false, 'status' => 'published', 'is_active' => true]);

        $user = $this->staff('application-unit.view');
        $client = $this->client();

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/application-unit/checklist-defaults");

        $response->assertOk();
        $response->assertJsonPath('data.applicant.0.title', 'Passport copy');
        $response->assertJsonPath('data.applicant.0.owner', 'applicant');
        $response->assertJsonPath('data.applicant.0.source', 'library');
        $response->assertJsonCount(1, 'data.applicant');
        $response->assertJsonPath('data.inviter.0.title', 'Invitation letter');
        $response->assertJsonPath('data.internal.0.title', 'Cover letter prepared');
        $response->assertJsonPath('data.internal.0.required', false);
    }

    public function test_services_own_checklist_templates_take_precedence_per_owner(): void
    {
        $serviceOnly = ChecklistTemplate::create(['title' => 'Service-specific passport rule', 'owner' => 'applicant', 'category' => 'client_documents', 'is_required' => true, 'document_required' => true, 'status' => 'published', 'is_active' => true]);
        ChecklistTemplate::create(['title' => 'Unrelated global applicant item', 'owner' => 'applicant', 'category' => 'client_documents', 'is_required' => true, 'document_required' => true, 'status' => 'published', 'is_active' => true]);
        $globalInviter = ChecklistTemplate::create(['title' => 'Global inviter item', 'owner' => 'inviter', 'category' => 'inviter', 'is_required' => true, 'document_required' => true, 'status' => 'published', 'is_active' => true]);

        $service = Service::create(['name' => 'UK Visit Visa', 'category' => 'visit_visa', 'is_active' => true]);
        // This service only curates its own applicant list; it has nothing
        // attached for inviter, so inviter should still fall back to the
        // global library.
        $service->checklistTemplates()->sync([$serviceOnly->id => ['is_required' => true, 'order' => 0]]);

        $user = $this->staff('application-unit.view');
        $client = $this->client(['service_category' => 'visit_visa']);

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/application-unit/checklist-defaults");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.applicant');
        $response->assertJsonPath('data.applicant.0.title', $serviceOnly->title);
        $response->assertJsonPath('data.inviter.0.title', $globalInviter->title);
    }

    public function test_falls_back_to_hardcoded_defaults_when_the_library_is_completely_empty(): void
    {
        $user = $this->staff('application-unit.view');
        $client = $this->client();

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/application-unit/checklist-defaults");

        $response->assertOk();
        $response->assertJsonPath('data.applicant.0.title', 'Passport (Current)');
        $response->assertJsonPath('data.inviter.0.title', 'Proof Of Legal Status');
        $response->assertJsonPath('data.internal.0.title', 'Application Form Filled');
    }

    public function test_checklist_defaults_requires_application_unit_view_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $client = $this->client();

        $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/application-unit/checklist-defaults")->assertForbidden();
    }
}
