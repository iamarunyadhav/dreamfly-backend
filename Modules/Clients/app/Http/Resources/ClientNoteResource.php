<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'body' => $this->body,
            'visibility' => $this->visibility,
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
