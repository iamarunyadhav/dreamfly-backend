<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.edit') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
