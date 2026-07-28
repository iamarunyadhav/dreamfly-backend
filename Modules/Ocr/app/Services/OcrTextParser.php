<?php

namespace Modules\Ocr\Services;

/**
 * Turns Google Cloud Vision's reconstructed document text into a simple,
 * top-to-bottom list of label/value rows - the "simple form" interpretation
 * of the extracted document (order preserved, no bounding-box geometry).
 */
class OcrTextParser
{
    private const LABEL_VALUE_PATTERN = '/^(.{1,60}?)\s*[:\-–—]\s*(.+)$/u';

    /**
     * @return array<int, array{label: string, value: ?string}>
     */
    public function parseText(string $fullText): array
    {
        $lines = preg_split('/\R/u', $fullText) ?: [];
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (mb_strlen($line) < 2) {
                continue;
            }

            if (preg_match(self::LABEL_VALUE_PATTERN, $line, $matches)) {
                $rows[] = ['label' => trim($matches[1]), 'value' => trim($matches[2])];
            } else {
                $rows[] = ['label' => $line, 'value' => null];
            }
        }

        return $rows;
    }

    /**
     * A single overall confidence score (0-1) averaged across every symbol
     * Vision detected - applied uniformly to every row, since Vision's line
     * grouping in `fullTextAnnotation.text` doesn't preserve a stable link
     * back to individual symbol confidences without walking (and re-joining)
     * the whole pages/blocks/paragraphs/words/symbols tree ourselves. This is
     * a deliberately simple, informational-only signal - never used to block
     * or hide a row.
     */
    public function averageConfidence(array $fullTextAnnotation): ?float
    {
        $sum = 0.0;
        $count = 0;

        foreach ($fullTextAnnotation['pages'] ?? [] as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['paragraphs'] ?? [] as $paragraph) {
                    foreach ($paragraph['words'] ?? [] as $word) {
                        foreach ($word['symbols'] ?? [] as $symbol) {
                            if (isset($symbol['confidence'])) {
                                $sum += $symbol['confidence'];
                                $count++;
                            }
                        }
                    }
                }
            }
        }

        return $count > 0 ? round($sum / $count, 4) : null;
    }
}
