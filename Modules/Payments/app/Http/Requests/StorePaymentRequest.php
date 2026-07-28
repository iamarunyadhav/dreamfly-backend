<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'agreement_id' => ['nullable', 'integer', 'exists:agreements,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'integer', 'min:0'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['required', 'date'],
            'receipt_file_id' => ['nullable', 'integer', 'exists:files,id'],
            'allow_overpayment' => ['sometimes', 'boolean'],
        ];
    }
}
