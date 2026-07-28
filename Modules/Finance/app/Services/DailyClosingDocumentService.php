<?php

namespace Modules\Finance\Services;

use App\Support\Pdf\SimplePdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Modules\Files\Models\File;
use Modules\Finance\Models\DailyClosing;
use Modules\Folders\Models\Folder;
use Spatie\Browsershot\Browsershot;
use Throwable;

class DailyClosingDocumentService
{
    public function generatePdf(DailyClosing $closing, int $userId): File
    {
        $date = $closing->closing_date->toDateString();
        $folder = Folder::firstOrCreate(
            ['name' => 'Daily Closings', 'parent_id' => null],
            ['slug' => 'daily-closings', 'is_active' => true, 'created_by' => $userId],
        );

        $storedName = 'daily-closing-'.$date.'-'.now()->format('His').'.pdf';
        $relativePath = 'generated/daily-closings/'.$storedName;

        Storage::disk('local')->put($relativePath, $this->renderBytes($closing));
        $absolutePath = Storage::disk('local')->path($relativePath);

        $file = File::create([
            'folder_id' => $folder->id,
            'name' => $storedName,
            'original_name' => 'Daily Closing '.$date.'.pdf',
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

        $closing->forceFill(['generated_file_id' => $file->id])->save();

        return $file;
    }

    private function renderBytes(DailyClosing $closing): string
    {
        $html = View::make('finance::pdf.daily-closing', ['closing' => $closing])->render();

        if (app()->environment('testing')) {
            return SimplePdf::fromText($this->text($closing));
        }

        try {
            $browsershot = Browsershot::html($html)->format('A4')->margins(0, 0, 0, 0)->showBackground()->waitUntilNetworkIdle();
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
            Log::warning('Daily closing PDF Browsershot render failed, using text fallback.', [
                'closing_id' => $closing->id,
                'error' => $e->getMessage(),
            ]);

            return SimplePdf::fromText($this->text($closing));
        }
    }

    private function text(DailyClosing $c): string
    {
        return implode("\n", [
            'DREAM FLY VISA CONSULTANCY (PVT) LTD',
            'DAILY CLOSING REPORT',
            'Date: '.$c->closing_date->format('d.m.Y'),
            'Status: '.strtoupper($c->status),
            '',
            'Opening Balance: LKR '.number_format($c->opening_balance),
            'Total Income: LKR '.number_format($c->income_total),
            'Total Expense: LKR '.number_format($c->expense_total),
            'Cash (net): LKR '.number_format($c->cash_total),
            'Bank (net): LKR '.number_format($c->bank_total),
            'Closing Balance: LKR '.number_format($c->closing_balance),
            'Counted Cash: '.($c->counted_cash !== null ? 'LKR '.number_format($c->counted_cash) : '-'),
            'Variance: LKR '.number_format($c->variance),
        ]);
    }
}
