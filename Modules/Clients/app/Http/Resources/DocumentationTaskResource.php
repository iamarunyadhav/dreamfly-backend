<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentationTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client_reference_no' => $this->whenLoaded('client', fn () => $this->client?->reference_no),
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->full_name),
            'title' => $this->title,
            'description' => $this->description,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->name),
            'assigned_role' => $this->assigned_role,
            'supervisor_id' => $this->supervisor_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'start_at' => $this->start_at,
            'due_at' => $this->due_at,
            'completed_at' => $this->completed_at,
            'hold_reason' => $this->hold_reason,
            'reminder_at' => $this->reminder_at,
            'reminded_at' => $this->reminded_at,
            'escalation_at' => $this->escalation_at,
            'escalated_at' => $this->escalated_at,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
