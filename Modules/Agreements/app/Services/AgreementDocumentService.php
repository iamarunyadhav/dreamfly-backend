<?php

namespace Modules\Agreements\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Agreements\Models\Agreement;
use Modules\Clients\Models\Client;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;

/**
 * Renders the Tamil service agreement to PDF and files it as a real File record
 * in a chosen folder, so the generated document lives in the client's folder
 * tree instead of only streaming on demand.
 */
class AgreementDocumentService
{
    public function __construct(protected AgreementPdfService $pdf)
    {
    }

    public function generate(Agreement $agreement, ?int $folderId, ?string $fileName, int $userId): File
    {
        $folder = $folderId
            ? Folder::findOrFail($folderId)
            : $this->agreementFolder($agreement->client_id ? Client::find($agreement->client_id) : null, $userId);

        $displayName = $this->normalizeFileName($fileName, $agreement);
        $storedName = (Str::slug($agreement->reference_no) ?: 'agreement-'.$agreement->id).'-'.now()->format('YmdHis').'.pdf';
        $relativePath = 'generated/agreements/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->pdf->render($agreement));
        $absolutePath = Storage::disk('local')->path($relativePath);

        $file = File::create([
            'folder_id' => $folder->id,
            'client_id' => $agreement->client_id,
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

    private function agreementFolder(?Client $client, int $userId): Folder
    {
        if (! $client) {
            return Folder::firstOrCreate(
                ['name' => 'Agreements', 'parent_id' => null],
                ['slug' => 'agreements', 'is_active' => true, 'created_by' => $userId],
            );
        }

        $root = Folder::firstOrCreate(['name' => 'Clients', 'parent_id' => null], [
            'slug' => 'clients',
            'is_active' => true,
            'created_by' => $userId,
        ]);

        $clientFolder = Folder::where('parent_id', $root->id)
            ->where('name', 'like', $client->reference_no.'%')
            ->first();

        if (! $clientFolder) {
            $name = trim($client->reference_no.' - '.$client->full_name);
            $clientFolder = Folder::create([
                'name' => $name,
                'slug' => Str::slug($name) ?: 'client-'.$client->id,
                'parent_id' => $root->id,
                'is_active' => true,
                'created_by' => $userId,
            ]);
        }

        return Folder::firstOrCreate(
            ['name' => 'Agreements', 'parent_id' => $clientFolder->id],
            ['slug' => Str::slug($clientFolder->name.' Agreements'), 'is_active' => true, 'created_by' => $userId],
        );
    }
}
