<?php

namespace Tests\Feature\CommonUsers;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Payments\Models\Payment;
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
        $lead = $this->lead();
        $file = $this->verifiedLeadFile($lead);

        $response = $this->actingAs($this->staffWithConversionPermissions())
            ->postJson("/api/v1/common-users/{$lead->id}/convert");

        $response->assertStatus(201);
        $response->assertJsonPath('data.full_name', 'Thangarasa Jesiyanthan');
        $response->assertJsonPath('data.reference_no', 'DF-1-2026');

        $client = Client::first();
        $this->assertNotNull($client);
        $this->assertSame($lead->id, $client->common_user_id);
        $this->assertSame(50000, $client->paid_amount);

        $this->assertDatabaseHas('common_users', ['id' => $lead->id, 'status' => 'converted']);
        $this->assertDatabaseHas('payments', [
            'client_id' => $client->id,
            'amount' => 50000,
            'method' => 'advance',
        ]);
        $this->assertSame(1, Payment::count());

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
            'Admin Summary',
            'Applicant Documents',
            'Inviter Documents',
            'Application Unit',
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
    }
}
