<?php

namespace Modules\Ocr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcrExtractionFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'label' => $this->label,
            'value' => $this->value,
            'confidence' => $this->confidence,
            'is_user_edited' => (bool) $this->is_user_edited,
        ];
    }
}
