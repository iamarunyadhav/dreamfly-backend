<?php

namespace Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('communications.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'message_template_id' => ['nullable', 'integer', 'exists:message_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', 'string', 'max:100'],
            'conditions' => ['nullable', 'array'],
            'recipient_rules' => ['nullable', 'array'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['whatsapp', 'email', 'sms', 'internal'])],
            'delay_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'repeat_rule' => ['nullable', 'string', 'max:255'],
            'escalation_rule' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
