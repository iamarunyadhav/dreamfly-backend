<!DOCTYPE html>
@php
    /** @var \Modules\Finance\Models\DailyClosing $closing */
    $money = fn ($v) => 'LKR ' . number_format((int) $v);
@endphp
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Daily Closing - {{ $closing->closing_date->toDateString() }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; padding: 28px 36px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0b3d91; padding-bottom: 12px; }
    .brand-name { font-size: 18px; font-weight: 700; color: #0b3d91; margin: 0; }
    .brand-sub { font-size: 10px; color: #b8860b; font-weight: 600; letter-spacing: 1px; }
    .title-band { display: flex; justify-content: space-between; align-items: center; margin: 16px 0; }
    .title { font-size: 20px; font-weight: 700; color: #0b3d91; letter-spacing: 1px; }
    .status { padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #fff; background: {{ $closing->status === 'closed' ? '#166534' : '#92400e' }}; }
    table.figures { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.figures td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 13px; }
    table.figures td.label { background: #f8fafc; color: #4b5563; width: 60%; }
    table.figures td.value { text-align: right; font-weight: 700; }
    tr.total td { border-top: 2px solid #0b3d91; font-size: 15px; color: #0b3d91; }
    tr.variance td { background: {{ (int) $closing->variance === 0 ? '#f0fdf4' : '#fef2f2' }}; color: {{ (int) $closing->variance === 0 ? '#166534' : '#991b1b' }}; }
    .meta { margin-top: 16px; font-size: 11px; color: #4b5563; }
    .footer { margin-top: 28px; text-align: center; font-size: 9.5px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
    <div class="header">
        <div>
            <p class="brand-name">DREAM FLY VISA CONSULTANCY (PVT) LTD</p>
            <p class="brand-sub">DREAMFLY VISA CONSULTANCY</p>
        </div>
        <div style="text-align:right; font-size:10.5px; color:#374151;">Reg. No: PV00293152</div>
    </div>

    <div class="title-band">
        <div class="title">DAILY CLOSING</div>
        <div class="status">{{ $closing->status }}</div>
    </div>

    <p style="font-size:13px; font-weight:600;">Date: {{ $closing->closing_date->format('d.m.Y') }}</p>

    <table class="figures">
        <tr><td class="label">Opening Balance</td><td class="value">{{ $money($closing->opening_balance) }}</td></tr>
        <tr><td class="label">Total Income</td><td class="value">{{ $money($closing->income_total) }}</td></tr>
        <tr><td class="label">Total Expense</td><td class="value">{{ $money($closing->expense_total) }}</td></tr>
        <tr><td class="label">Cash Movement (net)</td><td class="value">{{ $money($closing->cash_total) }}</td></tr>
        <tr><td class="label">Bank Movement (net)</td><td class="value">{{ $money($closing->bank_total) }}</td></tr>
        <tr class="total"><td class="label">Closing Balance</td><td class="value">{{ $money($closing->closing_balance) }}</td></tr>
        <tr><td class="label">Counted Cash</td><td class="value">{{ $closing->counted_cash !== null ? $money($closing->counted_cash) : '-' }}</td></tr>
        <tr class="variance"><td class="label">Cash Variance</td><td class="value">{{ $money($closing->variance) }}</td></tr>
    </table>

    @if (trim((string) $closing->notes) !== '')
        <p class="meta"><strong>Notes:</strong> {{ $closing->notes }}</p>
    @endif

    <p class="meta">
        Closed by user #{{ $closing->closed_by ?? '-' }} on {{ optional($closing->closed_at)->format('d.m.Y H:i') ?? '-' }}.
        @if ($closing->reopen_reason)
            <br>Reopened: {{ $closing->reopen_reason }} ({{ optional($closing->reopened_at)->format('d.m.Y H:i') }}).
        @endif
    </p>

    <div class="footer">DreamFly Visa Consultancy (Pvt) Ltd &middot; Generated {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
