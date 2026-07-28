<?php

namespace Modules\Files\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Files\Models\File;
use ZipArchive;

class DocumentRenderService
{
    /**
     * Render a private document into a lightweight HTML preview that preserves
     * DOCX table structure closely enough for office review before download/share.
     */
    public function previewHtml(File $file): string
    {
        if ($file->extension !== 'docx') {
            return $this->basicPreview($file);
        }

        $tables = $this->docxTables($this->absolutePath($file));
        $title = e($file->original_name);
        $tableHtml = collect($tables)->map(function (array $rows) {
            $rowsHtml = collect($rows)->map(function (array $cells) {
                $cellsHtml = collect($cells)->map(fn (string $cell) => '<td>'.nl2br(e($cell)).'</td>')->implode('');

                return '<tr>'.$cellsHtml.'</tr>';
            })->implode('');

            return '<table>'.$rowsHtml.'</table>';
        })->implode('');

        if ($tableHtml === '') {
            $tableHtml = '<p class="empty">No previewable table content found in this document.</p>';
        }

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { margin: 0; background: #f8fafc; color: #0f172a; font-family: Arial, sans-serif; }
    .page { width: min(920px, calc(100% - 32px)); margin: 24px auto; background: #fff; border: 1px solid #dbe3ef; box-shadow: 0 12px 30px rgba(15, 23, 42, .08); }
    .header { padding: 20px 24px; border-bottom: 4px solid #fbbf24; background: #0b3d91; color: #fff; }
    .brand { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #bfdbfe; }
    h1 { margin: 6px 0 0; font-size: 22px; }
    .content { padding: 24px; }
    table { width: 100%; border-collapse: collapse; margin: 0 0 18px; font-size: 13px; }
    td { border: 1px solid #cbd5e1; padding: 8px 10px; vertical-align: top; min-height: 28px; }
    td:first-child { width: 34%; background: #f8fafc; font-weight: 700; color: #1e3a8a; }
    .empty { color: #64748b; }
  </style>
</head>
<body>
  <main class="page">
    <header class="header">
      <div class="brand">Dream Fly Visa Consultancy</div>
      <h1>{$title}</h1>
    </header>
    <section class="content">{$tableHtml}</section>
  </main>
</body>
</html>
HTML;
    }

    public function generatePdf(File $source, int $userId): File
    {
        $html = $this->previewHtml($source);
        $storedName = Str::slug(pathinfo($source->name, PATHINFO_FILENAME) ?: 'document').'-preview-'.now()->format('YmdHis').'.pdf';
        $relativePath = trim(dirname($source->path), '.\\/').'/'.$storedName;
        $absolutePath = Storage::disk('local')->path($relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0775, true);
        }

        file_put_contents($absolutePath, $this->simplePdf($this->previewText($source)));

        return File::create([
            'folder_id' => $source->folder_id,
            'client_id' => $source->client_id,
            'common_user_id' => $source->common_user_id,
            'name' => $storedName,
            'original_name' => pathinfo($source->original_name, PATHINFO_FILENAME).'.pdf',
            'disk' => 'local',
            'path' => $relativePath,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => filesize($absolutePath) ?: 0,
            'uploaded_by' => $userId,
            'verified' => true,
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);
    }

    private function previewText(File $file): string
    {
        if ($file->extension !== 'docx') {
            return $file->original_name;
        }

        return collect($this->docxTables($this->absolutePath($file)))
            ->flatten(1)
            ->map(fn (array $row) => implode(' : ', array_filter($row)))
            ->filter()
            ->implode("\n");
    }

    private function docxTables(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! $xml) {
            return [];
        }

        $document = new DOMDocument();
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tables = [];
        foreach ($xpath->query('//w:tbl') as $table) {
            $rows = [];
            foreach ($xpath->query('./w:tr', $table) as $row) {
                $cells = [];
                foreach ($xpath->query('./w:tc', $row) as $cell) {
                    $cells[] = $this->cellText($xpath, $cell);
                }
                if (array_filter($cells)) {
                    $rows[] = $cells;
                }
            }
            if ($rows) {
                $tables[] = $rows;
            }
        }

        return $tables;
    }

    private function cellText(DOMXPath $xpath, mixed $cell): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t', $cell) as $textNode) {
            $parts[] = $textNode->nodeValue;
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    private function basicPreview(File $file): string
    {
        $title = e($file->original_name);
        $extension = e(strtoupper((string) $file->extension));

        return <<<HTML
<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:32px">
<section style="max-width:760px;margin:auto;background:white;border:1px solid #dbe3ef;padding:24px">
<h1 style="margin-top:0">{$title}</h1>
<p>This {$extension} file is stored securely. Use Download to open the original file.</p>
</section>
</body></html>
HTML;
    }

    private function absolutePath(File $file): string
    {
        return Storage::disk($file->disk)->path($file->path);
    }

    private function simplePdf(string $text): string
    {
        $lines = collect(preg_split('/\R/', $text) ?: [])
            ->flatMap(fn (string $line) => str_split(trim($line), 92))
            ->filter()
            ->take(58)
            ->values();

        if ($lines->isEmpty()) {
            $lines = collect(['Dream Fly document preview']);
        }

        $content = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
        foreach ($lines as $line) {
            $content .= '('.$this->pdfText((string) $line).") Tj\nT*\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
