<?php

namespace Modules\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agreements.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_nic' => ['nullable', 'string', 'max:30'],
            'client_passport_no' => ['nullable', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'visa_type' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:100'],
            'total_fee' => ['required', 'integer', 'min:0'],
            'advance_paid' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
