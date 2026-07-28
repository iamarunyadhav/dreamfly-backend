<?php

namespace Modules\Communications\Services\Providers;

use Modules\Communications\Models\Message;

interface CommunicationProviderInterface
{
    public function send(Message $message): ProviderResult;
}
