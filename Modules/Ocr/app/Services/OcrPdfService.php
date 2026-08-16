<?php

namespace Modules\Ocr\Services;

use App\Support\Pdf\BrowsershotPdfRenderer;
use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Modules\Files\Models\File;
use Modules\Ocr\Models\OcrExtraction;
use Throwable;

class OcrPdfService
{
    public function generatePdf(OcrExtraction $extraction, int $userId): File
    {
        $extraction->loadMissing(['file', 'fields']);
        $sourceFile = $extraction->file;

        $storedName = 'ocr-'.$extraction->id.'-'.now()->format('YmdHis').'.pdf';
        $relativePath = 'generated/ocr/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->renderBytes($extraction));
        $absolutePath = Storage::disk('local')->path($relativePath);

        return File::create([
            'folder_id' => $sourceFile?->folder_id,
            'client_id' => $sourceFile?->client_id,
            'common_user_id' => $sourceFile?->common_user_id,
            'name' => $storedName,
            'original_name' => ($sourceFile?->original_name ?? 'Document').' - OCR.pdf',
            'disk' => 'local',
            'path' => $relativePath,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => (is_file($absolutePath) ? filesize($absolutePath) : 0) ?: 0,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Prefer the branded, headless-Chrome rendered PDF. Fall back to a plain
     * text PDF if Chrome/Browsershot is unavailable (or in tests, where we skip
     * the browser for speed and determinism) so PDF generation never fails.
     */
    private function renderBytes(OcrExtraction $extraction): string
    {
        $html = View::make('ocr::pdf.extraction', ['extraction' => $extraction])->render();

        if (app()->environment('testing')) {
            return SimplePdf::fromText($this->text($extraction));
        }

        try {
            return BrowsershotPdfRenderer::render($html, [0, 0, 0, 0]);
        } catch (Throwable $e) {
            Log::warning('OCR extraction PDF Browsershot render failed, using text fallback.', [
                'extraction_id' => $extraction->id,
                'error' => $e->getMessage(),
            ]);

            return SimplePdf::fromText($this->text($extraction));
        }
    }

    private function text(OcrExtraction $extraction): string
    {
        $lines = [
            'DREAM FLY VISA CONSULTANCY (PVT) LTD',
            'OCR EXTRACTED DOCUMENT',
            'Source file: '.($extraction->file?->original_name ?? '-'),
            '',
        ];

        foreach ($extraction->fields as $field) {
            $lines[] = $field->label.': '.($field->value ?? '-');
        }

        return implode("\n", $lines);
    }
}
