<?php

namespace Modules\Agreements\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Agreements\Models\Agreement;
use Modules\Clients\Models\Client;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;

/**
 * Renders the Tamil service agreement to PDF and files it as a real File record
 * in a chosen folder, so the generated document lives in the client's folder
 * tree instead of only streaming on demand.
 */
class AgreementDocumentService
{
    public function __construct(protected AgreementPdfService $pdf, protected FolderService $folders)
    {
    }

    public function generate(Agreement $agreement, ?int $folderId, ?string $fileName, int $userId): File
    {
        $folder = $folderId
            ? Folder::findOrFail($folderId)
            : $this->agreementFolder(
                $agreement->client_id ? Client::find($agreement->client_id) : null,
                $agreement->common_user_id ? CommonUser::find($agreement->common_user_id) : null,
                $userId,
            );

        $displayName = $this->normalizeFileName($fileName, $agreement);
        $storedName = (Str::slug($agreement->reference_no) ?: 'agreement-'.$agreement->id).'-'.now()->format('YmdHis').'.pdf';
        $relativePath = 'generated/agreements/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->pdf->render($agreement));
        $absolutePath = Storage::disk('local')->path($relativePath);

        $file = File::create([
            'folder_id' => $folder->id,
            'client_id' => $agreement->client_id,
            'common_user_id' => $agreement->common_user_id,
            'name' => $storedName,
            'original_name' => $displayName,
            'disk' => 'local',
            'path' => $relativePath,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => (is_file($absolutePath) ? filesize($absolutePath) : 0) ?: 0,
            'uploaded_by' => $userId,
            'verified' => true,
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);

        $agreement->forceFill([
            'generated_file_id' => $file->id,
            'status' => $agreement->status === 'draft' ? 'sent' : $agreement->status,
        ])->save();

        return $file;
    }

    private function normalizeFileName(?string $fileName, Agreement $agreement): string
    {
        $name = trim((string) $fileName);
        if ($name === '') {
            $name = $agreement->reference_no.' Service Agreement';
        }

        return str_ends_with(strtolower($name), '.pdf') ? $name : $name.'.pdf';
    }

    private function agreementFolder(?Client $client, ?CommonUser $lead, int $userId): Folder
    {
        if ($lead) {
            return $this->folders->leadSubfolder($lead, 'Unsigned Agreement', $userId);
        }

        if (! $client) {
            return Folder::firstOrCreate(
                ['name' => 'Agreements', 'parent_id' => null],
                ['slug' => 'agreements', 'is_active' => true, 'created_by' => $userId],
            );
        }

        return $this->folders->clientSubfolder($client, 'Agreements', $userId);
    }
}
