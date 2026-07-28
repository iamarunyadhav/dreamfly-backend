<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientAdminSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'summary' => $this->summary,
            'internal_notes' => $this->internal_notes,
            'client_share_notes' => $this->client_share_notes,
            'form_data' => $this->form_data,
            'supervisor_id' => $this->supervisor_id,
            'application_staff_id' => $this->application_staff_id,
            'deadline_at' => $this->deadline_at,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'completed_by' => $this->completed_by,
            'generated_file_id' => $this->generated_file_id,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
