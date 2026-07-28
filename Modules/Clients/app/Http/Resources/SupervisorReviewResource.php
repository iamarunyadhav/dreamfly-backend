<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisorReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'round' => $this->round,
            'status' => $this->status,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at,
            'decision_notes' => $this->decision_notes,
            'sent_back_to_stage' => $this->sent_back_to_stage,
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_to_name' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->name),
            'comment_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
