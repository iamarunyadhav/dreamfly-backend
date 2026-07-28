<?php

namespace Tests\Feature\Communications;

use Illuminate\Support\Facades\Http;
use Modules\Communications\Models\Message;
use Modules\Communications\Services\Providers\WhatsAppCloudProvider;
use Tests\TestCase;

class WhatsAppCloudProviderTest extends TestCase
{
    private function settings(): array
    {
        return [
            'access_token' => 'test-token',
            'phone_number_id' => '1234567890',
        ];
    }

    public function test_a_successful_send_returns_the_cloud_api_message_id(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
        ]);

        $message = Message::make(['recipient' => '+94762275432', 'body' => 'Hello']);
        $result = (new WhatsAppCloudProvider($this->settings()))->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('whatsapp-cloud', $result->provider);
        $this->assertSame('wamid.123', $result->providerMessageId);
    }

    public function test_a_failed_send_returns_the_api_error_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401),
        ]);

        $message = Message::make(['recipient' => '+94762275432', 'body' => 'Hello']);
        $result = (new WhatsAppCloudProvider($this->settings()))->send($message);

        $this->assertFalse($result->success);
        $this->assertSame('Invalid OAuth access token', $result->failureReason);
    }
}
