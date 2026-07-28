<?php

namespace Modules\Forms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'order' => $this->order,
            'options' => $this->options,
        ];
    }
}
