<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientResponsibilityNotice extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'content',
        'status',
        'generated_file_id',
        'shared_at',
        'acknowledged',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledgement_method',
        'acknowledgement_note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
            'acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
