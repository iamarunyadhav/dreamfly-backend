<?php

namespace Modules\Communications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
