<?php

namespace Tests\Unit;

use Modules\Ocr\Services\OcrTextParser;
use PHPUnit\Framework\TestCase;

class OcrTextParserTest extends TestCase
{
    public function test_label_colon_value_lines_are_split(): void
    {
        $rows = (new OcrTextParser())->parseText("Passport No: N1234567\nFull Name: Arunpragash Alwar");

        $this->assertSame(['label' => 'Passport No', 'value' => 'N1234567'], $rows[0]);
        $this->assertSame(['label' => 'Full Name', 'value' => 'Arunpragash Alwar'], $rows[1]);
    }

    public function test_label_dash_value_lines_are_split(): void
    {
        $rows = (new OcrTextParser())->parseText("Nationality - Sri Lankan\nDate of Birth — 1990-05-12");

        $this->assertSame(['label' => 'Nationality', 'value' => 'Sri Lankan'], $rows[0]);
        $this->assertSame(['label' => 'Date of Birth', 'value' => '1990-05-12'], $rows[1]);
    }

    public function test_a_line_with_no_separator_becomes_a_label_only_row(): void
    {
        $rows = (new OcrTextParser())->parseText("REPUBLIC OF SRI LANKA\nPASSPORT");

        $this->assertSame(['label' => 'REPUBLIC OF SRI LANKA', 'value' => null], $rows[0]);
        $this->assertSame(['label' => 'PASSPORT', 'value' => null], $rows[1]);
    }

    public function test_order_is_preserved_top_to_bottom(): void
    {
        $rows = (new OcrTextParser())->parseText("First: A\nSecond: B\nThird: C");

        $this->assertSame(['First', 'Second', 'Third'], array_column($rows, 'label'));
    }

    public function test_blank_and_very_short_lines_are_discarded(): void
    {
        $rows = (new OcrTextParser())->parseText("Name: John\n\n \nX\nAddress: Colombo");

        $this->assertCount(2, $rows);
        $this->assertSame('Name', $rows[0]['label']);
        $this->assertSame('Address', $rows[1]['label']);
    }

    public function test_average_confidence_is_computed_across_all_symbols(): void
    {
        $annotation = [
            'pages' => [
                [
                    'blocks' => [
                        [
                            'paragraphs' => [
                                [
                                    'words' => [
                                        ['symbols' => [['confidence' => 0.9], ['confidence' => 0.8]]],
                                        ['symbols' => [['confidence' => 1.0]]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(0.9, (new OcrTextParser())->averageConfidence($annotation));
    }

    public function test_average_confidence_is_null_when_no_symbols_present(): void
    {
        $this->assertNull((new OcrTextParser())->averageConfidence(['pages' => []]));
    }
}
