<?php

namespace Modules\CommonUsers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Http\Resources\FileResource;

class CommonUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'address' => $this->address,
            'phone' => $this->phone,
            'nic' => $this->nic,
            'passport_no' => $this->passport_no,
            'email' => $this->email,
            'country' => $this->country,
            'native_country' => $this->native_country,
            'visa_type' => $this->visa_type,
            'service_category' => $this->service_category,
            'agreement_amount' => $this->agreement_amount,
            'paid_amount' => $this->paid_amount,
            'additional_charges_total' => $this->additional_charges_total,
            'balance' => $this->balance,
            'profile_photo_file_id' => $this->profile_photo_file_id,
            'profile_photo' => new FileResource($this->whenLoaded('profilePhoto')),
            'status' => $this->status,
            'documents_count' => $this->whenCounted('documents'),
            'verified_documents_count' => $this->whenCounted('verifiedDocuments'),
            'has_agreement' => (int) ($this->agreements_count ?? 0) > 0,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
