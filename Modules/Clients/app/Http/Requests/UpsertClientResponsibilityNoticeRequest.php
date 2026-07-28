<?php

namespace Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertClientResponsibilityNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            // Only the operator-added additions are editable - the fixed legal
            // clauses live in the Blade template and are never client-editable.
            'content' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
