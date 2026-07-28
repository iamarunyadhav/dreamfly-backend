<?php

namespace Modules\Clients\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientApplicationUnit extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'form_data',
        'applicant_checklist',
        'inviter_checklist',
        'internal_checklist',
        'notes',
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
            'form_data' => 'array',
            'applicant_checklist' => 'array',
            'inviter_checklist' => 'array',
            'internal_checklist' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}

