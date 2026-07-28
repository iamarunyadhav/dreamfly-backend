<?php

namespace Modules\Invoices\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->description,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'category' => $this->category,
            'tax' => $this->tax,
        ];
    }
}
