<?php

namespace Modules\Communications\Services\Providers;

use Illuminate\Support\Str;
use Modules\Communications\Models\Message;

class LogCommunicationProvider implements CommunicationProviderInterface
{
    public function __construct(private string $provider)
    {
    }

    public function send(Message $message): ProviderResult
    {
        return new ProviderResult(
            success: true,
            provider: $this->provider,
            providerMessageId: 'df_'.Str::uuid()->toString()
        );
    }
}
