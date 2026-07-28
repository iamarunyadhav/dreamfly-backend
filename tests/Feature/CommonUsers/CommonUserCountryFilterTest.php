<?php

namespace Tests\Feature\CommonUsers;

use App\Models\User;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Tests\TestCase;

class CommonUserCountryFilterTest extends TestCase
{
    public function test_common_users_index_filters_by_destination_country(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('common-users.view');

        CommonUser::create(['full_name' => 'UK Lead', 'country' => 'United Kingdom', 'service_category' => 'visit_visa', 'agreement_amount' => 1000, 'status' => 'unpaid']);
        CommonUser::create(['full_name' => 'Australia Lead', 'country' => 'Australia', 'service_category' => 'visit_visa', 'agreement_amount' => 1000, 'status' => 'unpaid']);

        $response = $this->actingAs($user)->getJson('/api/v1/common-users?country=Australia');

        $response->assertOk();
        $names = array_column($response->json('data'), 'full_name');
        $this->assertSame(['Australia Lead'], $names);
    }

    public function test_clients_index_filters_by_destination_country(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('clients.view');

        Client::create(['reference_no' => 'DF-970-2026', 'full_name' => 'UK Client', 'country' => 'United Kingdom', 'service_category' => 'visit_visa', 'current_stage' => 'admin_summary', 'status' => 'active']);
        Client::create(['reference_no' => 'DF-971-2026', 'full_name' => 'Canada Client', 'country' => 'Canada', 'service_category' => 'visit_visa', 'current_stage' => 'admin_summary', 'status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/v1/clients?country=Canada');

        $response->assertOk();
        $names = array_column($response->json('data'), 'full_name');
        $this->assertSame(['Canada Client'], $names);
    }
}
