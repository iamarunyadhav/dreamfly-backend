<?php

namespace Modules\Ocr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOcrSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ocr.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'max_pdf_pages' => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }
}
