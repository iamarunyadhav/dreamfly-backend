<?php

namespace Modules\Communications\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use Auditable;

    protected $fillable = [
        'message_template_id',
        'client_id',
        'common_user_id',
        'workflow_step',
        'channel',
        'provider',
        'provider_message_id',
        'recipient',
        'subject',
        'body',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
        'retry_count',
        'webhook_payload',
        'status_history',
        'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'webhook_payload' => 'array',
        'status_history' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
