<?php

namespace Tests\Feature\CommonUsers;

use App\Models\User;
use Modules\CommonUsers\Models\CommonUser;
use Tests\TestCase;

class CommonUserStatusFilterTest extends TestCase
{
    private function staff(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('common-users.view');

        return $user;
    }

    private function lead(string $status, array $overrides = []): CommonUser
    {
        static $n = 0;
        $n++;

        return CommonUser::create([
            'full_name' => "Lead {$n}",
            'phone' => "07700000{$n}0",
            'service_category' => 'visit_visa',
            'agreement_amount' => 100000,
            'status' => $status,
            ...$overrides,
        ]);
    }

    public function test_default_index_hides_converted_leads(): void
    {
        $this->lead('unpaid');
        $this->lead('partially_paid');
        $this->lead('converted');
        $user = $this->staff();

        $response = $this->actingAs($user)->getJson('/api/v1/common-users');

        $response->assertOk();
        $statuses = array_column($response->json('data'), 'status');
        $this->assertCount(2, $statuses);
        $this->assertNotContains('converted', $statuses);
    }

    public function test_status_all_shows_every_status_including_converted(): void
    {
        $this->lead('unpaid');
        $this->lead('converted');
        $user = $this->staff();

        $response = $this->actingAs($user)->getJson('/api/v1/common-users?status=all');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_status_converted_shows_only_converted_leads(): void
    {
        $this->lead('unpaid');
        $this->lead('converted');
        $this->lead('converted');
        $user = $this->staff();

        $response = $this->actingAs($user)->getJson('/api/v1/common-users?status=converted');

        $response->assertOk();
        $statuses = array_column($response->json('data'), 'status');
        $this->assertCount(2, $statuses);
        $this->assertSame(['converted', 'converted'], $statuses);
    }

    public function test_a_specific_non_converted_status_filters_exactly(): void
    {
        $this->lead('unpaid');
        $this->lead('fully_paid');
        $this->lead('converted');
        $user = $this->staff();

        $response = $this->actingAs($user)->getJson('/api/v1/common-users?status=fully_paid');

        $response->assertOk();
        $this->assertSame(['fully_paid'], array_column($response->json('data'), 'status'));
    }
}
