<?php

namespace Modules\Ocr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrExtractionField extends Model
{
    protected $fillable = [
        'ocr_extraction_id',
        'sort_order',
        'label',
        'value',
        'confidence',
        'is_user_edited',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'is_user_edited' => 'boolean',
        ];
    }

    public function extraction(): BelongsTo
    {
        return $this->belongsTo(OcrExtraction::class, 'ocr_extraction_id');
    }
}
