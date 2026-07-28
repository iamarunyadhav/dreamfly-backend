<?php

namespace Modules\Clients\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentationTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documentation-unit.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_role' => ['nullable', 'string', 'max:120'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['nullable', Rule::in(['pending', 'assigned', 'in_progress', 'waiting_client', 'waiting_third_party', 'overdue', 'completed', 'on_hold', 'cancelled'])],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'hold_reason' => ['nullable', 'string'],
            'reminder_at' => ['nullable', 'date'],
            'escalation_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
