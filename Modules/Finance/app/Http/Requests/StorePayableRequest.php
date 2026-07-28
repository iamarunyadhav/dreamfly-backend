<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'payee' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in([
                'vfs_fee', 'embassy_fee', 'agent_commission', 'rent', 'utility', 'staff_advance', 'other',
            ])],
            'amount' => ['required', 'integer', 'min:1'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
