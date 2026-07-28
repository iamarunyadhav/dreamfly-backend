<!DOCTYPE html>
@php
    /** @var \Modules\Ocr\Models\OcrExtraction $extraction */
    $file = $extraction->file;
@endphp
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OCR Extraction - {{ $file?->original_name }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', 'Noto Sans', Arial, sans-serif;
        color: #1f2937;
        font-size: 12px;
        line-height: 1.5;
        margin: 0;
        padding: 28px 36px 32px;
    }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #0b3d91;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .header .brand-name { font-size: 19px; font-weight: 700; color: #0b3d91; margin: 0; }
    .header .brand-sub { font-size: 10.5px; color: #b8860b; font-weight: 600; letter-spacing: 1px; margin: 2px 0; }
    .title { font-size: 18px; font-weight: 700; color: #0b3d91; letter-spacing: 0.5px; margin-bottom: 14px; }
    .source { font-size: 10.5px; color: #4b5563; margin-bottom: 18px; }
    table.fields { width: 100%; border-collapse: collapse; }
    table.fields tr:nth-child(even) { background: #f8fafc; }
    table.fields td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    table.fields td.label { width: 35%; font-weight: 600; color: #374151; }
    table.fields td.value { color: #111827; }
</style>
</head>
<body>
    <div class="header">
        <div>
            <p class="brand-name">DREAM FLY VISA CONSULTANCY (PVT) LTD</p>
            <p class="brand-sub">DREAMFLY VISA CONSULTANCY</p>
        </div>
    </div>

    <div class="title">OCR Extracted Document</div>
    <div class="source">Source file: {{ $file?->original_name ?? '-' }}</div>

    <table class="fields">
        @forelse ($extraction->fields as $field)
            <tr>
                <td class="label">{{ $field->label }}</td>
                <td class="value">{{ $field->value ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="2">No fields extracted.</td></tr>
        @endforelse
    </table>
</body>
</html>
