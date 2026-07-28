<?php

namespace Modules\Finance\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyClosing extends Model
{
    use Auditable;

    protected $fillable = [
        'closing_date',
        'opening_balance',
        'income_total',
        'expense_total',
        'cash_total',
        'bank_total',
        'closing_balance',
        'counted_cash',
        'variance',
        'status',
        'notes',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'generated_file_id',
        'sent_to_admin_at',
        'sent_to_admin_by',
        'created_by',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'sent_to_admin_at' => 'datetime',
        'opening_balance' => 'integer',
        'income_total' => 'integer',
        'expense_total' => 'integer',
        'cash_total' => 'integer',
        'bank_total' => 'integer',
        'closing_balance' => 'integer',
        'counted_cash' => 'integer',
        'variance' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'daily_closing_id');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sentToAdminBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_to_admin_by');
    }

    public function generatedFile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'generated_file_id');
    }
}
