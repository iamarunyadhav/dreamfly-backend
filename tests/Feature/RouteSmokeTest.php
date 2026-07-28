<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    /**
     * Every collection-level (no route-model-binding params) GET index route
     * across all modules. Route-model-bound routes (e.g. agreements/{agreement}/pdf,
     * files/{file}/download) are intentionally excluded.
     */
    public static function indexRoutes(): array
    {
        return [
            'users.index' => ['/api/v1/users'],
            'roles.index' => ['/api/v1/roles'],
            'permissions.index' => ['/api/v1/permissions'],
            'contacts.index' => ['/api/v1/contacts'],
            'folders.index' => ['/api/v1/folders'],
            'files.index' => ['/api/v1/files'],
            'agreements.index' => ['/api/v1/agreements'],
            'common-users.index' => ['/api/v1/common-users'],
            'clients.index' => ['/api/v1/clients'],
            'payments.index' => ['/api/v1/payments'],
            'invoices.index' => ['/api/v1/invoices'],
            'checklists.index' => ['/api/v1/checklists'],
            'workflows.index' => ['/api/v1/workflows'],
            'communications.templates.index' => ['/api/v1/communications/templates'],
            'communications.messages.index' => ['/api/v1/communications/messages'],
            'finance.index' => ['/api/v1/finance/ledger'],
            'forms.index' => ['/api/v1/forms'],
            'audit-logs.index' => ['/api/v1/audit-logs'],
            'system.settings.index' => ['/api/v1/system/settings'],
        ];
    }

    #[DataProvider('indexRoutes')]
    public function test_index_route_is_reachable_for_super_admin(string $uri): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->getJson($uri);

        $this->assertNotEquals(500, $response->getStatusCode(), "Route {$uri} returned a 500 error: ".$response->getContent());
        $this->assertNotEquals(404, $response->getStatusCode(), "Route {$uri} returned a 404.");
        $response->assertOk();
    }
}
