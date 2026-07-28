<?php

namespace Modules\Workflows\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'order' => $this->order,
            'owner_role' => $this->owner_role,
            'duration_days' => $this->duration_days,
            'requires_checklist' => (bool) $this->requires_checklist,
            'requires_acknowledgement' => (bool) $this->requires_acknowledgement,
            'requires_closure_record' => (bool) $this->requires_closure_record,
            'notification_template_id' => $this->notification_template_id,
            'escalation_rule' => $this->escalation_rule,
        ];
    }
}
