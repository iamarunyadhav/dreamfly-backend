<?php

namespace Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('contacts.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
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
