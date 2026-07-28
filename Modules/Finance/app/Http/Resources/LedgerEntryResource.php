<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'source' => $this->source,
            'payment_id' => $this->payment_id,
            'description' => $this->description,
            'reason' => $this->reason,
            'adjusts_entry_id' => $this->adjusts_entry_id,
            'is_locked' => (bool) $this->is_locked,
            'daily_closing_id' => $this->daily_closing_id,
            'editable' => $this->isEditable(),
            'entry_date' => $this->entry_date,
            'recorded_by' => $this->recorded_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
