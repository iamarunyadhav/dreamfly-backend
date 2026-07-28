<?php

namespace Modules\Invoices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'reference_no' => $this->reference_no,
            'issue_date' => $this->issue_date,
            'due_date' => $this->due_date,
            'total_service_fee' => $this->total_service_fee,
            'advance_paid' => $this->advance_paid,
            'application_fee' => $this->application_fee,
            'vfs_fee' => $this->vfs_fee,
            'items_total' => $this->items_total,
            'total_payable' => $this->total_payable,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'notes' => $this->notes,
            'status' => $this->status,
            'generated_file_id' => $this->generated_file_id,
            'generated_file' => $this->whenLoaded('generatedFile', fn () => new \Modules\Files\Http\Resources\FileResource($this->generatedFile)),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
