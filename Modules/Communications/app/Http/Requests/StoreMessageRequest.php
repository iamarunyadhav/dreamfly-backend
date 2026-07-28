<?php

namespace Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('communications.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'workflow_step' => ['nullable', 'string', 'max:80'],
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
