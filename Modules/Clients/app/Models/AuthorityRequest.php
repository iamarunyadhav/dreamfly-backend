<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Files\Models\File;

class AuthorityRequest extends Model
{
    use Auditable, SoftDeletes;

    /** Statuses that mean the request no longer needs a response. */
    public const RESOLVED_STATUSES = ['responded', 'closed', 'cancelled'];

    protected $fillable = [
        'client_id',
        'authority',
        'request_type',
        'title',
        'description',
        'received_at',
        'due_at',
        'status',
        'assigned_user_id',
        'responded_at',
        'response_notes',
        'response_file_id',
        'reminded_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'due_at' => 'date',
            'responded_at' => 'date',
            'reminded_at' => 'datetime',
        ];
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_at !== null
            && ! in_array($this->status, self::RESOLVED_STATUSES, true)
            && $this->due_at->isPast();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->due_at === null || in_array($this->status, self::RESOLVED_STATUSES, true)) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_at->startOfDay(), false);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function responseFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'response_file_id');
    }
}
