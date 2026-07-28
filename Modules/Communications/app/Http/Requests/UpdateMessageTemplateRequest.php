<?php

namespace Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('communications.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', Rule::in(['whatsapp', 'email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
