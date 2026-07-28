<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank', 'online', 'other'])],
            'description' => ['nullable', 'string'],
            'entry_date' => ['required', 'date'],
        ];
    }
}
