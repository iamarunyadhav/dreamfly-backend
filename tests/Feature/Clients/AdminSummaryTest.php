<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientAdminSummary;
use Modules\Communications\Models\Message;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;
use ZipArchive;

class AdminSummaryTest extends TestCase
{
    private function userWith(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(array $overrides = []): Client
    {
        return Client::create([
            'reference_no' => 'DF-99-2026',
            'full_name' => 'Arunpragash Alwar',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 225000,
            'paid_amount' => 50000,
            'current_stage' => 'admin_summary',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_user_without_client_view_permission_cannot_view_admin_summary(): void
    {
        $response = $this->actingAs(User::factory()->create(['status' => 'active']))
            ->getJson('/api/v1/clients/'.$this->client()->id.'/admin-summary');

        $response->assertStatus(403);
    }

    public function test_admin_summary_draft_can_be_saved(): void
    {
        $user = $this->userWith('clients.edit');
        $client = $this->client();
        $supervisor = User::factory()->create(['status' => 'active']);
        $staff = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/admin-summary", [
            'summary' => 'Client has paid an advance and submitted initial passport copy.',
            'internal_notes' => 'Check inviter documents before Application Unit starts.',
            'form_data' => [
                'inviter_name' => 'Mr Smith',
                'asset_certificate_required' => 'yes',
            ],
            'supervisor_id' => $supervisor->id,
            'application_staff_id' => $staff->id,
            'deadline_at' => '2026-07-25 10:30:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.client_id', $client->id);

        $this->assertDatabaseHas('client_admin_summaries', [
            'client_id' => $client->id,
            'status' => 'draft',
            'supervisor_id' => $supervisor->id,
            'application_staff_id' => $staff->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $this->assertSame('Mr Smith', ClientAdminSummary::first()->form_data['inviter_name']);
    }

    public function test_admin_summary_complete_requires_mandatory_fields(): void
    {
        $response = $this->actingAs($this->userWith('clients.edit'))
            ->postJson('/api/v1/clients/'.$this->client()->id.'/admin-summary/complete', [
                'summary' => 'Too short',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supervisor_id', 'application_staff_id', 'deadline_at']);
        $this->assertDatabaseCount('client_admin_summaries', 0);
    }

    public function test_admin_summary_complete_moves_client_to_application_unit(): void
    {
        $user = $this->userWith('clients.edit');
        $client = $this->client();
        $supervisor = User::factory()->create(['status' => 'active']);
        $staff = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/admin-summary/complete", [
            'summary' => 'Client documents and agreement have been checked. Application Unit can start.',
            'internal_notes' => 'Prioritize appointment booking.',
            'client_share_notes' => 'Please keep your phone available for document requests.',
            'form_data' => [
                'purpose_of_visit' => 'Family visit',
                'relationship' => 'Brother',
            ],
            'supervisor_id' => $supervisor->id,
            'application_staff_id' => $staff->id,
            'deadline_at' => '2026-07-25 10:30:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.admin_summary.status', 'completed');
        $response->assertJsonPath('data.client.current_stage', 'application_unit');

        $client->refresh();
        $this->assertSame('application_unit', $client->current_stage);
        $this->assertSame($supervisor->id, $client->assigned_supervisor_id);

        $summary = ClientAdminSummary::first();
        $this->assertNotNull($summary);
        $this->assertSame('completed', $summary->status);
        $this->assertSame($user->id, $summary->completed_by);
        $this->assertNotNull($summary->completed_at);

        // Stage advancement must go through the case-step engine (not just a
        // client.current_stage forceFill), so the gated Workflow tab can trust
        // case_steps as the single source of truth.
        $this->assertSame('completed', CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->value('status'));
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'application_unit')->value('status'));

        // The chosen Application Unit staff member actually lands on the new
        // current step (previously this was stored on client_admin_summaries
        // only and never propagated anywhere operational) and gets notified.
        $this->assertSame($staff->id, CaseStep::where('client_id', $client->id)->where('key', 'application_unit')->value('assigned_user_id'));
        $response->assertJsonPath('data.handoff.assignee.user_id', $staff->id);
        $this->assertDatabaseHas('messages', ['client_id' => $client->id, 'recipient' => $staff->email]);
    }

    public function test_admin_summary_complete_notifies_assignee_and_every_admin(): void
    {
        $admin = User::factory()->create(['status' => 'active', 'email' => 'admin@dreamfly.test', 'phone' => '94770000009']);
        $admin->assignRole('Admin');
        $user = $this->userWith('clients.edit');
        $client = $this->client();
        $supervisor = User::factory()->create(['status' => 'active']);
        $staff = User::factory()->create(['status' => 'active', 'name' => 'Priya', 'email' => 'priya@dreamfly.test', 'phone' => '94770000010']);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/admin-summary/complete", [
            'summary' => 'Client documents and agreement have been checked. Application Unit can start.',
            'supervisor_id' => $supervisor->id,
            'application_staff_id' => $staff->id,
            'deadline_at' => '2026-07-25 10:30:00',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.handoff.assignee.channels_sent', ['email', 'whatsapp']);
        $response->assertJsonPath('data.handoff.admins_notified', 1);
        $this->assertDatabaseHas('messages', ['recipient' => 'priya@dreamfly.test', 'channel' => 'email']);
        $this->assertDatabaseHas('messages', ['recipient' => '94770000010', 'channel' => 'whatsapp']);
        $this->assertDatabaseHas('messages', ['recipient' => 'admin@dreamfly.test', 'channel' => 'email']);
        $this->assertSame(1, \Modules\System\Models\Notification::where('user_id', $staff->id)->where('type', 'step_handoff_assignment')->count());
        $this->assertSame(1, \Modules\System\Models\Notification::where('user_id', $admin->id)->where('type', 'step_handoff_admin')->count());
    }

    public function test_admin_summary_cannot_complete_from_wrong_stage(): void
    {
        $supervisor = User::factory()->create(['status' => 'active']);
        $staff = User::factory()->create(['status' => 'active']);
        $client = $this->client(['current_stage' => 'documentation_unit']);

        $response = $this->actingAs($this->userWith('clients.edit'))
            ->postJson("/api/v1/clients/{$client->id}/admin-summary/complete", [
                'summary' => 'Client documents and agreement have been checked. Application Unit can start.',
                'supervisor_id' => $supervisor->id,
                'application_staff_id' => $staff->id,
                'deadline_at' => '2026-07-25 10:30:00',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('current_stage');
        $this->assertDatabaseCount('client_admin_summaries', 0);
    }

    public function test_admin_summary_docx_generation_saves_file_in_admin_summary_folder(): void
    {
        $user = $this->userWith(['clients.edit', 'files.view']);
        $client = $this->client();

        ClientAdminSummary::create([
            'client_id' => $client->id,
            'summary' => 'Client documents and agreement have been checked.',
            'form_data' => [
                'client_name' => 'Arunpragash Alwar',
                'travel_country' => 'United Kingdom',
                'purpose_of_visit' => 'Visit family',
                'application_type' => 'Visit Visa',
                'inviter_name' => 'Mr Smith',
                'relationship' => 'Friend',
                'asset_certificate_required' => 'yes',
                'last_6_month_bank_statement_provided' => 'no',
            ],
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/admin-summary/generate-docx");

        $response->assertStatus(201);
        $response->assertJsonPath('data.file.extension', 'docx');

        $file = File::first();
        $this->assertNotNull($file);
        $this->assertSame($client->id, $file->client_id);
        $this->assertSame('docx', $file->extension);
        $this->assertFileExists(storage_path('app/private/'.$file->path));

        $folder = Folder::find($file->folder_id);
        $this->assertNotNull($folder);
        $this->assertSame('Admin Summary', $folder->name);

        $this->assertSame($file->id, ClientAdminSummary::first()->generated_file_id);

        $docText = $this->docxText(storage_path('app/private/'.$file->path));
        $this->assertStringContainsString('Arunpragash Alwar', $docText);
        $this->assertStringContainsString('United Kingdom', $docText);
        $this->assertStringContainsString('Visit family', $docText);
        $this->assertStringContainsString('Mr Smith', $docText);
    }

    public function test_admin_summary_docx_generation_honours_chosen_folder_and_file_name(): void
    {
        $user = $this->userWith(['clients.edit', 'files.view']);
        $client = $this->client();

        ClientAdminSummary::create([
            'client_id' => $client->id,
            'summary' => 'Client documents and agreement have been checked.',
            'form_data' => ['client_name' => 'Arunpragash Alwar'],
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $otherFolder = Folder::create(['name' => 'Custom Save Folder', 'slug' => 'custom-save-folder', 'scope' => 'global', 'is_active' => true]);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/admin-summary/generate-docx", [
            'folder_id' => $otherFolder->id,
            'file_name' => 'Custom Admin Summary Name',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.file.name', 'Custom Admin Summary Name.docx');

        $file = File::first();
        $this->assertSame($otherFolder->id, $file->folder_id);
        $this->assertSame('Custom Admin Summary Name.docx', $file->original_name);
    }

    public function test_generated_admin_summary_can_be_previewed_converted_to_pdf_and_shared(): void
    {
        $user = $this->userWith(['clients.edit', 'files.view', 'files.download', 'communications.send']);
        $client = $this->client();

        ClientAdminSummary::create([
            'client_id' => $client->id,
            'summary' => 'Client documents and agreement have been checked.',
            'form_data' => [
                'client_name' => 'Arunpragash Alwar',
                'travel_country' => 'United Kingdom',
                'purpose_of_visit' => 'Visit family',
            ],
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $generated = $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/admin-summary/generate-docx")
            ->json('data.file');

        $preview = $this->actingAs($user)->get('/api/v1/files/'.$generated['id'].'/preview');
        $preview->assertOk();
        $preview->assertSee('Arunpragash Alwar', false);
        $preview->assertSee('United Kingdom', false);

        $pdfResponse = $this->actingAs($user)->postJson('/api/v1/files/'.$generated['id'].'/generate-pdf');
        $pdfResponse->assertStatus(201);
        $pdfResponse->assertJsonPath('data.extension', 'pdf');
        $this->assertFileExists(storage_path('app/private/'.File::where('extension', 'pdf')->first()->path));

        $shareResponse = $this->actingAs($user)->postJson('/api/v1/files/'.$generated['id'].'/share', [
            'channel' => 'whatsapp',
            'recipient' => '94761234567',
            'body' => 'Please review your Admin Summary document.',
        ]);

        $shareResponse->assertStatus(201);
        $shareResponse->assertJsonPath('data.status', 'sent');
        $this->assertSame('94761234567', Message::first()->recipient);
        $this->assertStringContainsString('/api/v1/files/'.$generated['id'].'/signed-download', Message::first()->body);
        $this->assertStringContainsString('signature=', Message::first()->body);
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
