<?php

namespace Modules\Invoices\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Models\Client;

class Invoice extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'reference_no',
        'issue_date',
        'due_date',
        'total_service_fee',
        'advance_paid',
        'application_fee',
        'vfs_fee',
        'notes',
        'status',
        'generated_file_id',
        'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    public function getTotalPayableAttribute(): int
    {
        return max(0, $this->total_service_fee - $this->advance_paid + $this->application_fee + $this->vfs_fee + $this->items_total);
    }

    public function getItemsTotalAttribute(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum(fn (InvoiceItem $item) => $item->amount + $item->tax);
        }

        return (int) $this->items()->selectRaw('coalesce(sum(amount + tax), 0) as total')->value('total');
    }

    public function getPaidAmountAttribute(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum('amount');
        }

        return (int) $this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): int
    {
        return max(0, $this->total_payable - $this->paid_amount);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\Modules\Payments\Models\Payment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generatedFile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'generated_file_id');
    }
}
