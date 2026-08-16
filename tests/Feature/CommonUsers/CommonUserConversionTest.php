<?php

namespace Tests\Feature\CommonUsers;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Agreements\Models\Agreement;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use Modules\Payments\Models\AdditionalCharge;
use Modules\Payments\Models\Payment;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;

class CommonUserConversionTest extends TestCase
{
    private function staffWithConversionPermissions(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['common-users.edit', 'clients.convert']);

        return $user;
    }

    private function lead(array $overrides = []): CommonUser
    {
        return CommonUser::create([
            'full_name' => 'Thangarasa Jesiyanthan',
            'phone' => '94762275432',
            'passport_no' => 'N1234567',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 225000,
            'paid_amount' => 50000,
            'status' => 'partially_paid',
            ...$overrides,
        ]);
    }

    private function verifiedLeadFile(CommonUser $lead): File
    {
        return File::create([
            'common_user_id' => $lead->id,
            'name' => 'passport.pdf',
            'original_name' => 'passport.pdf',
            'disk' => 'local',
            'path' => 'documents/lead-'.$lead->id.'/passport.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 12000,
            'verified' => true,
        ]);
    }

    private function signedAgreementAndPayment(CommonUser $lead): void
    {
        $signed = File::create([
            'common_user_id' => $lead->id,
            'name' => 'signed-agreement.pdf',
            'original_name' => 'signed-agreement.pdf',
            'disk' => 'local',
            'path' => 'documents/lead-'.$lead->id.'/signed-agreement.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 12000,
            'verified' => true,
            'verified_at' => now(),
        ]);

        Agreement::create([
            'reference_no' => 'DF-AGR-1-2026',
            'common_user_id' => $lead->id,
            'client_name' => $lead->full_name,
            'visa_type' => $lead->visa_type,
            'country' => $lead->country,
            'total_fee' => $lead->agreement_amount,
            'advance_paid' => $lead->paid_amount,
            'status' => 'signed',
            'signed_file_id' => $signed->id,
            'signed_at' => now(),
        ]);

        $receipt = File::create([
            'common_user_id' => $lead->id,
            'name' => 'receipt.pdf',
            'original_name' => 'receipt.pdf',
            'disk' => 'local',
            'path' => 'documents/lead-'.$lead->id.'/receipt.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 12000,
            'verified' => true,
            'verified_at' => now(),
        ]);

        Payment::create([
            'common_user_id' => $lead->id,
            'amount' => $lead->paid_amount,
            'method' => 'bank',
            'reference' => 'ADV-'.$lead->id,
            'paid_at' => now()->toDateString(),
            'status' => 'verified',
            'receipt_file_id' => $receipt->id,
            'verified_at' => now(),
        ]);
    }

    public function test_user_without_required_permissions_cannot_convert_common_user(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $lead = $this->lead();
        $this->verifiedLeadFile($lead);

        $response = $this->actingAs($user)->postJson("/api/v1/common-users/{$lead->id}/convert");

        $response->assertStatus(403);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_common_user_requires_payment_before_conversion(): void
    {
        $lead = $this->lead(['paid_amount' => 0, 'status' => 'unpaid']);
        $this->verifiedLeadFile($lead);

        $response = $this->actingAs($this->staffWithConversionPermissions())
            ->postJson("/api/v1/common-users/{$lead->id}/convert");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('paid_amount');
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_common_user_requires_verified_document_before_conversion(): void
    {
        $lead = $this->lead();

        File::create([
            'common_user_id' => $lead->id,
            'name' => 'unverified.pdf',
            'original_name' => 'unverified.pdf',
            'disk' => 'local',
            'path' => 'documents/lead-'.$lead->id.'/unverified.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 12000,
            'verified' => false,
        ]);

        $response = $this->actingAs($this->staffWithConversionPermissions())
            ->postJson("/api/v1/common-users/{$lead->id}/convert");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('documents');
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_common_user_conversion_creates_client_payment_and_default_folder_tree(): void
    {
        $staff = $this->staffWithConversionPermissions();
        $lead = $this->lead();
        $file = $this->verifiedLeadFile($lead);
        $this->signedAgreementAndPayment($lead);
        // Every lead gets its own folder tree at creation time in real usage
        // (CommonUsersController::store) - build one here so the conversion's
        // archiving step has something real to relocate.
        app(FolderService::class)->createLeadFolderTree($lead, $staff->id);

        $profilePhotoFolder = app(FolderService::class)->leadSubfolder($lead, 'Profile Photo', $staff->id);
        $profilePhoto = File::create([
            'folder_id' => $profilePhotoFolder->id,
            'common_user_id' => $lead->id,
            'name' => 'profile.jpg',
            'original_name' => 'profile.jpg',
            'disk' => 'local',
            'path' => 'documents/lead-'.$lead->id.'/profile.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 12000,
        ]);
        $lead->forceFill(['profile_photo_file_id' => $profilePhoto->id])->save();

        $response = $this->actingAs($staff)
            ->postJson("/api/v1/common-users/{$lead->id}/convert");

        $response->assertStatus(201);
        $response->assertJsonPath('data.full_name', 'Thangarasa Jesiyanthan');
        $response->assertJsonPath('data.reference_no', 'DF-1-2026');

        $client = Client::first();
        $this->assertNotNull($client);
        $this->assertSame($lead->id, $client->common_user_id);
        $this->assertSame(50000, $client->paid_amount);
        $this->assertSame($profilePhoto->id, $client->profile_photo_file_id);

        $this->assertDatabaseHas('common_users', ['id' => $lead->id, 'status' => 'converted']);
        $this->assertDatabaseHas('payments', [
            'client_id' => $client->id,
            'common_user_id' => null,
            'amount' => 50000,
            'method' => 'bank',
            'status' => 'verified',
        ]);
        $this->assertSame(1, Payment::count());
        $this->assertSame($client->id, Payment::first()->client_id);
        $this->assertNull(Payment::first()->common_user_id);

        $root = Folder::where('name', 'Clients')->whereNull('parent_id')->first();
        $this->assertNotNull($root);

        $countryFolder = Folder::where('parent_id', $root->id)->where('name', 'United Kingdom')->first();
        $this->assertNotNull($countryFolder);

        $clientFolder = Folder::where('parent_id', $countryFolder->id)
            ->where('name', 'like', 'DF-1-2026%Thangarasa Jesiyanthan')
            ->first();
        $this->assertNotNull($clientFolder);

        $expectedSubfolders = [
            'Agreements',
            'Payments',
            'Profile Photo',
            'Admin Summary',
            'Applicant Documents',
            'Inviter Documents',
            'Application Unit',
            'Correction Unit',
            'Documentation Unit',
            'Invoices',
            'Final Documents',
        ];

        foreach ($expectedSubfolders as $subfolder) {
            $this->assertDatabaseHas('folders', [
                'parent_id' => $clientFolder->id,
                'name' => $subfolder,
            ]);
        }

        $applicantDocuments = Folder::where('parent_id', $clientFolder->id)
            ->where('name', 'Applicant Documents')
            ->first();

        $file->refresh();
        $this->assertSame($client->id, $file->client_id);
        $this->assertSame($applicantDocuments->id, $file->folder_id);

        $clientProfilePhotoFolder = Folder::where('parent_id', $clientFolder->id)
            ->where('name', 'Profile Photo')
            ->first();
        $this->assertNotNull($clientProfilePhotoFolder);
        $profilePhoto->refresh();
        $this->assertSame($client->id, $profilePhoto->client_id);
        $this->assertNull($profilePhoto->common_user_id);
        $this->assertSame($clientProfilePhotoFolder->id, $profilePhoto->folder_id);

        // Converting a lead is one of the two real client-creation paths - the
        // gated Workflow tab depends on every client having its case_steps
        // seeded from the moment it exists, never left to a manual step.
        $this->assertSame(11, CaseStep::where('client_id', $client->id)->count());
        $this->assertSame('in_progress', CaseStep::where('client_id', $client->id)->where('key', 'admin_summary')->value('status'));

        // The lead's own (now-empty) folder tree moves into Moved > Common
        // Users > country rather than sitting in the active lead tree or in
        // the deleted-user archive.
        $movedCountry = Folder::where('name', 'United Kingdom')
            ->whereHas('parent', fn ($q) => $q
                ->where('name', 'Common Users')
                ->whereHas('parent', fn ($parent) => $parent->where('name', 'Moved')->whereNull('parent_id')))
            ->first();
        $this->assertNotNull($movedCountry);
        $this->assertDatabaseHas('folders', [
            'common_user_id' => $lead->id,
            'parent_id' => $movedCountry->id,
        ]);
    }

    public function test_common_user_conversion_carries_additional_charges_to_the_client(): void
    {
        $staff = $this->staffWithConversionPermissions();
        $lead = $this->lead();
        $this->verifiedLeadFile($lead);
        $this->signedAgreementAndPayment($lead);
        app(FolderService::class)->createLeadFolderTree($lead, $staff->id);

        $charge = AdditionalCharge::create([
            'common_user_id' => $lead->id,
            'description' => 'Police report document',
            'amount' => 5000,
            'created_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)->postJson("/api/v1/common-users/{$lead->id}/convert");
        $response->assertStatus(201);

        $client = Client::first();
        $this->assertNotNull($client);

        $charge->refresh();
        $this->assertSame($client->id, $charge->client_id);
        $this->assertNull($charge->common_user_id);

        $this->assertSame(1, AdditionalCharge::count());
        $this->assertSame(5000, (int) $client->additional_charges_total);
        // Balance = agreement_amount (225000) + additional_charges_total (5000) - paid_amount (50000).
        $this->assertSame(180000, (int) $client->balance);
    }

    public function test_deleted_common_user_folder_moves_to_archive_and_restores_to_active_tree(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo(['common-users.delete', 'common-users.edit']);
        $lead = $this->lead(['country' => 'Sweden']);
        app(FolderService::class)->createLeadFolderTree($lead, $staff->id);

        $this->actingAs($staff)->deleteJson("/api/v1/common-users/{$lead->id}")->assertOk();

        $archiveCountry = Folder::where('name', 'Sweden')
            ->whereHas('parent', fn ($q) => $q
                ->where('name', 'Common Users')
                ->whereHas('parent', fn ($parent) => $parent->where('name', 'Archived')->whereNull('parent_id')))
            ->first();
        $this->assertNotNull($archiveCountry);
        $this->assertDatabaseHas('folders', ['common_user_id' => $lead->id, 'parent_id' => $archiveCountry->id]);
        $this->assertSoftDeleted('common_users', ['id' => $lead->id]);

        $this->actingAs($staff)->postJson("/api/v1/common-users/{$lead->id}/restore")->assertOk();

        $activeCountry = Folder::where('name', 'Sweden')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Common Users')->whereNull('parent_id'))
            ->first();
        $this->assertNotNull($activeCountry);
        $this->assertDatabaseHas('folders', ['common_user_id' => $lead->id, 'parent_id' => $activeCountry->id]);
        $this->assertDatabaseHas('common_users', ['id' => $lead->id, 'deleted_at' => null]);
    }

    public function test_common_user_profile_photo_upload_sets_explicit_profile_photo(): void
    {
        Storage::fake('local');

        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo(['common-users.edit']);
        $lead = $this->lead(['country' => 'Australia']);
        app(FolderService::class)->createLeadFolderTree($lead, $staff->id);

        $response = $this->actingAs($staff)->postJson("/api/v1/common-users/{$lead->id}/profile-photo", [
            'file' => UploadedFile::fake()->image('face.jpg')->size(100),
        ]);

        $response->assertCreated();
        $fileId = $response->json('data.id');
        $this->assertDatabaseHas('common_users', [
            'id' => $lead->id,
            'profile_photo_file_id' => $fileId,
        ]);
        $this->assertDatabaseHas('files', [
            'id' => $fileId,
            'common_user_id' => $lead->id,
            'mime_type' => 'image/jpeg',
        ]);
        $this->assertDatabaseHas('folders', [
            'common_user_id' => $lead->id,
            'name' => 'Profile Photo',
        ]);
    }

    public function test_deleted_client_folder_moves_to_archive_and_restores_to_active_tree(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo(['clients.delete', 'clients.edit']);
        $client = Client::create([
            'reference_no' => 'DF-99-2026',
            'full_name' => 'Deleted Client',
            'country' => 'Canada',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'status' => 'active',
        ]);
        app(FolderService::class)->createClientFolderTree($client, $staff->id);

        $this->actingAs($staff)->deleteJson("/api/v1/clients/{$client->id}")->assertOk();

        $archiveCountry = Folder::where('name', 'Canada')
            ->whereHas('parent', fn ($q) => $q
                ->where('name', 'Clients')
                ->whereHas('parent', fn ($parent) => $parent->where('name', 'Archived')->whereNull('parent_id')))
            ->first();
        $this->assertNotNull($archiveCountry);
        $this->assertDatabaseHas('folders', ['client_id' => $client->id, 'parent_id' => $archiveCountry->id]);
        $this->assertSoftDeleted('clients', ['id' => $client->id]);

        $this->actingAs($staff)->postJson("/api/v1/clients/{$client->id}/restore")->assertOk();

        $activeCountry = Folder::where('name', 'Canada')
            ->whereHas('parent', fn ($q) => $q->where('name', 'Clients')->whereNull('parent_id'))
            ->first();
        $this->assertNotNull($activeCountry);
        $this->assertDatabaseHas('folders', ['client_id' => $client->id, 'parent_id' => $activeCountry->id]);
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);
    }
}
