<?php

namespace Modules\Communications\Services;

use Modules\System\Models\SystemSetting;

class ChannelSettingsService
{
    private const WHATSAPP_KEY = 'communications.whatsapp';
    private const EMAIL_KEY = 'communications.email';
    private const SMS_KEY = 'communications.sms';
    private const MASK = '********';

    public function getMasked(): array
    {
        return [
            'whatsapp' => $this->maskSecrets($this->stored(self::WHATSAPP_KEY, $this->defaultWhatsapp()), ['api_key', 'access_token']),
            'email' => $this->maskSecrets($this->stored(self::EMAIL_KEY, $this->defaultEmail()), ['password']),
            'sms' => $this->maskSecrets($this->stored(self::SMS_KEY, $this->defaultSms()), ['auth_token']),
        ];
    }

    public function getRaw(): array
    {
        return [
            'whatsapp' => $this->stored(self::WHATSAPP_KEY, $this->defaultWhatsapp()),
            'email' => $this->stored(self::EMAIL_KEY, $this->defaultEmail()),
            'sms' => $this->stored(self::SMS_KEY, $this->defaultSms()),
        ];
    }

    public function update(array $payload): array
    {
        $whatsapp = $this->mergePreservingSecrets(
            $this->stored(self::WHATSAPP_KEY, $this->defaultWhatsapp()),
            $payload['whatsapp'],
            ['api_key', 'access_token']
        );
        $email = $this->mergePreservingSecrets(
            $this->stored(self::EMAIL_KEY, $this->defaultEmail()),
            $payload['email'],
            ['password']
        );
        $sms = $this->mergePreservingSecrets(
            $this->stored(self::SMS_KEY, $this->defaultSms()),
            $payload['sms'],
            ['auth_token']
        );

        SystemSetting::updateOrCreate(['key' => self::WHATSAPP_KEY], ['value' => json_encode($whatsapp)]);
        SystemSetting::updateOrCreate(['key' => self::EMAIL_KEY], ['value' => json_encode($email)]);
        SystemSetting::updateOrCreate(['key' => self::SMS_KEY], ['value' => json_encode($sms)]);

        return $this->getMasked();
    }

    private function stored(string $key, array $default): array
    {
        $value = SystemSetting::where('key', $key)->value('value');
        $decoded = $value ? json_decode($value, true) : null;

        return is_array($decoded) ? array_replace($default, $decoded) : $default;
    }

    private function mergePreservingSecrets(array $current, array $incoming, array $secretKeys): array
    {
        $merged = array_replace($current, $incoming);

        foreach ($secretKeys as $key) {
            if (($incoming[$key] ?? null) === null || ($incoming[$key] ?? '') === '' || $incoming[$key] === self::MASK) {
                $merged[$key] = $current[$key] ?? null;
            }
        }

        return $merged;
    }

    private function maskSecrets(array $settings, array $secretKeys): array
    {
        foreach ($secretKeys as $key) {
            $settings[$key] = empty($settings[$key]) ? null : self::MASK;
        }

        return $settings;
    }

    private function defaultWhatsapp(): array
    {
        return [
            'enabled' => false,
            'provider' => 'manual',
            'sender' => null,
            'webhook_url' => null,
            'api_key' => null,
            'access_token' => null,
            'phone_number_id' => null,
            'business_account_id' => null,
            'retry_behavior' => 'manual',
        ];
    }

    private function defaultEmail(): array
    {
        return [
            'enabled' => false,
            'provider' => 'smtp',
            'from_name' => 'Dream Fly Visa Consultancy',
            'from_email' => null,
            'host' => null,
            'port' => 587,
            'encryption' => 'tls',
            'username' => null,
            'password' => null,
        ];
    }

    private function defaultSms(): array
    {
        return [
            'enabled' => false,
            'provider' => 'twilio',
            'from_number' => null,
            'account_sid' => null,
            'auth_token' => null,
            'messaging_service_sid' => null,
            'retry_behavior' => 'manual',
        ];
    }
}
