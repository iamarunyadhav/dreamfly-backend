<?php

namespace Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in(['internal', 'client'])],
        ];
    }
}
