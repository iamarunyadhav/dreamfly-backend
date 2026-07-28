<?php

namespace Modules\CommonUsers\Http\Requests;

use App\Support\Validation\UniqueAcrossPeople;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommonUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('common-users.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30', new UniqueAcrossPeople('phone', [
                ['table' => 'common_users'],
                ['table' => 'clients'],
            ], 'phone number')],
            'nic' => ['nullable', 'string', 'max:50', new UniqueAcrossPeople('nic', [
                ['table' => 'common_users'],
                ['table' => 'clients'],
            ], 'NIC')],
            'passport_no' => ['nullable', 'string', 'max:50', new UniqueAcrossPeople('passport_no', [
                ['table' => 'common_users'],
                ['table' => 'clients'],
            ], 'passport number')],
            'email' => ['nullable', 'email', 'max:255', new UniqueAcrossPeople('email', [
                ['table' => 'common_users'],
                ['table' => 'clients'],
            ], 'email address')],
            'country' => ['nullable', 'string', 'max:100'],
            'native_country' => ['nullable', 'string', 'max:100'],
            'visa_type' => ['nullable', 'string', 'max:100'],
            'service_category' => ['sometimes', Rule::in(['visit_visa', 'student_visa', 'other'])],
            'agreement_amount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(['unpaid', 'partially_paid', 'fully_paid', 'converted'])],
        ];
    }
}
