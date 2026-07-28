<?php

namespace Modules\Folders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('folders.edit') ?? false;
    }

    public function rules(): array
    {
        $folderId = $this->route('folder')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', Rule::exists('folders', 'id')->whereNot('id', $folderId)],
            'is_general' => ['sometimes', 'boolean'],
            'auto_create_for_clients' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'public_download' => ['sometimes', 'boolean'],
        ];
    }
}
