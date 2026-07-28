<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerEntry extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'type',
        'category',
        'amount',
        'payment_method',
        'source',
        'payment_id',
        'description',
        'reason',
        'adjusts_entry_id',
        'is_locked',
        'daily_closing_id',
        'entry_date',
        'recorded_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'is_locked' => 'boolean',
        'amount' => 'integer',
    ];

    /** A manual entry can be edited/deleted; posted (payment/adjustment) or locked entries cannot. */
    public function isEditable(): bool
    {
        return ! $this->is_locked && $this->source === 'manual';
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Payments\Models\Payment::class, 'payment_id');
    }

    public function adjustsEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'adjusts_entry_id');
    }

    public function dailyClosing(): BelongsTo
    {
        return $this->belongsTo(DailyClosing::class, 'daily_closing_id');
    }
}
