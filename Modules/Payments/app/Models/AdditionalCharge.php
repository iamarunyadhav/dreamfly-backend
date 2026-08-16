<?php

namespace Modules\Payments\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;

class AdditionalCharge extends Model
{
    use Auditable;

    protected $fillable = [
        'common_user_id',
        'client_id',
        'description',
        'amount',
        'created_by',
    ];

    public function commonUser(): BelongsTo
    {
        return $this->belongsTo(CommonUser::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
