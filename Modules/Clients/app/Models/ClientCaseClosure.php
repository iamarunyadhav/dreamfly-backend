<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientCaseClosure extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'handover_checklist',
        'archived',
        'archived_at',
        'archived_by',
        'notes',
        'completed_at',
        'completed_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'handover_checklist' => 'array',
            'archived' => 'boolean',
            'archived_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Every handover row must be marked returned before the case can close. */
    public function getAllDocumentsReturnedAttribute(): bool
    {
        $rows = $this->handover_checklist ?? [];

        return count($rows) > 0 && collect($rows)->every(fn (array $row) => (bool) ($row['returned'] ?? false));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
