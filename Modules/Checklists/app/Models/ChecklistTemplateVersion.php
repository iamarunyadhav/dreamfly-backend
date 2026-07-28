<?php

namespace Modules\Checklists\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateVersion extends Model
{
    protected $fillable = [
        'checklist_template_id',
        'version',
        'title',
        'owner',
        'category',
        'description',
        'is_required',
        'document_required',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'document_required' => 'boolean',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
