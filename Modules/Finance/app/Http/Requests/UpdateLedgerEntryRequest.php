<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLedgerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['income', 'expense'])],
            'category' => ['sometimes', 'string', 'max:100'],
            'amount' => ['sometimes', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['sometimes', 'date'],
        ];
    }
}
