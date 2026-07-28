<?php

namespace Tests\Feature\Communications;

use App\Models\User;
use Tests\TestCase;

class DeliveryTrackingTest extends TestCase
{
    public function test_message_send_uses_provider_abstraction_and_records_provider_id(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('communications.create');

        $response = $this->actingAs($user)->postJson('/api/v1/communications/messages', [
            'channel' => 'whatsapp',
            'recipient' => '+94762275432',
            'body' => 'Hello from Dream Fly',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'sent');
        $response->assertJsonPath('data.provider', 'manual');
        $this->assertNotNull($response->json('data.provider_message_id'));
        $this->assertIsArray($response->json('data.status_history'));
    }

    public function test_delivery_webhook_updates_message_status_history(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('communications.create');

        $messageId = $this->actingAs($user)->postJson('/api/v1/communications/messages', [
            'channel' => 'email',
            'recipient' => 'client@example.com',
            'subject' => 'Invoice',
            'body' => 'Please see attached invoice.',
        ])->json('data.provider_message_id');

        $response = $this->postJson('/api/v1/communications/webhooks/email', [
            'provider_message_id' => $messageId,
            'status' => 'delivered',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'delivered');
        $this->assertNotNull($response->json('data.delivered_at'));
        $this->assertCount(2, $response->json('data.status_history'));
    }
}
