<?php

namespace Modules\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Agreements\Models\Agreement;

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
            'common_user_id' => ['nullable', 'integer', 'exists:common_users,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'client_nic' => ['required_without:client_passport_no', 'nullable', 'string', 'max:30'],
            'client_passport_no' => ['required_without:client_nic', 'nullable', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'visa_type' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:100'],
            'total_fee' => ['required', 'integer', 'min:0'],
            'advance_paid' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_nic' => $this->blankToNull($this->input('client_nic')),
            'client_passport_no' => $this->blankToNull($this->input('client_passport_no')),
            'client_phone' => $this->blankToNull($this->input('client_phone')),
            'client_email' => $this->blankToNull($this->input('client_email')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $commonUserId = $this->integer('common_user_id') ?: null;
            $clientId = $this->integer('client_id') ?: null;
            $nic = $this->blankToNull($this->input('client_nic'));
            $passport = $this->blankToNull($this->input('client_passport_no'));

            if ($commonUserId && Agreement::where('common_user_id', $commonUserId)->exists()) {
                $validator->errors()->add('common_user_id', 'This common user already has an agreement.');
            }

            if ($clientId && Agreement::where('client_id', $clientId)->exists()) {
                $validator->errors()->add('client_id', 'This client already has an agreement.');
            }

            if ($nic && Agreement::whereRaw('LOWER(client_nic) = ?', [mb_strtolower($nic)])->exists()) {
                $validator->errors()->add('client_nic', 'An agreement already exists for this NIC.');
            }

            if ($passport && Agreement::whereRaw('LOWER(client_passport_no) = ?', [mb_strtolower($passport)])->exists()) {
                $validator->errors()->add('client_passport_no', 'An agreement already exists for this passport number.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'client_nic.required_without' => 'Enter either the NIC or passport number before creating an agreement.',
            'client_passport_no.required_without' => 'Enter either the NIC or passport number before creating an agreement.',
        ];
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
