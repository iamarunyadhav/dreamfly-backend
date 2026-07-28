<?php

namespace Modules\Agreements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'client_id' => $this->client_id,
            'client_name' => $this->client_name,
            'client_address' => $this->client_address,
            'client_phone' => $this->client_phone,
            'client_nic' => $this->client_nic,
            'client_passport_no' => $this->client_passport_no,
            'client_email' => $this->client_email,
            'visa_type' => $this->visa_type,
            'country' => $this->country,
            'total_fee' => $this->total_fee,
            'advance_paid' => $this->advance_paid,
            'balance' => $this->balance,
            'status' => $this->status,
            'generated_file_id' => $this->generated_file_id,
            'generated_file' => $this->whenLoaded('generatedFile', fn () => $this->generatedFile ? new \Modules\Files\Http\Resources\FileResource($this->generatedFile) : null),
            'pdf_url' => route('api.agreements.pdf', $this->id),
            'created_at' => $this->created_at,
        ];
    }
}
