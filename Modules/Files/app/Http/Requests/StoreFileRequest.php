<?php

namespace Modules\Files\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('files.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['required', 'exists:folders,id'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf,mp4,docx'],
        ];
    }
}
