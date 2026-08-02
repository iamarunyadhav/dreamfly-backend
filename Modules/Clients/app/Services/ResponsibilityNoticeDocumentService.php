<?php

namespace Modules\Clients\Services;

use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientResponsibilityNotice;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Renders the Client Document Responsibility Notice to PDF and files it as a real
 * File record in the client's folder tree, mirroring how the agreement and
 * invoice documents are produced.
 */
class ResponsibilityNoticeDocumentService
{
    public function __construct(private FolderService $folders)
    {
    }

    public function generate(Client $client, ClientResponsibilityNotice $notice, int $userId): File
    {
        $folder = $this->noticeFolder($client, $userId);
        $safeReference = Str::slug($client->reference_no) ?: 'client-'.$client->id;
        $storedName = $safeReference.'-responsibility-notice-'.now()->format('YmdHis').'.pdf';
        $relativePath = 'generated/responsibility-notices/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->renderBytes($client, $notice));
        $absolutePath = Storage::disk('local')->path($relativePath);

        $file = File::create([
            'folder_id' => $folder->id,
            'client_id' => $client->id,
            'name' => $storedName,
            'original_name' => $client->reference_no.' Client Document Responsibility Notice.pdf',
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

        $notice->forceFill([
            'generated_file_id' => $file->id,
            // An acknowledged notice keeps its status when it is re-generated.
            'status' => $notice->acknowledged ? $notice->status : 'generated',
        ])->save();

        return $file;
    }

    /**
     * The documents the client handed over, taken from the runtime case checklist
     * so the notice lists exactly what the office actually received.
     *
     * @return Collection<int, array{title: string, owner: string, status: string}>
     */
    public function documentsFor(Client $client): Collection
    {
        return CaseChecklistItem::where('client_id', $client->id)
            ->orderBy('owner')
            ->orderBy('id')
            ->get()
            ->map(fn (CaseChecklistItem $item) => [
                'title' => (string) $item->title,
                'owner' => Str::title(str_replace('_', ' ', (string) $item->owner)),
                'status' => Str::title(str_replace('_', ' ', (string) $item->status)),
            ])
            ->values();
    }

    private function renderBytes(Client $client, ClientResponsibilityNotice $notice): string
    {
        $documents = $this->documentsFor($client);

        $html = View::make('clients::pdf.responsibility-notice', [
            'client' => $client,
            'notice' => $notice,
            'documents' => $documents,
        ])->render();

        if (app()->environment('testing')) {
            return SimplePdf::fromText($this->text($client, $notice, $documents));
        }

        try {
            $browsershot = Browsershot::html($html)
                ->format('A4')
                ->margins(0, 0, 0, 0)
                ->showBackground()
                ->waitUntilNetworkIdle();

            if ($node = config('agreements.pdf.node_binary')) {
                $browsershot->setNodeBinary($node);
            }
            if ($npm = config('agreements.pdf.npm_binary')) {
                $browsershot->setNpmBinary($npm);
            }
            if ($chrome = config('agreements.pdf.chrome_path')) {
                $browsershot->setChromePath($chrome);
            }

            return $browsershot->pdf();
        } catch (Throwable $e) {
            Log::warning('Responsibility notice PDF Browsershot render failed, using text fallback.', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return SimplePdf::fromText($this->text($client, $notice, $documents));
        }
    }

    private function text(Client $client, ClientResponsibilityNotice $notice, Collection $documents): string
    {
        $lines = [
            'DREAM FLY VISA CONSULTANCY (PVT) LTD',
            'CLIENT DOCUMENT RESPONSIBILITY NOTICE',
            'Client: '.$client->full_name,
            'Client Ref: '.$client->reference_no,
            'Passport No: '.($client->passport_no ?? '-'),
            'Traveling Country: '.($client->country ?? '-'),
            'Issued: '.now()->format('d.m.Y'),
            '',
            'The client confirms all documents supplied are genuine, accurate and complete;',
            'accepts sole responsibility for any false or altered document and its consequences;',
            'accepts that the consultancy does not verify third-party documents and does not',
            'guarantee a visa outcome, which rests solely with the visa-issuing authority.',
        ];

        if ($documents->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'DOCUMENTS RECEIVED FROM CLIENT';
            foreach ($documents as $index => $document) {
                $lines[] = ($index + 1).'. '.$document['title'].' ('.$document['owner'].') - '.$document['status'];
            }
        }

        if (trim((string) $notice->content) !== '') {
            $lines[] = '';
            $lines[] = (string) $notice->content;
        }

        $lines[] = '';
        $lines[] = $notice->acknowledged
            ? 'ACKNOWLEDGED on '.optional($notice->acknowledged_at)->format('d.m.Y H:i')
            : 'AWAITING CLIENT ACKNOWLEDGEMENT';
        $lines[] = 'AUTHORIZED BY: P. KEMARUPAN, DIRECTOR';

        return implode("\n", $lines);
    }

    private function noticeFolder(Client $client, int $userId): Folder
    {
        return $this->folders->clientSubfolder($client, 'Final Documents', $userId);
    }
}
