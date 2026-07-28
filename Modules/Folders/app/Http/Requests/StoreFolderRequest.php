<?php

namespace Modules\Folders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('folders.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:folders,id'],
            'is_general' => ['sometimes', 'boolean'],
            'auto_create_for_clients' => ['sometimes', 'boolean'],
            'propagate_existing' => ['sometimes', 'boolean'],
            'public_download' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('name') && ! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }
}
