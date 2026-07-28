<?php

namespace Modules\Communications\Services\Providers;

use Modules\Communications\Services\ChannelSettingsService;

class CommunicationProviderFactory
{
    public function __construct(private ChannelSettingsService $settings)
    {
    }

    public function forChannel(string $channel): CommunicationProviderInterface
    {
        $settings = $this->settings->getRaw();
        $inTesting = app()->environment('testing');

        if ($channel === 'whatsapp') {
            $whatsapp = $settings['whatsapp'];

            return (! $inTesting && $whatsapp['enabled'] && $whatsapp['phone_number_id'] && $whatsapp['access_token'])
                ? new WhatsAppCloudProvider($whatsapp)
                : new LogCommunicationProvider($whatsapp['provider'] ?? 'manual');
        }

        if ($channel === 'email') {
            $email = $settings['email'];

            return (! $inTesting && $email['enabled'] && $email['host'] && $email['from_email'])
                ? new SmtpCommunicationProvider($email)
                : new LogCommunicationProvider($email['provider'] ?? 'smtp');
        }

        if ($channel === 'sms') {
            $sms = $settings['sms'];

            return (! $inTesting && $sms['enabled'] && $sms['account_sid'] && $sms['auth_token'] && ($sms['from_number'] || $sms['messaging_service_sid']))
                ? new TwilioSmsProvider($sms)
                : new LogCommunicationProvider($sms['provider'] ?? 'manual');
        }

        return new LogCommunicationProvider('internal-log');
    }
}
