<?php

namespace Modules\Ocr\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Files\Models\File;

class OcrExtraction extends Model
{
    protected $fillable = [
        'file_id',
        'status',
        'provider',
        'raw_response',
        'error_message',
        'requested_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(OcrExtractionField::class)->orderBy('sort_order');
    }
}
