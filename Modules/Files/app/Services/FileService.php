<?php

namespace Modules\Files\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Files\Models\File;
use Modules\Files\Repositories\Contracts\FileRepositoryInterface;
use Modules\Folders\Models\Folder;

class FileService extends BaseService
{
    protected const DISK = 'local';

    public function __construct(FileRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    /**
     * A generic upload into any folder (e.g. from the folder browser). The
     * target folder itself carries client_id/common_user_id once it's part
     * of an owned tree, so the file inherits that ownership instead of
     * landing unfiled from the client's/lead's own document list.
     */
    public function upload(UploadedFile $uploadedFile, int $folderId, int $uploadedBy): File
    {
        $folder = Folder::find($folderId);

        $owner = ['folder_id' => $folderId];
        if ($folder?->client_id) {
            $owner['client_id'] = $folder->client_id;
        }
        if ($folder?->common_user_id) {
            $owner['common_user_id'] = $folder->common_user_id;
        }

        return $this->store($uploadedFile, $uploadedBy, $owner, "files/{$folderId}");
    }

    public function uploadForClientFolder(UploadedFile $uploadedFile, int $clientId, int $folderId, int $uploadedBy): File
    {
        return $this->store($uploadedFile, $uploadedBy, ['folder_id' => $folderId, 'client_id' => $clientId], "files/{$folderId}");
    }

    /**
     * Upload a document attached to a lead (common user). If the lead already
     * has its own folder tree, $folderId files it there too (mirroring how
     * client uploads land in a real folder) - on conversion it's re-filed
     * into the client's own tree regardless.
     */
    public function uploadForLead(UploadedFile $uploadedFile, int $commonUserId, int $uploadedBy, ?int $folderId = null): File
    {
        $owner = ['common_user_id' => $commonUserId];
        if ($folderId) {
            $owner['folder_id'] = $folderId;
        }

        return $this->store($uploadedFile, $uploadedBy, $owner, "documents/lead-{$commonUserId}");
    }

    /**
     * Replace a document with a corrected copy. The new file becomes version N+1
     * of the same chain and the old one is kept, marked superseded, so the audit
     * trail still shows what was originally submitted.
     */
    public function uploadNewVersion(File $current, UploadedFile $uploadedFile, int $uploadedBy, ?string $note = null): File
    {
        return DB::transaction(function () use ($current, $uploadedFile, $uploadedBy, $note) {
            $rootId = $current->version_root_id ?? $current->id;

            // Lock to the true latest in the chain so two concurrent re-uploads
            // cannot both claim the same version number.
            $latest = File::where(function ($query) use ($rootId) {
                $query->where('version_root_id', $rootId)->orWhere('id', $rootId);
            })->orderByDesc('version')->lockForUpdate()->first() ?? $current;

            $replacement = $this->store(
                $uploadedFile,
                $uploadedBy,
                [
                    'folder_id' => $current->folder_id,
                    'client_id' => $current->client_id,
                    'common_user_id' => $current->common_user_id,
                    'version' => $latest->version + 1,
                    'version_root_id' => $rootId,
                    'replaces_file_id' => $current->id,
                    'version_note' => $note,
                ],
                'files/'.$current->folder_id,
            );

            // A new version starts unverified - a corrected document has to be
            // checked again rather than inheriting the old file's verified flag.
            File::where(function ($query) use ($rootId) {
                $query->where('version_root_id', $rootId)->orWhere('id', $rootId);
            })
                ->whereKeyNot($replacement->id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'superseded_at' => now()]);

            return $replacement->refresh();
        });
    }

    /**
     * Every version in a file's chain, oldest first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, File>
     */
    public function versionsOf(File $file): \Illuminate\Database\Eloquent\Collection
    {
        $rootId = $file->version_root_id ?? $file->id;

        return File::where(function ($query) use ($rootId) {
            $query->where('version_root_id', $rootId)->orWhere('id', $rootId);
        })->orderBy('version')->get();
    }

    public function verify(File $file, int $verifiedBy): File
    {
        return $this->repository->update($file, [
            'verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
        ]);
    }

    /**
     * Renames the display name only - storage path/extension/disk name are
     * untouched. `original_name` (not `name`) is what FileResource/the
     * frontend actually show as the document's name.
     */
    public function rename(File $file, string $name): File
    {
        return $this->repository->update($file, ['original_name' => $name]);
    }

    private function store(UploadedFile $uploadedFile, int $uploadedBy, array $owner, string $dir): File
    {
        return DB::transaction(function () use ($uploadedFile, $uploadedBy, $owner, $dir) {
            $extension = $uploadedFile->getClientOriginalExtension();
            $storedName = Str::uuid()->toString().($extension ? ".{$extension}" : '');
            $path = $uploadedFile->storeAs($dir, $storedName, self::DISK);

            $file = $this->repository->create([
                ...$owner,
                'name' => $storedName,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'disk' => self::DISK,
                'path' => $path,
                'extension' => $extension,
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
                'uploaded_by' => $uploadedBy,
            ]);

            // A first upload is its own version root, so every file has a chain.
            if (! $file->version_root_id) {
                $file->forceFill(['version_root_id' => $file->id])->save();
            }

            return $file;
        });
    }

    public function delete(Model $model): bool
    {
        return DB::transaction(function () use ($model) {
            /** @var File $model */
            Storage::disk($model->disk)->delete($model->path);

            return $this->repository->delete($model);
        });
    }
}
