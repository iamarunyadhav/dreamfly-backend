<?php

namespace Modules\Forms\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Form extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
