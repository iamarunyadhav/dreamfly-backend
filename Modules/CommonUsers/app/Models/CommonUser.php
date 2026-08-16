<?php

namespace Modules\CommonUsers\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Files\Models\File;
use Modules\Agreements\Models\Agreement;
use Modules\Payments\Models\AdditionalCharge;
use Modules\Payments\Models\Payment;

class CommonUser extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'full_name',
        'address',
        'phone',
        'nic',
        'passport_no',
        'email',
        'country',
        'native_country',
        'visa_type',
        'service_category',
        'agreement_amount',
        'paid_amount',
        'profile_photo_file_id',
        'status',
        'created_by',
    ];

    public function getBalanceAttribute(): int
    {
        return max(0, $this->agreement_amount + $this->additional_charges_total - $this->paid_amount);
    }

    public function getAdditionalChargesTotalAttribute(): int
    {
        // Use the eager-loaded sum (via withSum) when the caller provided one, to
        // avoid an extra query per row on list endpoints; fall back to a live sum.
        if (array_key_exists('additional_charges_sum_amount', $this->attributes)) {
            return (int) $this->attributes['additional_charges_sum_amount'];
        }

        return (int) $this->additionalCharges()->sum('amount');
    }

    public function additionalCharges(): HasMany
    {
        return $this->hasMany(AdditionalCharge::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function profilePhoto(): BelongsTo
    {
        return $this->belongsTo(File::class, 'profile_photo_file_id');
    }

    public function verifiedDocuments(): HasMany
    {
        return $this->hasMany(File::class)->where('verified', true);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
