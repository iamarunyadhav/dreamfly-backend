<?php

namespace Modules\Folders\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use ZipArchive;

/**
 * Upload Team's "Compress folder" action: bundles every current file under a
 * folder (recursively) into one zip. Images over 5MB are re-encoded first
 * (resize/quality-tuned, kept as close to lossless as the size budget allows)
 * since there is no Ghostscript/Imagick on this server to shrink PDFs or other
 * file types - those go into the zip unchanged.
 */
class FolderCompressionService
{
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    public function compress(Folder $folder, int $userId): File
    {
        $files = File::whereIn('folder_id', $this->descendantFolderIds($folder))
            ->where('is_current', true)
            ->get();

        $zipRelativePath = 'generated/folder-zips/'.Str::slug($folder->name).'-'.now()->format('YmdHis').'.zip';
        $zipAbsolutePath = Storage::disk('local')->path($zipRelativePath);
        Storage::disk('local')->makeDirectory('generated/folder-zips');

        $zip = new ZipArchive();
        $zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $usedNames = [];
        $tempFiles = [];

        foreach ($files as $file) {
            $sourcePath = Storage::disk($file->disk)->path($file->path);
            if (! is_file($sourcePath)) {
                continue;
            }

            $entryPath = $sourcePath;
            if (in_array($file->mime_type, self::IMAGE_MIME_TYPES, true) && filesize($sourcePath) > self::MAX_IMAGE_BYTES) {
                $compressed = $this->compressImage($sourcePath, $file->mime_type);
                if ($compressed) {
                    $entryPath = $compressed;
                    $tempFiles[] = $compressed;
                }
            }

            $zip->addFile($entryPath, $this->uniqueEntryName($file->original_name, $usedNames));
        }

        $zip->close();
        foreach ($tempFiles as $tempFile) {
            @unlink($tempFile);
        }

        $zipFile = File::create([
            'folder_id' => $folder->id,
            'client_id' => $folder->client_id,
            'common_user_id' => $folder->common_user_id,
            'name' => basename($zipRelativePath),
            'original_name' => $folder->name.' - Compressed.zip',
            'disk' => 'local',
            'path' => $zipRelativePath,
            'extension' => 'zip',
            'mime_type' => 'application/zip',
            'size' => is_file($zipAbsolutePath) ? filesize($zipAbsolutePath) : 0,
            'uploaded_by' => $userId,
        ]);

        return $zipFile->refresh();
    }

    /** @return int[] */
    private function descendantFolderIds(Folder $folder): array
    {
        $ids = [$folder->id];
        foreach ($folder->children as $child) {
            $ids = array_merge($ids, $this->descendantFolderIds($child));
        }

        return $ids;
    }

    /** Re-encodes an oversized image, backing off quality then dimensions until it fits under the budget. */
    private function compressImage(string $path, string $mimeType): ?string
    {
        $image = match ($mimeType) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };

        if (! $image) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'docprep-compress').'.jpg';

        for ($quality = 85; $quality >= 55; $quality -= 10) {
            imagejpeg($image, $tempPath, $quality);
            if (filesize($tempPath) <= self::MAX_IMAGE_BYTES) {
                imagedestroy($image);

                return $tempPath;
            }
        }

        // Quality alone wasn't enough - step dimensions down as a last resort.
        $width = imagesx($image);
        $height = imagesy($image);
        for ($i = 0; $i < 5; $i++) {
            $width = (int) ($width * 0.8);
            $height = (int) ($height * 0.8);
            $resized = imagescale($image, max($width, 1), max($height, 1));
            imagejpeg($resized, $tempPath, 65);
            imagedestroy($resized);

            if (filesize($tempPath) <= self::MAX_IMAGE_BYTES) {
                break;
            }
        }

        imagedestroy($image);

        return $tempPath;
    }

    /** @param string[] $usedNames */
    private function uniqueEntryName(string $name, array &$usedNames): string
    {
        $candidate = $name;
        $suffix = 1;
        while (in_array($candidate, $usedNames, true)) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $candidate = $extension ? "{$base} ({$suffix}).{$extension}" : "{$base} ({$suffix})";
            $suffix++;
        }

        $usedNames[] = $candidate;

        return $candidate;
    }
}
