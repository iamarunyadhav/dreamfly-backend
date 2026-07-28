<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientCaseClosure;
use Tests\TestCase;

class CaseClosureTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function client(): Client
    {
        return Client::create([
            'reference_no' => 'DF-960-2026',
            'full_name' => 'Closure Client',
            'service_category' => 'visit_visa',
            'current_stage' => 'visa_result',
            'status' => 'active',
        ]);
    }

    public function test_draft_can_save_the_handover_checklist(): void
    {
        $user = $this->staff('clients.edit');
        $client = $this->client();

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/case-closure", [
            'handover_checklist' => [
                ['title' => 'Original passport', 'returned' => false],
                ['title' => 'Original NIC', 'returned' => false],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.all_documents_returned', false);
        $this->assertCount(2, ClientCaseClosure::first()->handover_checklist);
    }

    public function test_archive_requires_every_document_returned(): void
    {
        $user = $this->staff('clients.edit');
        $client = $this->client();
        $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}/case-closure", [
            'handover_checklist' => [
                ['title' => 'Original passport', 'returned' => true],
                ['title' => 'Original NIC', 'returned' => false],
            ],
        ]);

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-closure/archive")
            ->assertStatus(422)->assertJsonValidationErrors('handover_checklist');

        ClientCaseClosure::first()->update(['handover_checklist' => [
            ['title' => 'Original passport', 'returned' => true],
            ['title' => 'Original NIC', 'returned' => true],
        ]]);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-closure/archive");
        $response->assertOk();
        $response->assertJsonPath('data.archived', true);
    }

    public function test_complete_requires_the_case_to_be_archived_first(): void
    {
        $user = $this->staff('clients.edit');
        $client = $this->client();
        ClientCaseClosure::create([
            'client_id' => $client->id,
            'handover_checklist' => [['title' => 'Original passport', 'returned' => true]],
        ]);

        $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-closure/complete")
            ->assertStatus(422)->assertJsonValidationErrors('archived');

        ClientCaseClosure::first()->update(['archived' => true, 'archived_at' => now()]);

        $response = $this->actingAs($user)->postJson("/api/v1/clients/{$client->id}/case-closure/complete");
        $response->assertOk();
        $this->assertNotNull(ClientCaseClosure::first()->completed_at);
    }

    public function test_show_returns_null_when_nothing_recorded_yet(): void
    {
        $user = $this->staff('clients.view');
        $client = $this->client();

        $response = $this->actingAs($user)->getJson("/api/v1/clients/{$client->id}/case-closure");

        $response->assertOk();
        $this->assertNull($response->json('data'));
    }
}
