<?php

namespace Modules\Ocr\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOcrExtractionFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ocr.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'value' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
