<?php

namespace Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(['client', 'inviter', 'staff', 'supplier', 'general'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
