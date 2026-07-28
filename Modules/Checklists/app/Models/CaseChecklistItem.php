<?php

namespace Modules\Checklists\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientApplicationUnit;
use Modules\Files\Models\File;

class CaseChecklistItem extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'client_id',
        'application_unit_id',
        'owner',
        'source_index',
        'title',
        'status',
        'is_required',
        'document_required',
        'linked_file_id',
        'note',
        'rejection_reason',
        'completed_at',
        'verified_at',
        'verified_by',
        'rejected_at',
        'rejected_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'document_required' => 'boolean',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function applicationUnit(): BelongsTo
    {
        return $this->belongsTo(ClientApplicationUnit::class, 'application_unit_id');
    }

    public function linkedFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'linked_file_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
