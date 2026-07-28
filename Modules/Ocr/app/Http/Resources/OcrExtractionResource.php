<?php

namespace Modules\Ocr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcrExtractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_id' => $this->file_id,
            'status' => $this->status,
            'provider' => $this->provider,
            'error_message' => $this->error_message,
            'requested_by' => $this->requested_by,
            'completed_at' => $this->completed_at,
            'fields' => OcrExtractionFieldResource::collection($this->whenLoaded('fields')),
            'created_at' => $this->created_at,
        ];
    }
}
