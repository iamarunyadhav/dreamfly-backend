<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientApplicationUnit;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;
use ZipArchive;

class ApplicationUnitTest extends TestCase
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
            'reference_no' => 'DF-100-2026',
            'full_name' => 'Kavitha S',
            'passport_no' => 'N7654321',
            'nic' => '998877665V',
            'phone' => '0771234567',
            'email' => 'kavitha@example.com',
            'country' => 'United Kingdom',
            'visa_type' => 'Family Visit',
            'service_category' => 'visit_visa',
            'current_stage' => 'application_unit',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_application_unit_draft_can_save_full_form_and_checklists(): void
    {
        $user = $this->staff('application-unit.update');
        $client = $this->client();

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/application-unit", [
            'form_data' => [
                'destination_country' => 'United Kingdom',
                'full_name_as_per_passport' => 'Kavitha S',
                'passport_number' => 'N7654321',
            ],
            'applicant_checklist' => [
                ['title' => 'Passport (Current)', 'status' => 'pending', 'required' => true],
            ],
            'inviter_checklist' => [
                ['title' => 'Proof Of Legal Status', 'status' => 'missing', 'required' => true],
            ],
            'notes' => 'Need applicant bank statement.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'draft');
        $this->assertSame('Kavitha S', ClientApplicationUnit::first()->form_data['full_name_as_per_passport']);
        $this->assertSame('Passport (Current)', ClientApplicationUnit::first()->applicant_checklist[0]['title']);
        $this->assertDatabaseHas('case_checklist_items', [
            'client_id' => $client->id,
            'owner' => 'applicant',
            'source_index' => 0,
            'title' => 'Passport (Current)',
            'status' => 'pending',
        ]);
    }

    public function test_internal_checklist_saves_and_syncs_into_the_runtime(): void
    {
        $user = $this->staff('application-unit.update');
        $client = $this->client();

        $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/application-unit", [
            'internal_checklist' => [
                ['title' => 'Cover Letter Prepared', 'status' => 'pending', 'required' => false],
                ['title' => 'Appointment Booked', 'status' => 'completed', 'required' => true],
            ],
        ])->assertOk()->assertJsonPath('data.internal_checklist.0.title', 'Cover Letter Prepared');

        $this->assertSame(
            'Appointment Booked',
            ClientApplicationUnit::first()->internal_checklist[1]['title'],
        );

        // Internal rows share the runtime checklist table with applicant/inviter
        // rows, so a required one gates a checklist-gated step like any other.
        $this->assertDatabaseHas('case_checklist_items', [
            'client_id' => $client->id,
            'owner' => 'internal',
            'source_index' => 0,
            'title' => 'Cover Letter Prepared',
            'status' => 'pending',
            'is_required' => false,
        ]);
        $this->assertDatabaseHas('case_checklist_items', [
            'client_id' => $client->id,
            'owner' => 'internal',
            'source_index' => 1,
            'status' => 'completed',
            'is_required' => true,
        ]);
    }

    public function test_internal_checklist_row_accepts_a_document_upload(): void
    {
        $user = $this->staff(['application-unit.update', 'files.create']);
        $client = $this->client();

        $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/application-unit", [
            'internal_checklist' => [['title' => 'File Copy Archived', 'status' => 'missing', 'required' => false]],
        ])->assertOk();

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/application-unit/checklist-file", [
            'kind' => 'internal',
            'index' => 0,
            'file' => UploadedFile::fake()->create('archive-copy.pdf', 40, 'application/pdf'),
        ]);

        $response->assertCreated();
        $fileId = $response->json('data.file.id');
        $this->assertNotNull($fileId);
        $this->assertSame($fileId, ClientApplicationUnit::first()->internal_checklist[0]['linked_file_id']);

        // Internal uploads land in the office-side Application Unit folder, not
        // the client-facing Applicant/Inviter Documents folders.
        $folderId = File::find($fileId)->folder_id;
        $this->assertSame('Application Unit', Folder::find($folderId)->name);
    }

    public function test_application_unit_complete_requires_application_stage_and_required_identity_fields(): void
    {
        $client = $this->client(['current_stage' => 'admin_summary']);

        $response = $this->actingAs($this->staff('application-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/application-unit/complete", [
                'form_data' => ['full_name_as_per_passport' => 'Kavitha S', 'passport_number' => 'N7654321'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_stage');
    }

    public function test_application_unit_complete_moves_case_to_documentation_unit(): void
    {
        $client = $this->client();

        $response = $this->actingAs($this->staff('application-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/application-unit/complete", [
                'form_data' => ['full_name_as_per_passport' => 'Kavitha S', 'passport_number' => 'N7654321'],
                'applicant_checklist' => [['title' => 'Passport (Current)', 'status' => 'completed', 'required' => true]],
                'inviter_checklist' => [['title' => 'Proof Of Legal Status', 'status' => 'completed', 'required' => true]],
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.application_unit.status', 'completed');
        $response->assertJsonPath('data.client.current_stage', 'documentation_unit');

        // Stage advancement must go through the case-step engine (not just a
        // client.current_stage forceFill), so the gated Workflow tab can trust
        // case_steps as the single source of truth.
        $this->assertSame('completed', CaseStep::where('client_id', $client->id)->where('key', 'application_unit')->value('status'));
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->value('status'));
    }

    public function test_application_unit_complete_assigns_chosen_correction_unit_staff_and_notifies(): void
    {
        $client = $this->client();
        $nila = User::factory()->create(['status' => 'active', 'name' => 'Nila', 'email' => 'nila@dreamfly.test', 'phone' => '94770000011']);

        $response = $this->actingAs($this->staff('application-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/application-unit/complete", [
                'form_data' => ['full_name_as_per_passport' => 'Kavitha S', 'passport_number' => 'N7654321'],
                'applicant_checklist' => [['title' => 'Passport (Current)', 'status' => 'completed', 'required' => true]],
                'inviter_checklist' => [['title' => 'Proof Of Legal Status', 'status' => 'completed', 'required' => true]],
                'next_assigned_user_id' => $nila->id,
            ]);

        $response->assertOk();
        $this->assertSame($nila->id, CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->value('assigned_user_id'));
        $response->assertJsonPath('data.handoff.assignee.user_id', $nila->id);
        $this->assertDatabaseHas('messages', ['client_id' => $client->id, 'recipient' => 'nila@dreamfly.test']);
    }

    public function test_application_unit_complete_falls_back_to_supervisor_when_no_one_chosen(): void
    {
        $supervisor = User::factory()->create(['status' => 'active', 'email' => 'sup@dreamfly.test']);
        $client = $this->client(['assigned_supervisor_id' => $supervisor->id]);

        $response = $this->actingAs($this->staff('application-unit.complete'))
            ->postJson("/api/v1/clients/{$client->id}/application-unit/complete", [
                'form_data' => ['full_name_as_per_passport' => 'Kavitha S', 'passport_number' => 'N7654321'],
                'applicant_checklist' => [['title' => 'Passport (Current)', 'status' => 'completed', 'required' => true]],
                'inviter_checklist' => [['title' => 'Proof Of Legal Status', 'status' => 'completed', 'required' => true]],
            ]);

        $response->assertOk();
        $this->assertSame($supervisor->id, CaseStep::where('client_id', $client->id)->where('key', 'documentation_unit')->value('assigned_user_id'));
        $response->assertJsonPath('data.handoff.assignee.user_id', $supervisor->id);
    }

    public function test_application_unit_docx_generation_saves_file_and_binds_values(): void
    {
        $user = $this->staff('application-unit.generate');
        $client = $this->client();

        ClientApplicationUnit::create([
            'client_id' => $client->id,
            'form_data' => [
                'destination_country' => 'Canada',
                'type_of_application' => 'New',
                'departure_from_home_at' => '2026-08-01T09:30',
                'number_of_applicants' => '2',
                'applicant_bank_balance' => '350000',
                'full_name_as_per_passport' => 'Kavitha S',
                'passport_number' => 'N7654321',
                'passport_issue_date' => '2022-01-15',
                'passport_expiry_date' => '2032-01-14',
                'biometrics_before' => 'Yes',
                'monthly_salary' => '125000',
                'email_address' => 'kavitha@example.com',
            ],
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/application-unit/generate-docx");

        $response->assertStatus(201);
        $response->assertJsonPath('data.file.extension', 'docx');

        $file = File::first();
        $this->assertNotNull($file);
        $this->assertSame($client->id, $file->client_id);
        $this->assertFileExists(storage_path('app/private/'.$file->path));

        $folder = Folder::find($file->folder_id);
        $this->assertSame('Application Unit', $folder->name);
        $this->assertSame($file->id, ClientApplicationUnit::first()->generated_file_id);

        $docText = $this->docxText(storage_path('app/private/'.$file->path));
        $this->assertStringContainsString('Canada', $docText);
        $this->assertStringContainsString('2026-08-01T09:30', $docText);
        $this->assertStringContainsString('125000', $docText);
        $this->assertStringContainsString('Kavitha S', $docText);
        $this->assertStringContainsString('N7654321', $docText);
    }

    public function test_application_unit_docx_generation_honours_chosen_folder_and_file_name(): void
    {
        $user = $this->staff('application-unit.generate');
        $client = $this->client();

        ClientApplicationUnit::create([
            'client_id' => $client->id,
            'form_data' => ['full_name_as_per_passport' => 'Kavitha S'],
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $otherFolder = Folder::create(['name' => 'Custom Save Folder', 'slug' => 'custom-save-folder-au', 'scope' => 'global', 'is_active' => true]);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/application-unit/generate-docx", [
            'folder_id' => $otherFolder->id,
            'file_name' => 'Custom Application Data',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.file.name', 'Custom Application Data.docx');

        $file = File::first();
        $this->assertSame($otherFolder->id, $file->folder_id);
        $this->assertSame('Custom Application Data.docx', $file->original_name);
    }

    public function test_application_unit_checklist_file_upload_links_document_to_row(): void
    {
        $user = $this->staff('application-unit.update');
        $client = $this->client();

        ClientApplicationUnit::create([
            'client_id' => $client->id,
            'form_data' => ['full_name_as_per_passport' => 'Kavitha S'],
            'applicant_checklist' => [
                ['title' => 'Passport (Current)', 'status' => 'missing', 'required' => true, 'owner' => 'applicant'],
            ],
            'inviter_checklist' => [],
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/application-unit/checklist-file", [
            'kind' => 'applicant',
            'index' => 0,
            'file' => UploadedFile::fake()->create('passport.pdf', 120, 'application/pdf'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.application_unit.applicant_checklist.0.status', 'pending');
        $response->assertJsonPath('data.application_unit.applicant_checklist.0.linked_file_name', 'passport.pdf');
        $response->assertJsonPath('data.file.extension', 'pdf');

        $file = File::first();
        $this->assertNotNull($file);
        $this->assertSame($client->id, $file->client_id);
        $this->assertSame('passport.pdf', $file->original_name);

        $folder = Folder::find($file->folder_id);
        $this->assertSame('Applicant Documents', $folder->name);

        $row = ClientApplicationUnit::first()->applicant_checklist[0];
        $this->assertSame($file->id, $row['linked_file_id']);
        $this->assertStringContainsString("/api/v1/files/{$file->id}/download", $row['linked_file_url']);
        $this->assertDatabaseHas('case_checklist_items', [
            'client_id' => $client->id,
            'owner' => 'applicant',
            'source_index' => 0,
            'linked_file_id' => $file->id,
            'status' => 'pending',
        ]);
    }

    public function test_documentation_unit_can_verify_and_reject_runtime_checklist_items(): void
    {
        $user = $this->staff(['application-unit.update', 'application-unit.view', 'files.verify']);
        $client = $this->client(['current_stage' => 'documentation_unit']);

        ClientApplicationUnit::create([
            'client_id' => $client->id,
            'applicant_checklist' => [
                ['title' => 'Passport (Current)', 'status' => 'missing', 'required' => true, 'owner' => 'applicant'],
            ],
            'inviter_checklist' => [],
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $upload = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/application-unit/checklist-file", [
            'kind' => 'applicant',
            'index' => 0,
            'file' => UploadedFile::fake()->create('passport.pdf', 120, 'application/pdf'),
        ]);

        $itemId = CaseChecklistItem::first()->id;
        $verify = $this->actingAs($user)->patchJson("/api/v1/clients/{$client->id}/application-unit/checklist-items/{$itemId}/verify");
        $verify->assertOk();
        $verify->assertJsonPath('data.status', 'verified');

        $this->assertTrue(File::find($upload->json('data.file.id'))->verified);
        $this->assertSame('verified', ClientApplicationUnit::first()->applicant_checklist[0]['status']);
        $this->assertTrue(ClientApplicationUnit::first()->applicant_checklist[0]['linked_file_verified']);

        $reject = $this->actingAs($user)->patchJson("/api/v1/clients/{$client->id}/application-unit/checklist-items/{$itemId}/reject", [
            'reason' => 'Blurry scan',
        ]);

        $reject->assertOk();
        $reject->assertJsonPath('data.status', 'rejected');
        $this->assertSame('rejected', ClientApplicationUnit::first()->applicant_checklist[0]['status']);
        $this->assertSame('Blurry scan', ClientApplicationUnit::first()->applicant_checklist[0]['rejection_reason']);
    }

    /**
     * Guards a defect found while adding the checklist-upload button to the
     * Correction Unit panel: the list/upload routes were gated on
     * `application-unit.*` only, which "Correction Unit Staff" (nor the new
     * "Documentation Unit Staff") has ever held - the role could verify/reject
     * (files.verify) but never actually load or upload against the checklist
     * it owns.
     */
    public function test_correction_unit_and_documentation_unit_staff_roles_can_list_and_upload_the_runtime_checklist(): void
    {
        $client = $this->client(['current_stage' => 'documentation_unit']);
        ClientApplicationUnit::create([
            'client_id' => $client->id,
            'applicant_checklist' => [
                ['title' => 'Passport (Current)', 'status' => 'missing', 'required' => true, 'owner' => 'applicant'],
            ],
            'inviter_checklist' => [],
            'status' => 'completed',
        ]);

        foreach (['Correction Unit Staff', 'Documentation Unit Staff'] as $roleName) {
            $user = User::factory()->create(['status' => 'active']);
            $user->assignRole($roleName);

            $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/application-unit/checklist-items")->assertOk();

            $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/application-unit/checklist-file", [
                'kind' => 'applicant',
                'index' => 0,
                'file' => UploadedFile::fake()->create($roleName.'.pdf', 80, 'application/pdf'),
            ])->assertStatus(201);
        }
    }

    private function docxText(string $path): string
    {
        $zip = new ZipArchive();
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return strip_tags($xml);
    }
}
