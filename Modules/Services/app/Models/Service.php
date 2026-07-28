<?php

namespace Modules\Services\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Communications\Models\MessageTemplate;
use Modules\Forms\Models\Form;
use Modules\Workflows\Models\WorkflowTemplate;

class Service extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'description',
        'workflow_template_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistTemplate::class, 'service_checklist_template')
            ->withPivot(['is_required', 'order'])
            ->orderBy('order');
    }

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'service_form');
    }

    public function messageTemplates(): BelongsToMany
    {
        return $this->belongsToMany(MessageTemplate::class, 'service_message_template')
            ->withPivot(['purpose']);
    }
}
