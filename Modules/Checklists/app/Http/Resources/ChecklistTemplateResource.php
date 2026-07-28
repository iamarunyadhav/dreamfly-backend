<?php

namespace Modules\Checklists\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'owner' => $this->owner,
            'category' => $this->category,
            'description' => $this->description,
            'is_required' => (bool) $this->is_required,
            'document_required' => (bool) $this->document_required,
            'status' => $this->status,
            'version' => (int) $this->version,
            'is_active' => (bool) $this->is_active,
            'versions_count' => $this->whenCounted('versions'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
