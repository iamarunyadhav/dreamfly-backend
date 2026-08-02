<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Http\Resources\FileResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'common_user_id' => $this->common_user_id,
            'reference_no' => $this->reference_no,
            'full_name' => $this->full_name,
            'passport_no' => $this->passport_no,
            'nic' => $this->nic,
            'phone' => $this->phone,
            'email' => $this->email,
            'country' => $this->country,
            'native_country' => $this->native_country,
            'visa_type' => $this->visa_type,
            'service_category' => $this->service_category,
            'agreement_amount' => $this->agreement_amount,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'profile_photo_file_id' => $this->profile_photo_file_id,
            'profile_photo' => new FileResource($this->whenLoaded('profilePhoto')),
            'assigned_supervisor_id' => $this->assigned_supervisor_id,
            'current_stage' => $this->current_stage,
            'visa_outcome' => $this->visa_outcome,
            'outcome_recorded_at' => $this->outcome_recorded_at,
            'outcome_recorded_by' => $this->outcome_recorded_by,
            'decision_file_id' => $this->decision_file_id,
            'refusal_reason' => $this->refusal_reason,
            'appeal_status' => $this->appeal_status,
            'appeal_due_at' => optional($this->appeal_due_at)->toDateString(),
            'appeal_notes' => $this->appeal_notes,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
