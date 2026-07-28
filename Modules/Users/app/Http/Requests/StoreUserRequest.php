<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
