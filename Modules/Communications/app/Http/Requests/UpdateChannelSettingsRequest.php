<?php

namespace Modules\Communications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChannelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('communications.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'whatsapp' => ['required', 'array'],
            'whatsapp.enabled' => ['required', 'boolean'],
            'whatsapp.provider' => ['required', 'string', 'max:100'],
            'whatsapp.sender' => ['nullable', 'string', 'max:120'],
            'whatsapp.webhook_url' => ['nullable', 'url', 'max:500'],
            'whatsapp.api_key' => ['nullable', 'string', 'max:1000'],
            'whatsapp.access_token' => ['nullable', 'string', 'max:2000'],
            'whatsapp.phone_number_id' => ['nullable', 'string', 'max:64'],
            'whatsapp.business_account_id' => ['nullable', 'string', 'max:64'],
            'whatsapp.retry_behavior' => ['required', Rule::in(['manual', 'auto'])],

            'email' => ['required', 'array'],
            'email.enabled' => ['required', 'boolean'],
            'email.provider' => ['required', 'string', 'max:100'],
            'email.from_name' => ['nullable', 'string', 'max:150'],
            'email.from_email' => ['nullable', 'email', 'max:150'],
            'email.host' => ['nullable', 'string', 'max:255'],
            'email.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'email.encryption' => ['nullable', Rule::in(['none', 'tls', 'ssl'])],
            'email.username' => ['nullable', 'string', 'max:255'],
            'email.password' => ['nullable', 'string', 'max:1000'],

            'sms' => ['required', 'array'],
            'sms.enabled' => ['required', 'boolean'],
            'sms.provider' => ['required', 'string', 'max:100'],
            'sms.from_number' => ['nullable', 'string', 'max:32'],
            'sms.account_sid' => ['nullable', 'string', 'max:255'],
            'sms.auth_token' => ['nullable', 'string', 'max:1000'],
            'sms.messaging_service_sid' => ['nullable', 'string', 'max:255'],
            'sms.retry_behavior' => ['required', Rule::in(['manual', 'auto'])],
        ];
    }
}
