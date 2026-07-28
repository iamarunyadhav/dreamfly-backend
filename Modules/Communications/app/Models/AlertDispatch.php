<?php

namespace Modules\Communications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertDispatch extends Model
{
    protected $fillable = [
        'alert_template_id',
        'trigger',
        'client_id',
        'dedupe_key',
        'context',
        'due_at',
        'status',
        'sent_at',
        'failure_reason',
        'recipients_notified',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'due_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AlertTemplate::class, 'alert_template_id');
    }
}
