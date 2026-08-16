<?php

namespace Modules\Invoices\Services;

use App\Support\Pdf\BrowsershotPdfRenderer;
use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Modules\Folders\Services\FolderService;
use Modules\Invoices\Models\Invoice;
use Throwable;

class InvoiceDocumentService
{
    public function __construct(private FolderService $folders)
    {
    }

    public function generatePdf(Invoice $invoice, int $userId): File
    {
        $invoice->loadMissing(['client', 'items', 'payments']);
        $folder = $this->invoiceFolder($invoice, $userId);
        $storedName = (Str::slug($invoice->reference_no) ?: 'invoice-'.$invoice->id).'-notice-'.now()->format('YmdHis').'.pdf';
        $relativePath = 'generated/invoices/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->renderBytes($invoice));
        $absolutePath = Storage::disk('local')->path($relativePath);

        $file = File::create([
            'folder_id' => $folder->id,
            'client_id' => $invoice->client_id,
            'name' => $storedName,
            'original_name' => $invoice->reference_no.' Invoice Notice.pdf',
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

        $invoice->forceFill([
            'generated_file_id' => $file->id,
            'status' => $invoice->status === 'draft' ? 'issued' : $invoice->status,
        ])->save();

        return $file;
    }

    /**
     * Prefer the branded, headless-Chrome rendered PDF. Fall back to a plain
     * text PDF if Chrome/Browsershot is unavailable (or in tests, where we skip
     * the browser for speed and determinism) so PDF generation never fails.
     */
    private function renderBytes(Invoice $invoice): string
    {
        $html = View::make('invoices::pdf.invoice-notice', ['invoice' => $invoice])->render();

        if (app()->environment('testing')) {
            return SimplePdf::fromText($this->text($invoice));
        }

        try {
            return BrowsershotPdfRenderer::render($html, [0, 0, 0, 0]);
        } catch (Throwable $e) {
            Log::warning('Invoice PDF Browsershot render failed, using text fallback.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return SimplePdf::fromText($this->text($invoice));
        }
    }

    private function text(Invoice $invoice): string
    {
        $client = $invoice->client;
        $lines = [
            'DREAM FLY VISA CONSULTANCY (PVT) LTD',
            'INVOICE NOTICE',
            'Reference No: '.$invoice->reference_no,
            'Client Name: '.($client?->full_name ?? '-'),
            'Passport No: '.($client?->passport_no ?? '-'),
            'Traveling Country: '.($client?->country ?? '-'),
            'Client Phone: '.($client?->phone ?? '-'),
            'Issue Date: '.optional($invoice->issue_date)->toDateString(),
            'Due Date: '.optional($invoice->due_date)->toDateString(),
            '',
            'SERVICE FEE',
            'Total Service Fee: LKR '.number_format($invoice->total_service_fee),
            'Advance Paid: LKR '.number_format($invoice->advance_paid),
            'Service Balance: LKR '.number_format(max(0, $invoice->total_service_fee - $invoice->advance_paid)),
            '',
            'VISA & OTHER FEES',
            'Application Fee: LKR '.number_format($invoice->application_fee),
            'VFS Appointment Fee: LKR '.number_format($invoice->vfs_fee),
        ];

        foreach ($invoice->items as $item) {
            $lines[] = ($item->category ? '['.$item->category.'] ' : '').$item->description.' x '.$item->quantity.': LKR '.number_format($item->amount + $item->tax);
        }

        return implode("\n", [
            ...$lines,
            '',
            'TOTAL AMOUNT PAYABLE NOW: LKR '.number_format($invoice->total_payable),
            'Paid: LKR '.number_format($invoice->paid_amount),
            'Balance: LKR '.number_format($invoice->balance),
            '',
            (string) $invoice->notes,
            'Kindly arrange the above payment to proceed with your visa application.',
            'AUTHORIZED BY: P. KEMARUPAN, DIRECTOR',
        ]);
    }

    private function invoiceFolder(Invoice $invoice, int $userId): Folder
    {
        if ($invoice->client) {
            return $this->folders->clientSubfolder($invoice->client, 'Invoices', $userId);
        }

        return Folder::firstOrCreate(
            ['name' => 'Invoices', 'parent_id' => null],
            ['slug' => 'invoices', 'is_active' => true, 'created_by' => $userId],
        );
    }
}
