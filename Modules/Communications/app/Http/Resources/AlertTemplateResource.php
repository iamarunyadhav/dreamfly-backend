<?php

namespace Modules\Communications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_template_id' => $this->message_template_id,
            'message_template_name' => $this->whenLoaded('messageTemplate', fn () => $this->messageTemplate?->name),
            'name' => $this->name,
            'trigger' => $this->trigger,
            'conditions' => $this->conditions,
            'recipient_rules' => $this->recipient_rules,
            'channels' => $this->channels,
            'delay_minutes' => $this->delay_minutes,
            'repeat_rule' => $this->repeat_rule,
            'escalation_rule' => $this->escalation_rule,
            'is_enabled' => $this->is_enabled,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
