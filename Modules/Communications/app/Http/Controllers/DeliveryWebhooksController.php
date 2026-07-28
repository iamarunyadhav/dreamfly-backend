<?php

namespace Modules\Communications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;

class DeliveryWebhooksController extends Controller
{
    use ApiResponse;

    public function __construct(protected MessageService $messages)
    {
    }

    public function store(string $channel, Request $request)
    {
        abort_unless(in_array($channel, ['whatsapp', 'email', 'sms'], true), 404);

        $message = $this->messages->recordWebhook($channel, $request->all());

        if (! $message) {
            return $this->ok(['recorded' => false], 'Webhook received but no matching message was found.');
        }

        return $this->ok(new MessageResource($message), 'Delivery status updated.');
    }
}
