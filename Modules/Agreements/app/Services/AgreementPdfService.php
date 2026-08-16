<?php

namespace Modules\Agreements\Services;

use App\Support\Pdf\BrowsershotPdfRenderer;
use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Modules\Agreements\Models\Agreement;
use Throwable;

/**
 * Renders the Tamil-language service agreement (fixed legal text + English
 * dynamic fields) to PDF via headless Chrome, so complex-script text renders
 * correctly instead of relying on a PHP-only PDF library's font handling.
 */
class AgreementPdfService
{
    public function render(Agreement $agreement): string
    {
        $html = View::make('agreements::pdf.service-agreement', [
            'agreement' => $agreement,
        ])->render();

        // Skip the browser in tests for speed/determinism, and fall back to a
        // plain-text PDF if Chrome is unavailable, so generation never hard-fails.
        if (app()->environment('testing')) {
            return SimplePdf::fromText($this->fallbackText($agreement));
        }

        try {
            return BrowsershotPdfRenderer::render($html, [12, 10, 12, 10]);
        } catch (Throwable $e) {
            Log::warning('Agreement PDF Browsershot render failed, using text fallback.', [
                'agreement_id' => $agreement->id,
                'error' => $e->getMessage(),
            ]);

            return SimplePdf::fromText($this->fallbackText($agreement));
        }
    }

    private function fallbackText(Agreement $agreement): string
    {
        return implode("\n", [
            'DREAM FLY VISA CONSULTANCY (PVT) LTD',
            'SERVICE AGREEMENT',
            'Reference No: '.$agreement->reference_no,
            'Client: '.$agreement->client_name,
            'Passport No: '.($agreement->client_passport_no ?? '-'),
            'Visa Type / Country: '.($agreement->visa_type ?? '-').' / '.($agreement->country ?? '-'),
            'Total Fee: LKR '.number_format($agreement->total_fee),
            'Advance Paid: LKR '.number_format($agreement->advance_paid),
            'Balance: LKR '.number_format($agreement->balance),
        ]);
    }
}
