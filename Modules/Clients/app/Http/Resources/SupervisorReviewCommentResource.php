<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisorReviewCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supervisor_review_id' => $this->supervisor_review_id,
            'client_id' => $this->client_id,
            'round' => $this->whenLoaded('review', fn () => $this->review?->round),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
