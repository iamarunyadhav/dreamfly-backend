<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisorReview extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'round',
        'status',
        'reviewer_id',
        'reviewed_at',
        'decision_notes',
        'sent_back_to_stage',
        'assigned_to_user_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SupervisorReviewComment::class);
    }
}
