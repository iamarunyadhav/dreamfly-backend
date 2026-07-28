<?php

namespace Modules\Ocr\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Models\File;
use Modules\Ocr\Models\OcrExtraction;
use Throwable;

class OcrExtractionService
{
    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(
        private OcrSettingsService $settings,
        private OcrTextParser $parser,
    ) {
    }

    /**
     * Run (or re-run) OCR against an uploaded file. Never throws - any
     * failure (disabled, unsupported, oversized, or a Vision API error)
     * leaves a `failed` extraction row with a readable error_message rather
     * than breaking the request.
     */
    public function run(File $file, int $userId): OcrExtraction
    {
        $extraction = OcrExtraction::create([
            'file_id' => $file->id,
            'status' => 'processing',
            'requested_by' => $userId,
        ]);

        $settings = $this->settings->getRaw();

        $guardError = $this->preflightError($file, $settings);
        if ($guardError) {
            return $this->fail($extraction, $guardError);
        }

        try {
            $result = $this->callVision($file, $settings);
        } catch (Throwable $e) {
            return $this->fail($extraction, $e->getMessage());
        }

        if (! $result['ok']) {
            return $this->fail($extraction, $result['error']);
        }

        $annotation = $result['annotation'];
        $fullText = $annotation['text'] ?? '';
        $rows = $this->parser->parseText($fullText);

        if ($rows === []) {
            return $this->fail($extraction, 'No text detected in the document.');
        }

        $confidence = $this->parser->averageConfidence($annotation);

        $extraction->forceFill([
            'status' => 'completed',
            'raw_response' => $annotation,
            'completed_at' => now(),
        ])->save();

        foreach ($rows as $index => $row) {
            $extraction->fields()->create([
                'sort_order' => $index,
                'label' => $row['label'],
                'value' => $row['value'],
                'confidence' => $confidence,
            ]);
        }

        return $extraction->fresh('fields');
    }

    private function preflightError(File $file, array $settings): ?string
    {
        if (! ($settings['enabled'] ?? false)) {
            return 'OCR is not enabled in settings.';
        }

        if (empty($settings['api_key'])) {
            return 'OCR is not configured.';
        }

        if (! in_array($file->mime_type, self::SUPPORTED_MIME_TYPES, true)) {
            return 'This file type is not supported for OCR.';
        }

        $maxBytes = (int) ($settings['max_file_size_mb'] ?? 15) * 1024 * 1024;
        if ($file->size > $maxBytes) {
            return 'File is too large for OCR.';
        }

        return null;
    }

    /**
     * @return array{ok: bool, annotation?: array, error?: string}
     */
    private function callVision(File $file, array $settings): array
    {
        $bytes = Storage::disk($file->disk)->get($file->path);
        $base64 = base64_encode($bytes);
        $apiKey = $settings['api_key'];

        if ($file->mime_type === 'application/pdf') {
            return $this->callFilesAnnotate($base64, $apiKey, (int) ($settings['max_pdf_pages'] ?? 5));
        }

        return $this->callImagesAnnotate($base64, $apiKey);
    }

    private function callImagesAnnotate(string $base64, string $apiKey): array
    {
        $url = "https://vision.googleapis.com/v1/images:annotate?key={$apiKey}";
        $payload = [
            'requests' => [[
                'image' => ['content' => $base64],
                'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
            ]],
        ];

        $response = $this->postWithRetry($url, $payload);

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $response->json('error.message') ?? ('HTTP '.$response->status())];
        }

        $annotation = $response->json('responses.0.fullTextAnnotation');

        return $annotation
            ? ['ok' => true, 'annotation' => $annotation]
            : ['ok' => false, 'error' => 'No text detected in the document.'];
    }

    /**
     * Vision's files:annotate endpoint supports inline PDF content
     * synchronously (up to 5 pages), unlike the async files:asyncBatchAnnotate
     * flow - simpler and avoids polling for a feature meant to feel instant.
     */
    private function callFilesAnnotate(string $base64, string $apiKey, int $maxPages): array
    {
        $url = "https://vision.googleapis.com/v1/files:annotate?key={$apiKey}";
        $payload = [
            'requests' => [[
                'inputConfig' => ['content' => $base64, 'mimeType' => 'application/pdf'],
                'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                'pages' => range(1, max(1, $maxPages)),
            ]],
        ];

        $response = $this->postWithRetry($url, $payload);

        if (! $response->successful()) {
            return ['ok' => false, 'error' => $response->json('error.message') ?? ('HTTP '.$response->status())];
        }

        $pageResponses = $response->json('responses.0.responses') ?? [];
        $texts = array_filter(array_map(fn ($r) => $r['fullTextAnnotation']['text'] ?? null, $pageResponses));
        $pages = array_merge(...array_map(fn ($r) => $r['fullTextAnnotation']['pages'] ?? [], $pageResponses));

        if ($texts === []) {
            return ['ok' => false, 'error' => 'No text detected in the document.'];
        }

        return ['ok' => true, 'annotation' => ['text' => implode("\n", $texts), 'pages' => $pages]];
    }

    /** Retries only transient failures (429/5xx) - a permanent 4xx fails immediately with its message intact. */
    private function postWithRetry(string $url, array $payload)
    {
        return retry(3, function () use ($url, $payload) {
            $response = Http::timeout(30)->post($url, $payload);

            if ($response->status() === 429 || $response->serverError()) {
                $response->throw();
            }

            return $response;
        }, 500);
    }

    private function fail(OcrExtraction $extraction, string $message): OcrExtraction
    {
        $extraction->forceFill(['status' => 'failed', 'error_message' => $message])->save();

        return $extraction->fresh('fields');
    }
}
