<?php

namespace Modules\Clients\Http\Requests;

use App\Support\Validation\UniqueAcrossPeople;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.create') ?? false;
    }

    public function rules(): array
    {
        // A client created directly from a given lead legitimately shares that
        // lead's phone/email/NIC/passport - exclude that specific lead record.
        $pairedCommonUserId = $this->input('common_user_id') ? (int) $this->input('common_user_id') : null;

        return [
            'common_user_id' => ['nullable', 'integer', 'exists:common_users,id'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'passport_no' => ['nullable', 'string', 'max:50', new UniqueAcrossPeople('passport_no', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients'],
            ], 'passport number')],
            'nic' => ['nullable', 'string', 'max:50', new UniqueAcrossPeople('nic', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients'],
            ], 'NIC')],
            'phone' => ['nullable', 'string', 'max:30', new UniqueAcrossPeople('phone', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients'],
            ], 'phone number')],
            'email' => ['nullable', 'email', 'max:255', new UniqueAcrossPeople('email', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients'],
            ], 'email address')],
            'country' => ['nullable', 'string', 'max:100'],
            'native_country' => ['nullable', 'string', 'max:100'],
            'visa_type' => ['nullable', 'string', 'max:100'],
            'agreement_amount' => ['nullable', 'integer', 'min:0'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'assigned_supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'current_stage' => ['sometimes', Rule::in([
                'admin_summary', 'application_unit', 'documentation_unit', 'supervisor_review',
                'invoice', 'submission', 'visa_result', 'closed',
            ])],
            'status' => ['sometimes', Rule::in(['active', 'on_hold', 'closed'])],
        ];
    }
}
