<?php

namespace Modules\Services\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'workflow_template_id' => $this->workflow_template_id,
            'workflow_template' => $this->whenLoaded('workflowTemplate', fn () => $this->workflowTemplate ? [
                'id' => $this->workflowTemplate->id,
                'name' => $this->workflowTemplate->name,
            ] : null),
            'is_active' => (bool) $this->is_active,
            'checklist_templates' => $this->whenLoaded('checklistTemplates', fn () => $this->checklistTemplates->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'category' => $t->category,
                'is_required' => (bool) $t->pivot->is_required,
                'order' => (int) $t->pivot->order,
            ])->values()),
            'forms' => $this->whenLoaded('forms', fn () => $this->forms->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
            ])->values()),
            'message_templates' => $this->whenLoaded('messageTemplates', fn () => $this->messageTemplates->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'channel' => $m->channel,
                'purpose' => $m->pivot->purpose,
            ])->values()),
            'checklist_templates_count' => $this->whenCounted('checklistTemplates'),
            'forms_count' => $this->whenCounted('forms'),
            'message_templates_count' => $this->whenCounted('messageTemplates'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
