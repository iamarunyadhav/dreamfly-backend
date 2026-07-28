<?php

namespace Tests\Feature\Communications;

use Modules\Communications\Services\ChannelSettingsService;
use Modules\Communications\Services\Providers\CommunicationProviderFactory;
use Modules\Communications\Services\Providers\LogCommunicationProvider;
use Tests\TestCase;

class CommunicationProviderFactoryTest extends TestCase
{
    public function test_the_testing_environment_always_gets_the_log_provider_regardless_of_settings(): void
    {
        app(ChannelSettingsService::class)->update([
            'whatsapp' => ['enabled' => true, 'provider' => 'whatsapp-cloud', 'sender' => null, 'webhook_url' => null, 'api_key' => 'k', 'access_token' => 'tok', 'phone_number_id' => '123', 'business_account_id' => null, 'retry_behavior' => 'manual'],
            'email' => ['enabled' => true, 'provider' => 'smtp', 'from_name' => 'Dream Fly', 'from_email' => 'a@b.com', 'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'a@b.com', 'password' => 'secret'],
            'sms' => ['enabled' => true, 'provider' => 'twilio', 'from_number' => '+15005550006', 'account_sid' => 'AC1', 'auth_token' => 'tok', 'messaging_service_sid' => null, 'retry_behavior' => 'manual'],
        ]);

        $factory = app(CommunicationProviderFactory::class);

        $this->assertInstanceOf(LogCommunicationProvider::class, $factory->forChannel('whatsapp'));
        $this->assertInstanceOf(LogCommunicationProvider::class, $factory->forChannel('email'));
        $this->assertInstanceOf(LogCommunicationProvider::class, $factory->forChannel('sms'));
    }
}
