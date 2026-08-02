<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Files\Http\Resources\FileResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'common_user_id' => $this->common_user_id,
            'agreement_id' => $this->agreement_id,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount,
            'method' => $this->method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'status' => $this->status,
            'receipt_file_id' => $this->receipt_file_id,
            'receipt_file' => $this->whenLoaded('receiptFile', fn () => $this->receiptFile ? new FileResource($this->receiptFile) : null),
            'verified_at' => $this->verified_at,
            'verified_by' => $this->verified_by,
            'verification_notes' => $this->verification_notes,
            'is_overpayment' => (bool) $this->is_overpayment,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
