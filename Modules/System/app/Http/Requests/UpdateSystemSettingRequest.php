<?php

namespace Modules\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('system.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
        ];
    }
}
