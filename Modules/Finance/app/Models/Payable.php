<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Models\Client;

class Payable extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'payee',
        'category',
        'amount',
        'client_id',
        'due_date',
        'notes',
        'status',
        'payment_method',
        'reference',
        'paid_at',
        'paid_by',
        'ledger_entry_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date !== null && $this->due_date->isPast();
    }
}
