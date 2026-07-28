<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Clients\Models\AuthorityRequest;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\VisaSubmission;
use Modules\Files\Models\File;
use Tests\TestCase;

class PostLodgementTest extends TestCase
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
            'reference_no' => 'DF-410-2026',
            'full_name' => 'Ganesh R',
            'passport_no' => 'N4100001',
            'phone' => '0771230410',
            'country' => 'United Kingdom',
            'visa_type' => 'Family Visit',
            'service_category' => 'visit_visa',
            'current_stage' => 'submission',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    // --- Stage 16: submission / lodgement -------------------------------------

    public function test_submission_details_are_saved_and_returned(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $this->actingAs($user)
            ->putJson("/api/v1/clients/{$client->id}/visa-submission", [
                'submitted_at' => '2026-07-20',
                'lodgement_reference' => 'GWF123456789',
                'tracking_reference' => 'VFS-COL-77812',
                'submitted_to' => 'VFS Global Colombo',
                'submission_method' => 'vfs',
                'appointment_at' => '2026-07-20 09:30:00',
                'appointment_location' => 'VFS Colombo, Bambalapitiya',
                'biometrics_at' => '2026-07-20',
                'expected_decision_at' => '2026-08-17',
                'notes' => 'Priority service purchased.',
            ])
            ->assertOk()
            ->assertJsonPath('data.lodgement_reference', 'GWF123456789')
            ->assertJsonPath('data.submission_method', 'vfs')
            ->assertJsonPath('data.expected_decision_at', '2026-08-17');

        $this->actingAs($user)
            ->getJson("/api/v1/clients/{$client->id}/visa-submission")
            ->assertOk()
            ->assertJsonPath('data.tracking_reference', 'VFS-COL-77812');

        $this->assertSame(1, VisaSubmission::where('client_id', $client->id)->count());
    }

    public function test_saving_submission_twice_updates_the_same_row(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/visa-submission", [
            'lodgement_reference' => 'FIRST',
        ])->assertOk();

        $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/visa-submission", [
            'lodgement_reference' => 'SECOND',
        ])->assertOk()->assertJsonPath('data.lodgement_reference', 'SECOND');

        $this->assertSame(1, VisaSubmission::where('client_id', $client->id)->count());
    }

    public function test_submission_receipt_uploads_into_final_documents(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/visa-submission/receipt", [
            'file' => UploadedFile::fake()->create('lodgement-receipt.pdf', 40, 'application/pdf'),
        ]);

        $response->assertCreated();
        $fileId = $response->json('data.file.id');
        $file = File::find($fileId);

        $this->assertNotNull($file);
        $this->assertSame($client->id, $file->client_id);
        $this->assertSame('Final Documents', $file->folder->name);
        $this->assertSame($fileId, VisaSubmission::where('client_id', $client->id)->value('receipt_file_id'));
    }

    // --- Stage 17: authority requests -----------------------------------------

    public function test_authority_request_is_logged_with_a_deadline(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/authority-requests", [
                'authority' => 'UK Visas & Immigration',
                'request_type' => 'additional_documents',
                'title' => 'Six months of sponsor bank statements',
                'description' => 'Statements must be bank-stamped.',
                'received_at' => '2026-07-22',
                'due_at' => '2026-07-29',
                'assigned_user_id' => $user->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.authority', 'UK Visas & Immigration')
            ->assertJsonPath('data.assigned_user_name', $user->name);
    }

    public function test_due_date_cannot_precede_the_received_date(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/authority-requests", [
                'authority' => 'VFS',
                'request_type' => 'interview',
                'title' => 'Attend interview',
                'received_at' => '2026-07-22',
                'due_at' => '2026-07-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('due_at');
    }

    public function test_marking_responded_stamps_the_response_date_and_clears_overdue(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $authorityRequest = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'UKVI',
            'request_type' => 'additional_documents',
            'title' => 'Payslips',
            'received_at' => now()->subDays(10)->toDateString(),
            'due_at' => now()->subDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        // Past due and unresolved - flagged overdue by the model.
        $this->actingAs($user)
            ->getJson("/api/v1/clients/{$client->id}/authority-requests")
            ->assertOk()
            ->assertJsonPath('data.0.is_overdue', true);

        $this->actingAs($user)
            ->putJson("/api/v1/clients/{$client->id}/authority-requests/{$authorityRequest->id}", [
                'status' => 'responded',
                'response_notes' => 'Payslips emailed to the caseworker.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'responded')
            ->assertJsonPath('data.is_overdue', false);

        $this->assertNotNull($authorityRequest->refresh()->responded_at);
    }

    public function test_response_document_attaches_to_the_request(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();

        $authorityRequest = AuthorityRequest::create([
            'client_id' => $client->id,
            'authority' => 'UKVI',
            'request_type' => 'additional_documents',
            'title' => 'Bank statements',
            'received_at' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson(
            "/api/v1/clients/{$client->id}/authority-requests/{$authorityRequest->id}/response-file",
            ['file' => UploadedFile::fake()->create('statements.pdf', 30, 'application/pdf')],
        );

        $response->assertCreated();
        $this->assertNotNull($authorityRequest->refresh()->response_file_id);
        $this->assertSame('Final Documents', File::find($authorityRequest->response_file_id)->folder->name);
    }

    public function test_a_request_belonging_to_another_client_is_not_reachable(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client();
        $other = $this->client(['reference_no' => 'DF-411-2026', 'full_name' => 'Other Client']);

        $authorityRequest = AuthorityRequest::create([
            'client_id' => $other->id,
            'authority' => 'UKVI',
            'request_type' => 'other',
            'title' => 'Something',
            'received_at' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/clients/{$client->id}/authority-requests/{$authorityRequest->id}", ['status' => 'closed'])
            ->assertStatus(404);
    }

    // --- Stage 18: visa decision ----------------------------------------------

    public function test_approved_decision_is_recorded_with_who_and_when(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client(['current_stage' => 'visa_result']);

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/visa-decision", ['visa_outcome' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.visa_outcome', 'approved')
            ->assertJsonPath('data.appeal_status', 'none')
            ->assertJsonPath('data.outcome_recorded_by', $user->id);

        $this->assertNotNull($client->refresh()->outcome_recorded_at);
    }

    public function test_refusal_requires_a_reason_and_opens_the_appeal_path(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client(['current_stage' => 'visa_result']);

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/visa-decision", ['visa_outcome' => 'refused'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('refusal_reason');

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/visa-decision", [
                'visa_outcome' => 'refused',
                'refusal_reason' => 'Insufficient evidence of ties to home country (para 4.2a).',
                'appeal_due_at' => '2026-08-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.visa_outcome', 'refused')
            ->assertJsonPath('data.appeal_status', 'considering')
            ->assertJsonPath('data.appeal_due_at', '2026-08-20');
    }

    public function test_an_explicit_decision_date_is_not_overwritten_by_now(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client(['current_stage' => 'visa_result']);

        $this->actingAs($user)
            ->postJson("/api/v1/clients/{$client->id}/visa-decision", [
                'visa_outcome' => 'approved',
                'outcome_recorded_at' => '2026-07-18 10:00:00',
            ])
            ->assertOk();

        $this->assertSame('2026-07-18', $client->refresh()->outcome_recorded_at->toDateString());
    }

    public function test_switching_a_refusal_to_approved_clears_the_refusal_reason(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client(['current_stage' => 'visa_result']);

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/visa-decision", [
            'visa_outcome' => 'refused',
            'refusal_reason' => 'Recorded in error.',
        ])->assertOk();

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/visa-decision", [
            'visa_outcome' => 'approved',
        ])->assertOk()->assertJsonPath('data.refusal_reason', null);
    }

    public function test_decision_document_uploads_into_final_documents(): void
    {
        $user = $this->staff(['clients.view', 'clients.edit']);
        $client = $this->client(['current_stage' => 'visa_result']);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/visa-decision/document", [
            'file' => UploadedFile::fake()->create('grant-letter.pdf', 25, 'application/pdf'),
        ]);

        $response->assertCreated();
        $fileId = $response->json('data.file.id');

        $this->assertSame($fileId, $client->refresh()->decision_file_id);
        $this->assertSame('Final Documents', File::find($fileId)->folder->name);
    }

    public function test_recording_a_decision_requires_edit_permission(): void
    {
        $viewer = $this->staff('clients.view');
        $client = $this->client(['current_stage' => 'visa_result']);

        $this->actingAs($viewer)
            ->postJson("/api/v1/clients/{$client->id}/visa-decision", ['visa_outcome' => 'approved'])
            ->assertStatus(403);
    }
}
