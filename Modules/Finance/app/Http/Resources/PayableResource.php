<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payee' => $this->payee,
            'category' => $this->category,
            'amount' => $this->amount,
            'client_id' => $this->client_id,
            'client_name' => $this->whenLoaded('client', fn () => $this->client?->full_name),
            'due_date' => optional($this->due_date)->toDateString(),
            'notes' => $this->notes,
            'status' => $this->status,
            'is_overdue' => $this->is_overdue,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'paid_at' => optional($this->paid_at)->toDateString(),
            'paid_by' => $this->paid_by,
            'ledger_entry_id' => $this->ledger_entry_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
