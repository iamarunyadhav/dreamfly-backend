<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientResponsibilityNotice;
use Modules\Communications\Models\Message;
use Modules\Files\Models\File;
use Tests\TestCase;

class ResponsibilityNoticeTest extends TestCase
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
            'reference_no' => 'DF-320-2026',
            'full_name' => 'Nirosha P',
            'passport_no' => 'N3200001',
            'nic' => '911234567V',
            'phone' => '0771230320',
            'email' => 'nirosha@example.com',
            'country' => 'United Kingdom',
            'visa_type' => 'Family Visit',
            'service_category' => 'visit_visa',
            'current_stage' => 'responsibility_notice',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_show_returns_null_notice_and_the_documents_it_will_list(): void
    {
        $user = $this->staff('clients.view');
        $client = $this->client();

        CaseChecklistItem::create([
            'client_id' => $client->id,
            'owner' => 'applicant',
            'source_index' => 0,
            'title' => 'Passport (Current)',
            'status' => 'verified',
            'is_required' => true,
            'document_required' => true,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/responsibility-notice");

        $response->assertOk();
        $response->assertJsonPath('data.notice', null);
        $response->assertJsonPath('data.documents.0.title', 'Passport (Current)');
        $response->assertJsonPath('data.documents.0.owner', 'Applicant');
        $response->assertJsonPath('data.documents.0.status', 'Verified');
    }

    public function test_draft_saves_operator_content_without_generating(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $this->actingAs($user)
            ->putJson("/api/v1/clients/{$client->id}/responsibility-notice", [
                'content' => 'Original NIC and birth certificate handed over on 25.07.2026.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.acknowledged', false);

        $notice = ClientResponsibilityNotice::where('client_id', $client->id)->first();
        $this->assertNotNull($notice);
        $this->assertNull($notice->generated_file_id);
        $this->assertSame('Original NIC and birth certificate handed over on 25.07.2026.', $notice->content);
    }

    public function test_generate_saves_a_pdf_into_the_client_folder(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/generate");

        $response->assertCreated();
        $response->assertJsonPath('data.notice.status', 'generated');

        $fileId = $response->json('data.file.id');
        $file = File::find($fileId);

        $this->assertNotNull($file);
        $this->assertSame($client->id, $file->client_id);
        $this->assertSame('pdf', $file->extension);
        $this->assertSame('Final Documents', $file->folder->name);
        $this->assertSame($fileId, $client->refresh()->responsibilityNotice->generated_file_id);
    }

    public function test_share_generates_when_needed_and_records_a_client_message(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit', 'communications.send']);
        $client = $this->client();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/share", [
                'channel' => 'whatsapp',
                'recipient' => '+94771230320',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.notice.status', 'shared');
        $this->assertNotNull($response->json('data.notice.shared_at'));
        // Sharing auto-generates the PDF when one does not exist yet.
        $this->assertNotNull($response->json('data.notice.generated_file_id'));

        $message = Message::where('client_id', $client->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('responsibility_notice', $message->workflow_step);
        $this->assertStringContainsString('Responsibility Notice:', $message->body);
    }

    public function test_acknowledgement_requires_a_generated_notice_then_records_who_and_how(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        // Nothing generated yet - rejected.
        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/acknowledge", [
                'acknowledgement_method' => 'whatsapp_reply',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notice');

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/responsibility-notice/generate")->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/acknowledge", [
                'acknowledgement_method' => 'whatsapp_reply',
                'acknowledgement_note' => 'Client replied "accepted" on WhatsApp.',
            ])
            ->assertOk()
            ->assertJsonPath('data.acknowledged', true)
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.acknowledgement_method', 'whatsapp_reply')
            ->assertJsonPath('data.acknowledged_by', $user->id);
    }

    public function test_revoking_an_acknowledgement_requires_a_reason_and_reopens_the_gate(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/responsibility-notice/generate")->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/responsibility-notice/acknowledge", [
            'acknowledgement_method' => 'verbal',
        ])->assertOk();

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/revoke-acknowledgement", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/revoke-acknowledgement", [
                'reason' => 'Recorded against the wrong case.',
            ])
            ->assertOk()
            ->assertJsonPath('data.acknowledged', false)
            ->assertJsonPath('data.status', 'generated');
    }

    public function test_editing_permission_is_required_to_generate(): void
    {
        $viewer = $this->staff('clients.view');
        $client = $this->client();

        $this->actingAs($viewer)
            ->postJson("/api/v1/clients/{$client->id}/responsibility-notice/generate")
            ->assertStatus(403);
    }
}
