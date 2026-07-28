<?php

namespace Modules\Finance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyClosingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'closing_date' => $this->closing_date,
            'opening_balance' => $this->opening_balance,
            'income_total' => $this->income_total,
            'expense_total' => $this->expense_total,
            'cash_total' => $this->cash_total,
            'bank_total' => $this->bank_total,
            'closing_balance' => $this->closing_balance,
            'counted_cash' => $this->counted_cash,
            'variance' => $this->variance,
            'status' => $this->status,
            'notes' => $this->notes,
            'closed_by' => $this->closed_by,
            'closed_at' => $this->closed_at,
            'reopened_by' => $this->reopened_by,
            'reopened_at' => $this->reopened_at,
            'reopen_reason' => $this->reopen_reason,
            'generated_file_id' => $this->generated_file_id,
            'generated_file' => $this->whenLoaded('generatedFile', fn () => $this->generatedFile ? new \Modules\Files\Http\Resources\FileResource($this->generatedFile) : null),
            'sent_to_admin_at' => $this->sent_to_admin_at,
            'sent_to_admin_by' => $this->sent_to_admin_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
