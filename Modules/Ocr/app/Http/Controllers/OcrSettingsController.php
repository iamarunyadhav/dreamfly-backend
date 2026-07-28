<?php

namespace Modules\Ocr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Modules\Ocr\Http\Requests\UpdateOcrSettingsRequest;
use Modules\Ocr\Services\OcrSettingsService;

class OcrSettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private OcrSettingsService $settings)
    {
    }

    public function show()
    {
        return $this->ok($this->settings->getMasked());
    }

    public function update(UpdateOcrSettingsRequest $request)
    {
        return $this->ok($this->settings->update($request->validated()), 'OCR settings saved.');
    }
}
