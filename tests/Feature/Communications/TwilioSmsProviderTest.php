<?php

namespace Tests\Feature\Communications;

use Illuminate\Support\Facades\Http;
use Modules\Communications\Models\Message;
use Modules\Communications\Services\Providers\TwilioSmsProvider;
use Tests\TestCase;

class TwilioSmsProviderTest extends TestCase
{
    private function settings(): array
    {
        return [
            'account_sid' => 'AC-test-sid',
            'auth_token' => 'test-token',
            'from_number' => '+15005550006',
            'messaging_service_sid' => null,
        ];
    }

    public function test_a_successful_send_returns_the_twilio_message_sid(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $message = Message::make(['recipient' => '+94762275432', 'body' => 'Hello']);
        $result = (new TwilioSmsProvider($this->settings()))->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('twilio', $result->provider);
        $this->assertSame('SM123', $result->providerMessageId);
    }

    public function test_a_failed_send_returns_the_twilio_error_message(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['message' => 'Authentication Error'], 401),
        ]);

        $message = Message::make(['recipient' => '+94762275432', 'body' => 'Hello']);
        $result = (new TwilioSmsProvider($this->settings()))->send($message);

        $this->assertFalse($result->success);
        $this->assertSame('Authentication Error', $result->failureReason);
    }
}
