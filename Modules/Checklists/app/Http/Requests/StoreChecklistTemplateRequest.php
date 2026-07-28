<?php

namespace Modules\Checklists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checklists.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
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
