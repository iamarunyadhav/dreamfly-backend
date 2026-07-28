<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientApplicationUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'form_data' => $this->form_data,
            'applicant_checklist' => $this->applicant_checklist,
            'inviter_checklist' => $this->inviter_checklist,
            'internal_checklist' => $this->internal_checklist,
            'notes' => $this->notes,
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

