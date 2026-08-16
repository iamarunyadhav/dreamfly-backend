<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Tests\TestCase;

class AdditionalChargesTest extends TestCase
{
    private function staff(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['common-users.edit', 'common-users.view', 'clients.edit', 'clients.view', 'payments.create', 'payments.delete']);

        return $user;
    }

    public function test_additional_charge_on_a_lead_increases_balance(): void
    {
        $lead = CommonUser::create([
            'full_name' => 'Extra Charge Lead',
            'country' => 'Canada',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'paid_amount' => 25000,
            'status' => 'partially_paid',
        ]);

        $this->assertSame(75000, $lead->refresh()->balance);

        $response = $this->actingAs($this->staff())->postJson("/api/v1/common-users/{$lead->id}/additional-charges", [
            'description' => 'Police report document',
            'amount' => 12000,
        ]);

        $response->assertCreated();
        $this->assertSame(87000, $lead->refresh()->balance);
        $this->assertSame(12000, $lead->additional_charges_total);

        $chargeId = $response->json('data.id');

        $this->actingAs($this->staff())
            ->deleteJson("/api/v1/common-users/{$lead->id}/additional-charges/{$chargeId}")
            ->assertOk();

        $this->assertSame(75000, $lead->refresh()->balance);
    }

    public function test_additional_charge_on_a_client_increases_balance_and_is_listed_in_profile(): void
    {
        $client = Client::create([
            'reference_no' => 'DF-1-2026',
            'full_name' => 'Extra Charge Client',
            'country' => 'Canada',
            'visa_type' => 'Visit Visa',
            'service_category' => 'visit_visa',
            'agreement_amount' => 200000,
            'paid_amount' => 50000,
            'status' => 'active',
        ]);

        $staff = $this->staff();

        $response = $this->actingAs($staff)->postJson("/api/v1/clients/{$client->id}/additional-charges", [
            'description' => 'Courier fee',
            'amount' => 3000,
        ]);

        $response->assertCreated();
        $this->assertSame(153000, $client->refresh()->balance);

        $profile = $this->actingAs($staff)->getJson("/api/v1/clients/{$client->id}/profile");
        $profile->assertOk();
        $profile->assertJsonPath('data.additional_charges.0.description', 'Courier fee');
        $profile->assertJsonPath('data.client.additional_charges_total', 3000);
    }
}
