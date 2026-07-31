<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Modules\Clients\Models\Client;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    /** Each unit role must hold the permission group its own stage routes require. */
    private const UNIT_ROLE_MODULES = [
        'Application Unit Staff' => 'application-unit',
        'Documentation Unit Staff' => 'documentation-unit',
    ];

    public function test_user_with_roles_view_permission_can_list_roles(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('roles.view');

        $response = $this->actingAs($user)->getJson('/api/v1/roles');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_user_without_roles_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/v1/roles');

        $response->assertStatus(403);
    }

    public function test_user_with_roles_create_permission_can_create_role_with_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('roles.create');

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'name' => 'Test Role',
            'permissions' => ['contacts.view', 'contacts.create'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Test Role');

        $role = Role::where('name', 'Test Role')->first();
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(
            ['contacts.view', 'contacts.create'],
            $role->fresh()->permissions->pluck('name')->all()
        );
    }

    public function test_user_without_roles_create_permission_cannot_create_role(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'name' => 'Another Role',
        ]);

        $response->assertStatus(403);
        $this->assertNull(Role::where('name', 'Another Role')->first());
    }

    public function test_permissions_index_returns_seeded_permission_list(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('roles.view');

        $response = $this->actingAs($user)->getJson('/api/v1/permissions');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(Permission::count(), $data);

        $usersView = collect($data)->firstWhere('name', 'users.view');
        $this->assertNotNull($usersView);
        $this->assertSame('users', $usersView['group']);
        $this->assertSame('view', $usersView['action']);
    }

    /**
     * Guards the defect where a stage's routes required `<module>.*` but the
     * seeded role that owns that stage was never granted the group at all.
     */
    public function test_seeded_unit_roles_hold_every_permission_their_own_module_declares(): void
    {
        foreach (self::UNIT_ROLE_MODULES as $roleName => $module) {
            $role = Role::where('name', $roleName)->first();
            $this->assertNotNull($role, "{$roleName} is not seeded.");

            $declared = Permission::where('name', 'like', "{$module}.%")->pluck('name');
            $this->assertNotEmpty($declared, "No {$module}.* permissions are declared.");

            $held = $role->permissions->pluck('name')
                ->filter(fn (string $name) => str_starts_with($name, "{$module}."));

            $this->assertEqualsCanonicalizing(
                $declared->all(),
                $held->values()->all(),
                "{$roleName} is missing {$module}.* permissions its own routes require."
            );
        }
    }

    /**
     * case-steps.reset is the one deliberate deviation in this seeder: Manager
     * is otherwise identical to Admin/Super Admin everywhere else, but must not
     * be able to reopen a completed workflow step.
     */
    public function test_case_steps_reset_permission_is_admin_and_super_admin_only(): void
    {
        $this->assertTrue(Role::where('name', 'Super Admin')->first()->hasPermissionTo('case-steps.reset'));
        $this->assertTrue(Role::where('name', 'Admin')->first()->hasPermissionTo('case-steps.reset'));

        foreach ([
            'Manager', 'Supervisor', 'Application Unit Staff', 'Documentation Unit Staff',
            'Accounts Staff', 'Reception Staff', 'Read-only Staff',
        ] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            $this->assertNotNull($role, "{$roleName} is not seeded.");
            $this->assertFalse($role->hasPermissionTo('case-steps.reset'), "{$roleName} should not hold case-steps.reset.");
        }
    }

    /** The role itself — not a hand-granted permission — must reach the stage it owns. */
    public function test_application_unit_staff_role_can_reach_the_application_unit_endpoint(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Application Unit Staff');

        $client = Client::create([
            'reference_no' => 'DF-901-2026',
            'full_name' => 'Role Gate Check',
            'service_category' => 'visit_visa',
            'current_stage' => 'application_unit',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/clients/{$client->id}/application-unit")
            ->assertOk();
    }
}
