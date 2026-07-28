<?php

namespace Modules\CommonUsers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Uploading a lead's documents is part of managing that lead.
        return $this->user()?->can('common-users.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf,mp4,docx'],
        ];
    }
}
