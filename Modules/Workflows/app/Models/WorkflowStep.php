<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_template_id',
        'name',
        'key',
        'order',
        'owner_role',
        'duration_days',
        'requires_checklist',
        'requires_acknowledgement',
        'requires_closure_record',
        'notification_template_id',
        'escalation_rule',
    ];

    protected $casts = [
        'requires_checklist' => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'requires_closure_record' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }
}
