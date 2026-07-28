<?php

namespace Modules\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('services.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['visit_visa', 'student_visa', 'other'])],
            'description' => ['nullable', 'string'],
            'workflow_template_id' => ['nullable', 'integer', 'exists:workflow_templates,id'],
            'is_active' => ['sometimes', 'boolean'],
            'checklist_templates' => ['sometimes', 'array'],
            'checklist_templates.*.id' => ['required', 'integer', 'exists:checklist_templates,id'],
            'checklist_templates.*.is_required' => ['sometimes', 'boolean'],
            'checklist_templates.*.order' => ['sometimes', 'integer', 'min:0'],
            'form_ids' => ['sometimes', 'array'],
            'form_ids.*' => ['integer', 'exists:forms,id'],
            'message_templates' => ['sometimes', 'array'],
            'message_templates.*.id' => ['required', 'integer', 'exists:message_templates,id'],
            'message_templates.*.purpose' => ['nullable', 'string', 'max:100'],
        ];
    }
}
