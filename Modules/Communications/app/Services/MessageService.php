<?php

namespace Modules\Communications\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Communications\Repositories\Contracts\MessageRepositoryInterface;
use Modules\Communications\Services\Providers\CommunicationProviderFactory;

class MessageService extends BaseService
{
    public function __construct(MessageRepositoryInterface $repository, private CommunicationProviderFactory $providers)
    {
        parent::__construct($repository);
    }

    /**
     * Record a message log entry and mark it as sent immediately.
     * There is no real WhatsApp/email/SMS integration yet - this
     * skeleton simply records the log entry as if it were sent instantly.
     *
     * $userId is nullable because system-raised sends (configured alerts) have
     * no acting user; `messages.sent_by` is nullable to match.
     */
    public function send(array $attributes, ?int $userId = null): Model
    {
        return DB::transaction(function () use ($attributes, $userId) {
            $message = $this->repository->create([...$attributes, 'status' => 'pending']);
            $result = $this->providers->forChannel($message->channel)->send($message);
            $history = [[
                'status' => $result->success ? 'sent' : 'failed',
                'at' => now()->toISOString(),
                'provider' => $result->provider,
                'reason' => $result->failureReason,
            ]];

            return $this->repository->update($message, [
                'status' => $result->success ? 'sent' : 'failed',
                'provider' => $result->provider,
                'provider_message_id' => $result->providerMessageId,
                'sent_at' => $result->success ? now() : null,
                'failed_at' => $result->success ? null : now(),
                'failure_reason' => $result->failureReason,
                'status_history' => $history,
                'sent_by' => $userId,
            ]);
        });
    }

    public function recordWebhook(string $channel, array $payload): ?Model
    {
        $providerMessageId = data_get($payload, 'provider_message_id')
            ?? data_get($payload, 'message_id')
            ?? data_get($payload, 'id');
        $status = data_get($payload, 'status', 'delivered');

        if (! $providerMessageId) {
            return null;
        }

        $message = $this->repository->query()->where('provider_message_id', $providerMessageId)->first();

        if (! $message) {
            return null;
        }

        $history = $message->status_history ?? [];
        $history[] = [
            'status' => $status,
            'at' => now()->toISOString(),
            'channel' => $channel,
        ];

        $updates = [
            'status' => in_array($status, ['failed', 'delivered', 'read'], true) ? $status : $message->status,
            'webhook_payload' => $payload,
            'status_history' => $history,
        ];

        if ($status === 'delivered') {
            $updates['delivered_at'] = now();
        } elseif ($status === 'read') {
            $updates['read_at'] = now();
        } elseif ($status === 'failed') {
            $updates['failed_at'] = now();
            $updates['failure_reason'] = data_get($payload, 'failure_reason') ?? data_get($payload, 'error');
        }

        return $this->repository->update($message, $updates);
    }
}
