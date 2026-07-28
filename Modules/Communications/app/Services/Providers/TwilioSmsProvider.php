<?php

namespace Modules\Communications\Services\Providers;

use Illuminate\Support\Facades\Http;
use Modules\Communications\Models\Message;
use Throwable;

/**
 * Sends an SMS via Twilio's Messages API
 * (https://api.twilio.com/2010-04-01/Accounts/{account_sid}/Messages.json).
 */
class TwilioSmsProvider implements CommunicationProviderInterface
{
    public function __construct(private array $settings)
    {
    }

    public function send(Message $message): ProviderResult
    {
        try {
            $payload = [
                'To' => $message->recipient,
                'Body' => $message->body,
            ];

            if (! empty($this->settings['messaging_service_sid'])) {
                $payload['MessagingServiceSid'] = $this->settings['messaging_service_sid'];
            } else {
                $payload['From'] = $this->settings['from_number'];
            }

            $response = Http::asForm()
                ->withBasicAuth((string) $this->settings['account_sid'], (string) $this->settings['auth_token'])
                ->timeout(30)
                ->post(sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $this->settings['account_sid']), $payload);

            if ($response->successful()) {
                return new ProviderResult(success: true, provider: 'twilio', providerMessageId: $response->json('sid'));
            }

            return new ProviderResult(
                success: false,
                provider: 'twilio',
                failureReason: $response->json('message') ?? ('HTTP '.$response->status()),
            );
        } catch (Throwable $e) {
            return new ProviderResult(success: false, provider: 'twilio', failureReason: $e->getMessage());
        }
    }
}
