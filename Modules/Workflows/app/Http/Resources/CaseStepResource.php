<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'workflow_template_id' => $this->workflow_template_id,
            'workflow_step_id' => $this->workflow_step_id,
            'key' => $this->key,
            'name' => $this->name,
            'order' => $this->order,
            'owner_role' => $this->owner_role,
            'status' => $this->status,
            'duration_days' => $this->duration_days,
            'requires_checklist' => (bool) $this->requires_checklist,
            'requires_acknowledgement' => (bool) $this->requires_acknowledgement,
            'requires_closure_record' => (bool) $this->requires_closure_record,
            'started_at' => $this->started_at,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'completed_by' => $this->completed_by,
            'completed_by_name' => $this->whenLoaded('completer', fn () => $this->completer?->name),
            'hold_reason' => $this->hold_reason,
            'held_at' => $this->held_at,
            'notes' => $this->notes,
            'is_overdue' => $this->is_overdue,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
