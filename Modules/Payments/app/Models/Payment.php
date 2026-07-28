<?php

namespace Modules\Payments\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Agreements\Models\Agreement;
use Modules\Clients\Models\Client;
use Modules\Invoices\Models\Invoice;

class Payment extends Model
{
    use Auditable;

    protected $fillable = [
        'client_id',
        'agreement_id',
        'invoice_id',
        'amount',
        'method',
        'reference',
        'notes',
        'paid_at',
        'status',
        'receipt_file_id',
        'verified_at',
        'verified_by',
        'verification_notes',
        'is_overpayment',
        'recorded_by',
    ];

    protected $casts = [
        'paid_at' => 'date',
        'verified_at' => 'datetime',
        'is_overpayment' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function receiptFile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'receipt_file_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
