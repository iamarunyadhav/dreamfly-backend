<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResponsibilityNoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'content' => $this->content,
            'status' => $this->status,
            'generated_file_id' => $this->generated_file_id,
            'shared_at' => $this->shared_at,
            'acknowledged' => (bool) $this->acknowledged,
            'acknowledged_at' => $this->acknowledged_at,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_by_name' => $this->whenLoaded('acknowledgedByUser', fn () => $this->acknowledgedByUser?->name),
            'acknowledgement_method' => $this->acknowledgement_method,
            'acknowledgement_note' => $this->acknowledgement_note,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
