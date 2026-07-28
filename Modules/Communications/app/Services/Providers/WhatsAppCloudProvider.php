<?php

namespace Modules\Communications\Services\Providers;

use Illuminate\Support\Facades\Http;
use Modules\Communications\Models\Message;
use Throwable;

/**
 * Sends a text message via the Meta WhatsApp Cloud API
 * (https://graph.facebook.com/{version}/{phone_number_id}/messages).
 */
class WhatsAppCloudProvider implements CommunicationProviderInterface
{
    private const API_VERSION = 'v21.0';

    public function __construct(private array $settings)
    {
    }

    public function send(Message $message): ProviderResult
    {
        try {
            $response = Http::withToken((string) $this->settings['access_token'])
                ->timeout(30)
                ->post(sprintf('https://graph.facebook.com/%s/%s/messages', self::API_VERSION, $this->settings['phone_number_id']), [
                    'messaging_product' => 'whatsapp',
                    'to' => $message->recipient,
                    'type' => 'text',
                    'text' => ['body' => $message->body],
                ]);

            if ($response->successful()) {
                return new ProviderResult(
                    success: true,
                    provider: 'whatsapp-cloud',
                    providerMessageId: $response->json('messages.0.id'),
                );
            }

            return new ProviderResult(
                success: false,
                provider: 'whatsapp-cloud',
                failureReason: $response->json('error.message') ?? ('HTTP '.$response->status()),
            );
        } catch (Throwable $e) {
            return new ProviderResult(success: false, provider: 'whatsapp-cloud', failureReason: $e->getMessage());
        }
    }
}
