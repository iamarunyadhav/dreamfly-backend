<?php

namespace Modules\Clients\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisorReviewComment extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'supervisor_review_id',
        'client_id',
        'user_id',
        'body',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(SupervisorReview::class, 'supervisor_review_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
