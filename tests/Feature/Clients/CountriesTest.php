<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Modules\Clients\Models\Country;
use Tests\TestCase;

class CountriesTest extends TestCase
{
    private function staff(string $permission): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permission);

        return $user;
    }

    public function test_index_lists_seeded_countries_with_sri_lanka_first(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/v1/countries');

        $response->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertSame('Sri Lanka', $names[0]);
        $this->assertContains('United Kingdom', $names);
    }

    public function test_a_staff_member_who_can_create_leads_can_add_a_country(): void
    {
        $user = $this->staff('common-users.create');

        $response = $this->actingAs($user)->postJson('/api/v1/countries', ['name' => 'Kyrgyzstan']);

        $response->assertCreated();
        $this->assertDatabaseHas('countries', ['name' => 'Kyrgyzstan']);

        // It shows up in the dropdown immediately.
        $list = $this->actingAs($user)->getJson('/api/v1/countries');
        $this->assertContains('Kyrgyzstan', array_column($list->json('data'), 'name'));
    }

    public function test_a_user_without_lead_or_client_create_permission_cannot_add_a_country(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->postJson('/api/v1/countries', ['name' => 'Kyrgyzstan'])->assertStatus(403);
    }

    public function test_adding_a_duplicate_country_name_is_rejected_case_insensitively(): void
    {
        $user = $this->staff('clients.create');

        $this->actingAs($user)->postJson('/api/v1/countries', ['name' => 'sri lanka'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_a_disabled_country_does_not_appear_in_the_list(): void
    {
        Country::where('name', 'Malta')->update(['is_active' => false]);
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/v1/countries');

        $this->assertNotContains('Malta', array_column($response->json('data'), 'name'));
    }
}
