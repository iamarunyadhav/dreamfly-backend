<?php

namespace Modules\Communications\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertTemplate extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'message_template_id',
        'name',
        'trigger',
        'conditions',
        'recipient_rules',
        'channels',
        'delay_minutes',
        'repeat_rule',
        'escalation_rule',
        'is_enabled',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'recipient_rules' => 'array',
            'channels' => 'array',
            'delay_minutes' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
