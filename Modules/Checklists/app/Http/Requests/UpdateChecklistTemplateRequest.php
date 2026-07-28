<?php

namespace Modules\Checklists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checklists.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'owner' => ['sometimes', Rule::in(['applicant', 'inviter', 'internal'])],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'document_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
