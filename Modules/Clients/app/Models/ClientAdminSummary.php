<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientAdminSummary extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'summary',
        'internal_notes',
        'client_share_notes',
        'form_data',
        'supervisor_id',
        'application_staff_id',
        'deadline_at',
        'status',
        'started_at',
        'completed_at',
        'completed_by',
        'generated_file_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'form_data' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function applicationStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'application_staff_id');
    }
}
