<?php

namespace Modules\Checklists\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Http\Resources\FileResource;

class CaseChecklistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'application_unit_id' => $this->application_unit_id,
            'owner' => $this->owner,
            'source_index' => $this->source_index,
            'title' => $this->title,
            'status' => $this->status,
            'is_required' => (bool) $this->is_required,
            'document_required' => (bool) $this->document_required,
            'linked_file_id' => $this->linked_file_id,
            'linked_file' => $this->whenLoaded('linkedFile', fn () => new FileResource($this->linkedFile)),
            'note' => $this->note,
            'rejection_reason' => $this->rejection_reason,
            'completed_at' => $this->completed_at,
            'verified_at' => $this->verified_at,
            'verified_by' => $this->verified_by,
            'rejected_at' => $this->rejected_at,
            'rejected_by' => $this->rejected_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
