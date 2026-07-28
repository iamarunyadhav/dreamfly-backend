<?php

namespace Modules\Folders\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Files\Models\File;

class Folder extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'client_id',
        'common_user_id',
        'template_id',
        'scope',
        'is_general',
        'auto_create_for_clients',
        'applies_to',
        'is_active',
        'public_download',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_general' => 'boolean',
            'auto_create_for_clients' => 'boolean',
            'applies_to' => 'array',
            'is_active' => 'boolean',
            'public_download' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commonUser(): BelongsTo
    {
        return $this->belongsTo(CommonUser::class);
    }
}
