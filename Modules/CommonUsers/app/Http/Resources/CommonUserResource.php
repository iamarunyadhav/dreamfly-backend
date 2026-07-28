<?php

namespace Modules\CommonUsers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'balance' => $this->balance,
            'status' => $this->status,
            'documents_count' => $this->whenCounted('documents'),
            'verified_documents_count' => $this->whenCounted('verifiedDocuments'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
