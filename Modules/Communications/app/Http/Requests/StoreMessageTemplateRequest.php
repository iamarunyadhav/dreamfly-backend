<?php

namespace Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('communications.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
