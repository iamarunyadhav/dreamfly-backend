<?php

namespace Modules\Agreements\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Agreements\Services\AgreementPackageService;

class AgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'client_id' => $this->client_id,
            'common_user_id' => $this->common_user_id,
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
            'signed_file_id' => $this->signed_file_id,
            'signed_at' => $this->signed_at,
            'generated_file' => $this->whenLoaded('generatedFile', fn () => $this->generatedFile ? new \Modules\Files\Http\Resources\FileResource($this->generatedFile) : null),
            'signed_file' => $this->whenLoaded('signedFile', fn () => $this->signedFile ? new \Modules\Files\Http\Resources\FileResource($this->signedFile) : null),
            'default_explainer_video' => app(AgreementPackageService::class)->defaultVideo(),
            'pdf_url' => route('api.agreements.pdf', $this->id),
            'created_at' => $this->created_at,
        ];
    }
}
