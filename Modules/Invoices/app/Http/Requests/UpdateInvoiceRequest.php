<?php

namespace Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoices.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'reference_no' => ['sometimes', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'total_service_fee' => ['sometimes', 'integer', 'min:0'],
            'advance_paid' => ['sometimes', 'integer', 'min:0'],
            'application_fee' => ['sometimes', 'integer', 'min:0'],
            'vfs_fee' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'issued', 'sent', 'paid', 'partial', 'partially_paid', 'overdue', 'cancelled', 'waived', 'refunded'])],
            'items' => ['sometimes', 'array'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.amount' => ['nullable', 'integer', 'min:0'],
            'items.*.category' => ['nullable', 'string', 'max:100'],
            'items.*.tax' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
