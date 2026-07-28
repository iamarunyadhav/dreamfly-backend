<?php

namespace Tests\Feature\Agreements;

use App\Models\User;
use Modules\Agreements\Models\Agreement;
use Modules\Clients\Models\Client;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
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

    public function test_generate_requires_a_folder(): void
    {
        $user = $this->user(['agreements.generate']);
        $agreement = $this->agreement($user);

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('folder_id');
    }

    public function test_generate_requires_permission(): void
    {
        $user = $this->user(['agreements.view']);
        $agreement = $this->agreement($user);
        $folder = Folder::create(['name' => 'X', 'slug' => 'x-folder', 'is_active' => true, 'created_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/v1/agreements/{$agreement->id}/generate", ['folder_id' => $folder->id])
            ->assertForbidden();
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
