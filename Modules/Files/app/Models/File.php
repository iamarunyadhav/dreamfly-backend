<?php

namespace Modules\Files\Models;

use App\Models\User;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Folders\Models\Folder;

class File extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'folder_id',
        'common_user_id',
        'client_id',
        'name',
        'original_name',
        'disk',
        'path',
        'extension',
        'mime_type',
        'size',
        'version',
        'version_root_id',
        'replaces_file_id',
        'is_current',
        'version_note',
        'superseded_at',
        'uploaded_by',
        'verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_current' => 'boolean',
        'superseded_at' => 'datetime',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** The file this one replaced, if it is a corrected re-upload. */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(File::class, 'replaces_file_id');
    }

    /** Every version in this file's chain, oldest first. */
    public function versions(): HasMany
    {
        return $this->hasMany(File::class, 'version_root_id', 'version_root_id')->orderBy('version');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
