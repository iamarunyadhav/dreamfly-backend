<?php

namespace Modules\System\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'role',
        'client_id',
        'documentation_task_id',
        'type',
        'title',
        'body',
        'status',
        'read_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
