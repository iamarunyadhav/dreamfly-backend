<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientAdminSummary;
use Modules\Clients\Models\ClientApplicationUnit;
use Modules\Communications\Models\Message;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Payments\Models\Payment;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    public function test_profile_returns_documents_payments_workflow_communications_notes_and_audit(): void
    {
        Storage::disk('local')->put('files/client/passport.pdf', 'passport');

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('clients.view', 'clients.edit');

        $client = Client::create([
            'reference_no' => 'DF-500-2026',
            'full_name' => 'Profile Client',
            'passport_no' => 'N500',
            'phone' => '+94760000000',
            'email' => 'profile@example.com',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 200000,
            'paid_amount' => 50000,
            'current_stage' => 'documentation_unit',
            'created_by' => $user->id,
        ]);

        ClientAdminSummary::create([
            'client_id' => $client->id,
            'summary' => 'Admin completed',
            'status' => 'completed',
            'completed_at' => now(),
            'created_by' => $user->id,
        ]);

        ClientApplicationUnit::create([
            'client_id' => $client->id,
            'notes' => 'Application completed',
            'status' => 'completed',
            'completed_at' => now(),
            'created_by' => $user->id,
        ]);

        $folder = Folder::create([
            'name' => 'Client Folder',
            'slug' => 'client-folder',
            'scope' => 'client',
            'client_id' => $client->id,
            'is_active' => true,
        ]);

        File::create([
            'folder_id' => $folder->id,
            'client_id' => $client->id,
            'name' => 'passport.pdf',
            'original_name' => 'passport.pdf',
            'disk' => 'local',
            'path' => 'files/client/passport.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
            'uploaded_by' => $user->id,
            'verified' => true,
        ]);

        Payment::create([
            'client_id' => $client->id,
            'amount' => 50000,
            'method' => 'cash',
            'reference' => 'RCPT-1',
            'paid_at' => now()->toDateString(),
            'recorded_by' => $user->id,
        ]);

        Message::create([
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'recipient' => $client->phone,
            'body' => 'Client update',
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/notes", [
            'body' => 'Internal follow up',
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/profile");

        $response->assertOk();
        $response->assertJsonPath('data.client.reference_no', 'DF-500-2026');
        $this->assertCount(1, $response->json('data.documents'));
        $this->assertCount(1, $response->json('data.payments'));
        $this->assertCount(8, $response->json('data.workflow'));
        $this->assertNotEmpty($response->json('data.communications'));
        $this->assertCount(1, $response->json('data.notes'));
        $this->assertNotEmpty($response->json('data.audit_logs'));
    }

    public function test_client_notes_require_edit_permission(): void
    {
        $viewer = User::factory()->create(['status' => 'active']);
        $viewer->givePermissionTo('clients.view');
        $client = Client::create([
            'reference_no' => 'DF-501-2026',
            'full_name' => 'Readonly Client',
            'service_category' => 'visit_visa',
        ]);

        $this->actingAs($viewer)->postJson("/api/v1/clients/{$client->id}/notes", [
            'body' => 'Should not save',
        ])->assertForbidden();

        $this->assertDatabaseMissing('client_notes', [
            'client_id' => $client->id,
            'body' => 'Should not save',
        ]);
    }

    public function test_client_profile_photo_upload_sets_explicit_profile_photo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['clients.view', 'clients.edit']);
        $client = Client::create([
            'reference_no' => 'DF-502-2026',
            'full_name' => 'Photo Client',
            'country' => 'Canada',
            'service_category' => 'visit_visa',
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/profile-photo", [
            'file' => UploadedFile::fake()->image('photo.png')->size(100),
        ]);

        $response->assertCreated();
        $fileId = $response->json('data.id');
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'profile_photo_file_id' => $fileId,
        ]);
        $this->assertDatabaseHas('files', [
            'id' => $fileId,
            'client_id' => $client->id,
            'mime_type' => 'image/png',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/clients/{$client->id}/profile")
            ->assertOk()
            ->assertJsonPath('data.client.profile_photo.id', $fileId);
    }
}
