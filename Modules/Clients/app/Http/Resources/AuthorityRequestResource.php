<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Http\Resources\FileResource;

class AuthorityRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'authority' => $this->authority,
            'request_type' => $this->request_type,
            'title' => $this->title,
            'description' => $this->description,
            'received_at' => optional($this->received_at)->toDateString(),
            'due_at' => optional($this->due_at)->toDateString(),
            'status' => $this->status,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->name),
            'responded_at' => optional($this->responded_at)->toDateString(),
            'response_notes' => $this->response_notes,
            'response_file_id' => $this->response_file_id,
            'response_file' => $this->whenLoaded('responseFile', fn () => $this->responseFile ? new FileResource($this->responseFile) : null),
            'reminded_at' => $this->reminded_at,
            'is_overdue' => $this->is_overdue,
            'days_remaining' => $this->days_remaining,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
