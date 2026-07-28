<?php

namespace App\Support\Pdf;

use Illuminate\Support\Collection;

/**
 * Produces a minimal, dependency-free single-page PDF from plain text. Used as a
 * fallback when headless-Chrome (Browsershot) rendering is unavailable - e.g. in
 * the testing environment or on a host without Chrome - so document generation
 * never hard-fails.
 */
class SimplePdf
{
    public static function fromText(string $text): string
    {
        /** @var Collection<int, string> $lines */
        $lines = collect(preg_split('/\R/', $text) ?: [])
            ->flatMap(fn (string $line) => str_split(trim($line), 92))
            ->filter()
            ->take(62)
            ->values();

        $content = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
        foreach ($lines as $line) {
            $content .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $line).") Tj\nT*\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
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

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
