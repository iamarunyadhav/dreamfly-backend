<?php

namespace Modules\Communications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_template_id' => $this->message_template_id,
            'client_id' => $this->client_id,
            'workflow_step' => $this->workflow_step,
            'channel' => $this->channel,
            'provider' => $this->provider,
            'provider_message_id' => $this->provider_message_id,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'sent_at' => $this->sent_at,
            'delivered_at' => $this->delivered_at,
            'read_at' => $this->read_at,
            'failed_at' => $this->failed_at,
            'failure_reason' => $this->failure_reason,
            'retry_count' => $this->retry_count,
            'status_history' => $this->status_history,
            'sent_by' => $this->sent_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
