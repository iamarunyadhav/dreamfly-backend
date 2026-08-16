<?php

namespace Modules\Clients\Services;

use App\Support\Pdf\BrowsershotPdfRenderer;
use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\Files\Models\File;
use Modules\Folders\Services\FolderService;
use Throwable;

/**
 * Renders a plain HTML/Browsershot summary of the Documentation Unit's own
 * task list (no supplied .docx template - unlike Admin Summary/Application
 * Unit, this unit has no fixed office form to fill), filed as a real File
 * record. There is no dedicated "unit" table for Documentation Unit - its
 * tasks are the same `documentation_tasks` rows Correction Unit creates, so
 * the summary just reports on all of a client's tasks.
 */
class DocumentPrepSummaryDocumentService
{
    public function __construct(private FolderService $folders)
    {
    }

    public function generate(Client $client, int $userId): File
    {
        $tasks = DocumentationTask::where('client_id', $client->id)
            ->with(['assignedUser', 'file'])
            ->orderBy('due_at')
            ->get();

        $folder = $this->folders->clientSubfolder($client, 'Documentation Unit', $userId);
        $safeReference = Str::slug($client->reference_no) ?: 'client-'.$client->id;
        $storedName = $safeReference.'-documentation-unit-summary-'.now()->format('YmdHis').'.pdf';
        $relativePath = 'generated/document-prep-summaries/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->renderBytes($client, $tasks));
        $absolutePath = Storage::disk('local')->path($relativePath);

        return File::create([
            'folder_id' => $folder->id,
            'client_id' => $client->id,
            'name' => $storedName,
            'original_name' => $client->reference_no.' Documentation Unit Summary.pdf',
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
    }

    private function renderBytes(Client $client, Collection $tasks): string
    {
        if (app()->environment('testing')) {
            return SimplePdf::fromText($this->text($client, $tasks));
        }

        try {
            $html = View::make('clients::pdf.document-prep-summary', ['client' => $client, 'tasks' => $tasks])->render();

            return BrowsershotPdfRenderer::render($html, [0, 0, 0, 0]);
        } catch (Throwable $e) {
            Log::warning('Documentation Unit summary PDF Browsershot render failed, using text fallback.', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return SimplePdf::fromText($this->text($client, $tasks));
        }
    }

    private function text(Client $client, Collection $tasks): string
    {
        $lines = [
            'DREAM FLY VISA CONSULTANCY (PVT) LTD',
            'DOCUMENTATION UNIT SUMMARY',
            'Client: '.$client->full_name,
            'Client Ref: '.$client->reference_no,
            'Generated: '.now()->format('d.m.Y H:i'),
            '',
            'TASKS',
        ];

        foreach ($tasks as $index => $task) {
            $assignee = $task->assignedUser?->name ?? $task->assigned_role ?? 'Unassigned';
            $due = $task->due_at?->format('d.m.Y') ?? 'no due date';
            $lines[] = ($index + 1).'. '.$task->title.' - '.$assignee.' - '.str_replace('_', ' ', $task->status).' - due '.$due;
        }

        if ($tasks->isEmpty()) {
            $lines[] = 'No Documentation Unit tasks recorded.';
        }

        return implode("\n", $lines);
    }
}
