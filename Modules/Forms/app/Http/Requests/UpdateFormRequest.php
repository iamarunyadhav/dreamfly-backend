<?php

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forms.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'fields' => ['sometimes', 'array'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', 'max:50'],
            'fields.*.is_required' => ['sometimes', 'boolean'],
            'fields.*.order' => ['sometimes', 'integer'],
            'fields.*.options' => ['sometimes', 'array'],
        ];
    }
}
