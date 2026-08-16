<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Files\Models\File;

class DocumentationTask extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'assigned_user_id',
        'assigned_role',
        'supervisor_id',
        'priority',
        'status',
        'start_at',
        'due_at',
        'completed_at',
        'hold_reason',
        'reminder_at',
        'reminded_at',
        'escalation_at',
        'escalated_at',
        'notes',
        'file_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_at' => 'datetime',
            'reminded_at' => 'datetime',
            'escalation_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
