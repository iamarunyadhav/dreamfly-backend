<?php

namespace Modules\Roles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.edit') ?? false;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
