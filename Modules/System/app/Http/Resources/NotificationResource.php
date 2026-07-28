<?php

namespace Modules\System\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'client_id' => $this->client_id,
            'documentation_task_id' => $this->documentation_task_id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status,
            'read_at' => $this->read_at,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
