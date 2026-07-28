<?php

namespace Tests\Feature\Communications;

use App\Models\User;
use Modules\System\Models\SystemSetting;
use Tests\TestCase;

class ChannelSettingsTest extends TestCase
{
    public function test_channel_settings_are_saved_with_masked_secret_responses(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['communications.view', 'communications.update']);

        $response = $this->actingAs($user)->putJson('/api/v1/communications/channel-settings', [
            'whatsapp' => [
                'enabled' => true,
                'provider' => 'whatsapp-cloud',
                'sender' => '+94762275432',
                'webhook_url' => 'https://example.com/webhooks/whatsapp',
                'api_key' => 'api-secret',
                'access_token' => 'token-secret',
                'retry_behavior' => 'auto',
            ],
            'email' => [
                'enabled' => true,
                'provider' => 'smtp',
                'from_name' => 'Dream Fly',
                'from_email' => 'dreamflyaz@gmail.com',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'dreamflyaz@gmail.com',
                'password' => 'mail-secret',
            ],
            'sms' => [
                'enabled' => true,
                'provider' => 'twilio',
                'from_number' => '+15005550006',
                'account_sid' => 'AC-sid',
                'auth_token' => 'sms-secret',
                'messaging_service_sid' => null,
                'retry_behavior' => 'manual',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.whatsapp.api_key', '********');
        $response->assertJsonPath('data.whatsapp.access_token', '********');
        $response->assertJsonPath('data.email.password', '********');
        $response->assertJsonPath('data.sms.auth_token', '********');

        $stored = json_decode(SystemSetting::where('key', 'communications.whatsapp')->value('value'), true);
        $this->assertSame('api-secret', $stored['api_key']);
        $this->assertSame('token-secret', $stored['access_token']);

        $storedSms = json_decode(SystemSetting::where('key', 'communications.sms')->value('value'), true);
        $this->assertSame('sms-secret', $storedSms['auth_token']);
    }

    public function test_masked_secret_inputs_preserve_existing_values(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['communications.view', 'communications.update']);

        SystemSetting::create([
            'key' => 'communications.email',
            'value' => json_encode([
                'enabled' => true,
                'provider' => 'smtp',
                'from_name' => 'Dream Fly',
                'from_email' => 'old@example.com',
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'old@example.com',
                'password' => 'existing-secret',
            ]),
        ]);
        SystemSetting::create([
            'key' => 'communications.sms',
            'value' => json_encode([
                'enabled' => true,
                'provider' => 'twilio',
                'from_number' => '+15005550006',
                'account_sid' => 'AC-old',
                'auth_token' => 'existing-sms-secret',
                'messaging_service_sid' => null,
                'retry_behavior' => 'manual',
            ]),
        ]);

        $this->actingAs($user)->putJson('/api/v1/communications/channel-settings', [
            'whatsapp' => [
                'enabled' => false,
                'provider' => 'manual',
                'sender' => null,
                'webhook_url' => null,
                'api_key' => null,
                'access_token' => null,
                'retry_behavior' => 'manual',
            ],
            'email' => [
                'enabled' => true,
                'provider' => 'smtp',
                'from_name' => 'Dream Fly',
                'from_email' => 'new@example.com',
                'host' => 'smtp.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'new@example.com',
                'password' => '********',
            ],
            'sms' => [
                'enabled' => true,
                'provider' => 'twilio',
                'from_number' => '+15005550006',
                'account_sid' => 'AC-new',
                'auth_token' => '********',
                'messaging_service_sid' => null,
                'retry_behavior' => 'manual',
            ],
        ])->assertOk();

        $stored = json_decode(SystemSetting::where('key', 'communications.email')->value('value'), true);

        $this->assertSame('new@example.com', $stored['from_email']);
        $this->assertSame('existing-secret', $stored['password']);

        $storedSms = json_decode(SystemSetting::where('key', 'communications.sms')->value('value'), true);
        $this->assertSame('AC-new', $storedSms['account_sid']);
        $this->assertSame('existing-sms-secret', $storedSms['auth_token']);
    }
}
