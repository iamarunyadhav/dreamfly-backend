<?php

namespace Modules\Workflows\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clients\Models\Client;

class CaseStep extends Model
{
    use Auditable;

    protected $fillable = [
        'client_id',
        'workflow_template_id',
        'workflow_step_id',
        'key',
        'name',
        'order',
        'owner_role',
        'assigned_user_id',
        'status',
        'duration_days',
        'requires_checklist',
        'requires_acknowledgement',
        'requires_closure_record',
        'started_at',
        'due_at',
        'completed_at',
        'completed_by',
        'hold_reason',
        'held_at',
        'notes',
    ];

    protected $casts = [
        'requires_checklist' => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'requires_closure_record' => 'boolean',
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'held_at' => 'datetime',
    ];

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null
            && ! in_array($this->status, ['completed', 'skipped', 'on_hold'], true)
            && $this->due_at->isPast();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
