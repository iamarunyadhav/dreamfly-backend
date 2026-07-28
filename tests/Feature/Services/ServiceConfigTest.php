<?php

namespace Tests\Feature\Services;

use App\Models\User;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Clients\Models\Client;
use Modules\Communications\Models\MessageTemplate;
use Modules\Forms\Models\Form;
use Modules\Services\Models\Service;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Models\WorkflowTemplate;
use Tests\TestCase;

class ServiceConfigTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function workflowTemplate(): WorkflowTemplate
    {
        $template = WorkflowTemplate::create(['name' => 'Visit Visa Flow', 'service_type' => 'visit_visa', 'is_active' => true]);
        $template->steps()->createMany([
            ['name' => 'Intake', 'key' => 'intake', 'order' => 0, 'owner_role' => 'Reception Staff', 'duration_days' => 1],
            ['name' => 'Submission', 'key' => 'submission', 'order' => 1, 'owner_role' => 'Documentation Unit Staff', 'duration_days' => 3, 'requires_checklist' => true],
        ]);

        return $template;
    }

    public function test_create_service_bundles_workflow_checklists_forms_and_templates(): void
    {
        $user = $this->user(['services.create']);
        $template = $this->workflowTemplate();
        $checklist = ChecklistTemplate::create(['title' => 'Passport copy', 'category' => 'applicant', 'is_required' => true, 'document_required' => true]);
        $form = Form::create(['name' => 'Visit application form', 'status' => 'published']);
        $message = MessageTemplate::create(['name' => 'Welcome', 'channel' => 'whatsapp', 'body' => 'Hi']);

        $response = $this->actingAs($user)->postJson('/api/v1/services', [
            'name' => 'UK Visit Visa',
            'category' => 'visit_visa',
            'description' => 'Standard visit visa package',
            'workflow_template_id' => $template->id,
            'is_active' => true,
            'checklist_templates' => [['id' => $checklist->id, 'is_required' => true, 'order' => 0]],
            'form_ids' => [$form->id],
            'message_templates' => [['id' => $message->id, 'purpose' => 'welcome']],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.workflow_template.id', $template->id);
        $response->assertJsonPath('data.checklist_templates.0.id', $checklist->id);
        $response->assertJsonPath('data.forms.0.id', $form->id);
        $response->assertJsonPath('data.message_templates.0.purpose', 'welcome');

        $service = Service::first();
        $this->assertSame(1, $service->checklistTemplates()->count());
        $this->assertSame(1, $service->forms()->count());
        $this->assertSame(1, $service->messageTemplates()->count());
    }

    public function test_update_resyncs_pivots(): void
    {
        $user = $this->user(['services.create', 'services.edit']);
        $checklistA = ChecklistTemplate::create(['title' => 'A', 'category' => 'applicant', 'is_required' => true, 'document_required' => true]);
        $checklistB = ChecklistTemplate::create(['title' => 'B', 'category' => 'applicant', 'is_required' => true, 'document_required' => true]);

        $service = Service::create(['name' => 'S', 'category' => 'other', 'is_active' => true, 'created_by' => $user->id]);
        $service->checklistTemplates()->sync([$checklistA->id => ['is_required' => true, 'order' => 0]]);

        $this->actingAs($user)->putJson("/api/v1/services/{$service->id}", [
            'checklist_templates' => [['id' => $checklistB->id, 'is_required' => false, 'order' => 0]],
        ])->assertOk();

        $service->refresh();
        $this->assertEqualsCanonicalizing([$checklistB->id], $service->checklistTemplates()->pluck('checklist_templates.id')->all());
    }

    public function test_service_workflow_drives_case_step_initialization(): void
    {
        $staff = $this->user(['clients.view', 'clients.edit']);
        $template = $this->workflowTemplate();

        Service::create([
            'name' => 'UK Visit Visa',
            'category' => 'visit_visa',
            'workflow_template_id' => $template->id,
            'is_active' => true,
            'created_by' => $staff->id,
        ]);

        $client = Client::create([
            'reference_no' => 'DF-610-2026',
            'full_name' => 'Service Client',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'paid_amount' => 0,
            'current_stage' => 'intake',
            'status' => 'active',
        ]);

        $response = $this->actingAs($staff)->postJson("/api/v1/clients/{$client->id}/case-steps/initialize", []);

        $response->assertCreated();
        $steps = collect($response->json('data.steps'));
        // Uses the service's 2-step template, not the default 8-stage journey.
        $this->assertSame(2, $steps->count());
        $this->assertEqualsCanonicalizing(['intake', 'submission'], $steps->pluck('key')->all());
        $this->assertTrue($steps->firstWhere('key', 'submission')['requires_checklist']);
    }

    public function test_index_requires_permission(): void
    {
        $user = $this->user(['clients.view']);

        $this->actingAs($user)->getJson('/api/v1/services')->assertForbidden();
    }
}
