<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_type' => $this->service_type,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'steps' => WorkflowStepResource::collection($this->whenLoaded('steps')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
