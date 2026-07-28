<?php

namespace Modules\Checklists\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTemplate extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'title',
        'owner',
        'category',
        'description',
        'is_required',
        'document_required',
        'status',
        'version',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'document_required' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ChecklistTemplateVersion::class)->orderByDesc('version');
    }
}
