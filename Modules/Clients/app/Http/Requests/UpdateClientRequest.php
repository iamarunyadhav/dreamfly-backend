<?php

namespace Modules\Clients\Http\Requests;

use App\Support\Validation\UniqueAcrossPeople;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }

    public function rules(): array
    {
        $client = $this->route('client');
        $ignoreId = $client?->id;
        // The lead this client was converted from shares its phone/email/NIC/
        // passport by design - exclude that one paired record.
        $pairedCommonUserId = $client?->common_user_id;

        return [
            'common_user_id' => ['nullable', 'integer', 'exists:common_users,id'],
            'reference_no' => ['sometimes', 'string', 'max:255'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'passport_no' => ['nullable', 'string', 'max:50', new UniqueAcrossPeople('passport_no', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients', 'ignoreId' => $ignoreId],
            ], 'passport number')],
            'nic' => ['nullable', 'string', 'max:50', new UniqueAcrossPeople('nic', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients', 'ignoreId' => $ignoreId],
            ], 'NIC')],
            'phone' => ['nullable', 'string', 'max:30', new UniqueAcrossPeople('phone', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients', 'ignoreId' => $ignoreId],
            ], 'phone number')],
            'email' => ['nullable', 'email', 'max:255', new UniqueAcrossPeople('email', [
                ['table' => 'common_users', 'ignoreId' => $pairedCommonUserId],
                ['table' => 'clients', 'ignoreId' => $ignoreId],
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
            'visa_outcome' => ['nullable', Rule::in(['approved', 'refused', 'withdrawn', 'pending'])],
            'status' => ['sometimes', Rule::in(['active', 'on_hold', 'closed'])],
        ];
    }
}
