<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_user_with_role_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@dreamfly.test',
            'password' => 'password',
            'status' => 'active',
        ]);
        $user->assignRole('Read-only Staff');

        // A Referer/Origin header matching config('sanctum.stateful') is required so
        // Sanctum's EnsureFrontendRequestsAreStateful middleware treats this as a
        // first-party frontend request and starts the session (exactly as a real
        // SPA request from the browser would, via cookies) — without it, the
        // controller's $request->session() calls would throw.
        $response = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/auth/login', [
            'email' => 'staff@dreamfly.test',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.email', 'staff@dreamfly.test');

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'staff@dreamfly.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@dreamfly.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login_even_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'inactive@dreamfly.test',
            'password' => 'password',
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@dreamfly.test',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_fetch_own_profile_with_permissions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Read-only Staff');

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.email', $user->email);
        $this->assertNotEmpty($response->json('data.permissions'));
        $this->assertContains('users.view', $response->json('data.permissions'));
    }

    public function test_logout_invalidates_session_and_me_returns_401(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Read-only Staff');

        $this->actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/auth/logout')->assertOk();

        // The 'sanctum' guard (a RequestGuard) memoizes its resolved user for the
        // lifetime of the guard instance. Within a single test method the same
        // AuthManager/container persists across simulated requests (unlike real
        // separate PHP processes per request), so without forgetting the cached
        // guards here, the earlier /me call's resolved user would still be
        // reported as authenticated even though the 'web' guard was logged out.
        Auth::forgetGuards();

        $this->assertGuest();

        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
