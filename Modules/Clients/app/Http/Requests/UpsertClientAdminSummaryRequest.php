<?php

namespace Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertClientAdminSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'client_share_notes' => ['nullable', 'string'],
            'form_data' => ['nullable', 'array'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'application_staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'deadline_at' => ['nullable', 'date'],
        ];
    }
}
