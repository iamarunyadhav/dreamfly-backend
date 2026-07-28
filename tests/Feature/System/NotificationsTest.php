<?php

namespace Tests\Feature\System;

use App\Models\User;
use Modules\System\Models\Notification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    public function test_user_can_view_own_and_role_notifications_without_system_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Documentation Unit Staff');
        $other = User::factory()->create(['status' => 'active']);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'direct',
            'title' => 'Direct notification',
        ]);
        Notification::create([
            'role' => 'Documentation Unit Staff',
            'type' => 'role',
            'title' => 'Role notification',
        ]);
        Notification::create([
            'user_id' => $other->id,
            'type' => 'other',
            'title' => 'Other notification',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('unread_count', 2);
        $response->assertJsonFragment(['title' => 'Direct notification']);
        $response->assertJsonFragment(['title' => 'Role notification']);
        $response->assertJsonMissing(['title' => 'Other notification']);
    }

    public function test_user_can_mark_visible_notifications_read(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'deadline',
            'title' => 'Deadline notification',
        ]);

        $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_mark_all_read_only_updates_visible_notifications(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);

        $visible = Notification::create([
            'user_id' => $user->id,
            'type' => 'visible',
            'title' => 'Visible',
        ]);
        $hidden = Notification::create([
            'user_id' => $other->id,
            'type' => 'hidden',
            'title' => 'Hidden',
        ]);

        $this->actingAs($user)->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame('read', $visible->refresh()->status);
        $this->assertSame('unread', $hidden->refresh()->status);
    }
}
