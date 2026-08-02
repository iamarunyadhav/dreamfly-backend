<?php

namespace Tests\Feature\Agreements;

use App\Models\User;
use Modules\Agreements\Models\Agreement;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AgreementFlowTest extends TestCase
{
    private function user(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-800-2026',
            'full_name' => 'Agreement Client',
            'passport_no' => 'P800',
            'phone' => '+94760000800',
            'email' => 'agr@example.com',
            'country' => 'United Kingdom',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 300000,
            'paid_amount' => 0,
        ]);
    }

    private function agreement(User $user, ?Client $client = null): Agreement
    {
        return Agreement::create([
            'reference_no' => 'DF-AGR-TEST-'.uniqid(),
            'client_id' => $client?->id,
            'client_name' => $client?->full_name ?? 'Manual Client',
            'client_passport_no' => $client?->passport_no,
            'visa_type' => 'Visit Visa',
            'country' => 'United Kingdom',
            'total_fee' => 300000,
            'advance_paid' => 50000,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    public function test_generate_saves_agreement_pdf_to_chosen_folder(): void
    {
        $user = $this->user(['agreements.generate']);
        $client = $this->client();
        $agreement = $this->agreement($user, $client);
        $folder = Folder::create(['name' => 'Agreements', 'slug' => 'agreements-dest', 'is_active' => true, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", [
            'folder_id' => $folder->id,
            'file_name' => 'Signed Copy',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.file.extension', 'pdf');
        $response->assertJsonPath('data.agreement.status', 'sent');

        $agreement->refresh();
        $this->assertNotNull($agreement->generated_file_id);

        $file = File::find($agreement->generated_file_id);
        $this->assertSame($folder->id, $file->folder_id);
        $this->assertSame($client->id, $file->client_id);
        $this->assertSame('Signed Copy.pdf', $file->original_name);
        $this->assertFileExists(storage_path('app/private/'.$file->path));
    }

    public function test_generate_without_folder_uses_default_destination(): void
    {
        $user = $this->user(['agreements.generate']);
        $agreement = $this->agreement($user);

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", [])
            ->assertCreated()
            ->assertJsonPath('data.file.extension', 'pdf');

        $agreement->refresh();
        $this->assertNotNull($agreement->generated_file_id);
        $this->assertSame('Agreements', Folder::find(File::find($agreement->generated_file_id)->folder_id)->name);
    }

    public function test_generate_requires_permission(): void
    {
        $user = $this->user(['agreements.view']);
        $agreement = $this->agreement($user);
        $folder = Folder::create(['name' => 'X', 'slug' => 'x-folder', 'is_active' => true, 'created_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", ['folder_id' => $folder->id])
            ->assertForbidden();
    }

    public function test_generate_cannot_duplicate_an_already_saved_agreement(): void
    {
        $user = $this->user(['agreements.generate']);
        $agreement = $this->agreement($user);
        $folder = Folder::create(['name' => 'Agreements', 'slug' => 'agreements-dest', 'is_active' => true, 'created_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", [
            'folder_id' => $folder->id,
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", [
            'folder_id' => $folder->id,
        ])->assertStatus(422)->assertJsonValidationErrors('agreement');

        $this->assertSame(1, File::count());
    }

    public function test_create_rejects_duplicate_person_identifier(): void
    {
        $user = $this->user(['agreements.create']);
        Agreement::create([
            'reference_no' => 'DF-AGR-DUP-1',
            'client_name' => 'Existing Client',
            'client_nic' => '912581624V',
            'client_passport_no' => 'EF123455111',
            'total_fee' => 100000,
            'advance_paid' => 0,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $payload = [
            'client_name' => 'Same Person',
            'client_nic' => '912581624V',
            'client_passport_no' => 'EF123455111',
            'total_fee' => 200000,
            'advance_paid' => 0,
        ];

        $this->actingAs($user)->postJson('/api/v1/agreements', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_nic', 'client_passport_no']);
    }

    public function test_create_rejects_second_agreement_for_same_common_user(): void
    {
        $user = $this->user(['agreements.create']);
        $lead = CommonUser::create([
            'full_name' => 'Lead Person',
            'phone' => '+94770000111',
            'nic' => '902222222V',
            'passport_no' => 'P222222',
            'country' => 'United Kingdom',
            'service_category' => 'visit_visa',
            'agreement_amount' => 200000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);

        Agreement::create([
            'reference_no' => 'DF-AGR-LEAD-1',
            'common_user_id' => $lead->id,
            'client_name' => $lead->full_name,
            'client_nic' => $lead->nic,
            'client_passport_no' => $lead->passport_no,
            'total_fee' => 200000,
            'advance_paid' => 0,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson('/api/v1/agreements', [
            'common_user_id' => $lead->id,
            'client_name' => $lead->full_name,
            'client_nic' => $lead->nic,
            'client_passport_no' => $lead->passport_no,
            'country' => $lead->country,
            'total_fee' => 200000,
            'advance_paid' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors(['common_user_id', 'client_nic', 'client_passport_no']);
    }

    public function test_signed_agreement_requires_generated_file_and_cannot_be_uploaded_twice(): void
    {
        $user = $this->user(['agreements.edit', 'agreements.generate']);
        $agreement = $this->agreement($user);
        $folder = Folder::create(['name' => 'Agreements', 'slug' => 'agreements-signed-test', 'is_active' => true, 'created_by' => $user->id]);

        $this->actingAs($user)
            ->post("/api/v1/agreements/{$agreement->id}/signed-file", [
                'file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('agreement');

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", [
            'folder_id' => $folder->id,
        ])->assertCreated();

        $this->actingAs($user)
            ->post("/api/v1/agreements/{$agreement->id}/signed-file", [
                'file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->post("/api/v1/agreements/{$agreement->id}/signed-file", [
                'file' => UploadedFile::fake()->create('signed-again.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_share_records_package_message_with_signed_link_and_bank_details(): void
    {
        $user = $this->user(['agreements.share']);
        $client = $this->client();
        $agreement = $this->agreement($user, $client);

        $response = $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/share", [
            'channel' => 'whatsapp',
            'recipient' => $client->phone,
            'welcome_message' => 'Welcome aboard.',
            'bank_instructions' => 'Account No: 123456789',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.client_id', $client->id);
        $body = $response->json('data.body');
        $this->assertStringContainsString('Welcome aboard.', $body);
        $this->assertStringContainsString('Account No: 123456789', $body);
        $this->assertStringContainsString('Unsigned Agreement:', $body);
        $this->assertStringContainsString('signature=', $body);

        // Share auto-generates the unsigned agreement so there is something to attach.
        $this->assertNotNull($agreement->refresh()->generated_file_id);
    }
}
