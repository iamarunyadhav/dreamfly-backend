<?php

namespace Modules\Workflows\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('workflows.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['sometimes', 'array'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.key' => ['nullable', 'string', 'max:100'],
            'steps.*.order' => ['sometimes', 'integer'],
            'steps.*.owner_role' => ['nullable', 'string', 'max:100'],
            'steps.*.duration_days' => ['nullable', 'integer', 'min:0'],
            'steps.*.requires_checklist' => ['sometimes', 'boolean'],
            'steps.*.requires_acknowledgement' => ['sometimes', 'boolean'],
            'steps.*.notification_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'steps.*.escalation_rule' => ['nullable', 'string', 'max:255'],
        ];
    }
}
