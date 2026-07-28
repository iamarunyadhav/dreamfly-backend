<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may edit their own profile - no admin permission required.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            // A password change requires proving knowledge of the current one.
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
