<?php

namespace Modules\Communications\Services\Providers;

class ProviderResult
{
    public function __construct(
        public bool $success,
        public string $provider,
        public ?string $providerMessageId = null,
        public ?string $failureReason = null,
    ) {
    }
}
