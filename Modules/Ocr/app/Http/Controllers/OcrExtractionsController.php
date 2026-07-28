<?php

namespace Modules\Ocr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Ocr\Http\Requests\UpdateOcrExtractionFieldRequest;
use Modules\Ocr\Http\Resources\OcrExtractionFieldResource;
use Modules\Ocr\Http\Resources\OcrExtractionResource;
use Modules\Ocr\Models\OcrExtraction;
use Modules\Ocr\Models\OcrExtractionField;
use Modules\Ocr\Services\OcrExtractionService;
use Modules\Ocr\Services\OcrPdfService;

class OcrExtractionsController extends Controller
{
    use ApiResponse;

    public function run(File $file, OcrExtractionService $service)
    {
        $extraction = $service->run($file, request()->user()->id);

        return $this->created(new OcrExtractionResource($extraction));
    }

    public function show(File $file)
    {
        $extraction = OcrExtraction::where('file_id', $file->id)->with('fields')->latest()->first();

        return $this->ok($extraction ? new OcrExtractionResource($extraction) : null);
    }

    public function updateField(UpdateOcrExtractionFieldRequest $request, OcrExtraction $extraction, OcrExtractionField $field)
    {
        abort_unless($field->ocr_extraction_id === $extraction->id, 404);

        $field->forceFill(['value' => $request->validated()['value'] ?? null, 'is_user_edited' => true])->save();

        return $this->ok(new OcrExtractionFieldResource($field), 'Field updated.');
    }

    public function generatePdf(OcrExtraction $extraction, OcrPdfService $pdfService)
    {
        $file = $pdfService->generatePdf($extraction, request()->user()->id);

        return $this->created(new FileResource($file));
    }
}
