<?php

namespace Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertClientApplicationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('application-unit.update')
            || $this->user()?->can('application-unit.edit')
            || $this->user()?->can('application-unit.complete')
            || $this->user()?->can('application-unit.generate')
            || $this->user()?->can('clients.edit');
    }

    public function rules(): array
    {
        return [
            'form_data' => ['nullable', 'array'],
            'applicant_checklist' => ['nullable', 'array'],
            'inviter_checklist' => ['nullable', 'array'],
            'internal_checklist' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
