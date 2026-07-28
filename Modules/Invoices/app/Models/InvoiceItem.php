<?php

namespace Modules\Invoices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'category',
        'tax',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
