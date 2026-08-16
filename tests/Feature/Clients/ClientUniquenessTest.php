<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Workflows\Models\CaseStep;
use Tests\TestCase;

class ClientUniquenessTest extends TestCase
{
    private function staff(string $permission): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permission);

        return $user;
    }

    private function client(array $overrides = []): Client
    {
        return Client::create([
            'reference_no' => 'DF-950-2026',
            'full_name' => 'Client A',
            'phone' => '0773330000',
            'email' => 'client.a@example.com',
            'nic' => '199933330000',
            'passport_no' => 'N3330000',
            'service_category' => 'visit_visa',
            'current_stage' => 'admin_summary',
            'status' => 'active',
            ...$overrides,
        ]);
    }

    public function test_creating_a_client_with_a_duplicate_phone_is_rejected(): void
    {
        $this->client();
        $user = $this->staff('clients.create');

        $response = $this->actingAs($user)->postJson('/api/v1/clients', [
            'full_name' => 'Client B',
            'phone' => '0773330000',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_creating_a_client_directly_from_its_own_lead_does_not_collide(): void
    {
        $lead = CommonUser::create([
            'full_name' => 'Paired Lead',
            'phone' => '0774440000',
            'passport_no' => 'N4440000',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'status' => 'fully_paid',
        ]);
        $user = $this->staff('clients.create');

        $response = $this->actingAs($user)->postJson('/api/v1/clients', [
            'common_user_id' => $lead->id,
            'full_name' => $lead->full_name,
            'phone' => $lead->phone,
            'passport_no' => $lead->passport_no,
        ]);

        $response->assertCreated();

        // POST /clients is the other real client-creation path (besides lead
        // conversion) - it must also seed case_steps immediately, never left to
        // a manual "initialize" call.
        $this->assertSame(11, CaseStep::where('client_id', $response->json('data.id'))->count());
    }

    public function test_updating_a_client_to_collide_with_another_client_is_rejected(): void
    {
        $target = $this->client();
        $this->client(['reference_no' => 'DF-951-2026', 'phone' => '0775550000', 'email' => 'other@example.com', 'nic' => '199955550000', 'passport_no' => 'N5550000']);
        $user = $this->staff('clients.edit');

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$target->id}", ['phone' => '0775550000']);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_updating_a_client_keeping_its_own_paired_leads_phone_is_allowed(): void
    {
        $lead = CommonUser::create([
            'full_name' => 'Paired Lead 2',
            'phone' => '0776660000',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'status' => 'converted',
        ]);
        $client = $this->client([
            'reference_no' => 'DF-952-2026',
            'common_user_id' => $lead->id,
            'phone' => $lead->phone,
        ]);
        $user = $this->staff('clients.edit');

        $response = $this->actingAs($user)->putJson("/api/v1/clients/{$client->id}", ['phone' => $lead->phone]);

        $response->assertOk();
    }
}
