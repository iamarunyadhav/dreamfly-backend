<?php

namespace Modules\Agreements\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agreement extends Model
{
    use Auditable;

    protected $fillable = [
        'reference_no',
        'client_id',
        'common_user_id',
        'client_name',
        'client_address',
        'client_phone',
        'client_nic',
        'client_passport_no',
        'client_email',
        'visa_type',
        'country',
        'total_fee',
        'advance_paid',
        'status',
        'generated_file_id',
        'signed_file_id',
        'signed_at',
        'created_by',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function getBalanceAttribute(): int
    {
        return max(0, $this->total_fee - $this->advance_paid);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(\Modules\Clients\Models\Client::class, 'client_id');
    }

    public function commonUser(): BelongsTo
    {
        return $this->belongsTo(\Modules\CommonUsers\Models\CommonUser::class, 'common_user_id');
    }

    public function generatedFile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'generated_file_id');
    }

    public function signedFile(): BelongsTo
    {
        return $this->belongsTo(\Modules\Files\Models\File::class, 'signed_file_id');
    }
}
