<?php

namespace Modules\Clients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientCaseClosureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'handover_checklist' => $this->handover_checklist ?? [],
            'all_documents_returned' => $this->all_documents_returned,
            'archived' => (bool) $this->archived,
            'archived_at' => $this->archived_at,
            'archived_by' => $this->archived_by,
            'notes' => $this->notes,
            'completed_at' => $this->completed_at,
            'completed_by' => $this->completed_by,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
