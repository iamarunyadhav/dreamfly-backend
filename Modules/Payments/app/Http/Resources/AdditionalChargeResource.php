<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'common_user_id' => $this->common_user_id,
            'client_id' => $this->client_id,
            'description' => $this->description,
            'amount' => $this->amount,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
