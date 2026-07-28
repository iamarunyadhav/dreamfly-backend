<?php

namespace Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertClientCaseClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'handover_checklist' => ['nullable', 'array'],
            'handover_checklist.*.title' => ['required_with:handover_checklist', 'string', 'max:255'],
            'handover_checklist.*.returned' => ['sometimes', 'boolean'],
            'handover_checklist.*.note' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
