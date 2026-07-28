<?php

namespace Modules\Clients\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Files\Models\File;

class VisaSubmission extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'submitted_at',
        'lodgement_reference',
        'tracking_reference',
        'submitted_to',
        'submission_method',
        'appointment_at',
        'appointment_location',
        'biometrics_at',
        'expected_decision_at',
        'receipt_file_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'date',
            'appointment_at' => 'datetime',
            'biometrics_at' => 'date',
            'expected_decision_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function receiptFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'receipt_file_id');
    }
}
