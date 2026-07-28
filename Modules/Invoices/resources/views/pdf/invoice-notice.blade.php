<!DOCTYPE html>
@php
    /** @var \Modules\Invoices\Models\Invoice $invoice */
    $client = $invoice->client;
    $serviceBalance = max(0, (int) $invoice->total_service_fee - (int) $invoice->advance_paid);
    $money = fn ($v) => 'LKR ' . number_format((int) $v);
    $statusLabels = [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'sent' => 'Issued',
        'partial' => 'Partially Paid',
        'partially_paid' => 'Partially Paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
        'waived' => 'Waived',
        'refunded' => 'Refunded',
    ];
    $statusLabel = $statusLabels[$invoice->status] ?? ucfirst((string) $invoice->status);
    $statusColors = [
        'paid' => '#166534',
        'partially_paid' => '#92400e',
        'partial' => '#92400e',
        'overdue' => '#991b1b',
        'cancelled' => '#4b5563',
        'waived' => '#4b5563',
        'refunded' => '#4b5563',
    ];
    $statusColor = $statusColors[$invoice->status] ?? '#0b3d91';
@endphp
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice Notice - {{ $invoice->reference_no }}</title>
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
        margin-bottom: 6px;
    }
    .header .brand-name { font-size: 19px; font-weight: 700; color: #0b3d91; margin: 0; }
    .header .brand-sub { font-size: 10.5px; color: #b8860b; font-weight: 600; letter-spacing: 1px; margin: 2px 0; }
    .header .reg { font-size: 10px; color: #4b5563; }
    .header .contact { text-align: right; font-size: 10.5px; color: #374151; }
    .invoice-band {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 14px 0 16px;
    }
    .invoice-band .title { font-size: 22px; font-weight: 700; color: #0b3d91; letter-spacing: 1px; }
    .invoice-band .status {
        display: inline-block;
        padding: 5px 14px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #fff;
        background: {{ $statusColor }};
    }
    .meta-grid {
        display: flex;
        gap: 16px;
        margin-bottom: 18px;
    }
    .meta-grid .card {
        flex: 1;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px 12px;
    }
    .meta-grid .card h4 {
        margin: 0 0 6px;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #6b7280;
    }
    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 2px 0; vertical-align: top; font-size: 11px; }
    table.kv td.label { color: #6b7280; width: 42%; }
    table.kv td.value { font-weight: 600; color: #111827; }
    .section-title {
        background: #0b3d91;
        color: #fff;
        padding: 5px 10px;
        font-weight: 700;
        font-size: 11.5px;
        border-radius: 3px;
        margin: 14px 0 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    table.lines { width: 100%; border-collapse: collapse; }
    table.lines th {
        background: #f3f4f6;
        color: #374151;
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 7px 10px;
        border-bottom: 2px solid #d1d5db;
    }
    table.lines th.num, table.lines td.num { text-align: right; }
    table.lines td { padding: 7px 10px; border-bottom: 1px solid #eceff3; font-size: 11.5px; }
    table.lines tr.group td { background: #fafbfc; font-weight: 600; color: #0b3d91; }
    .summary {
        margin-top: 14px;
        margin-left: auto;
        width: 60%;
        border-collapse: collapse;
    }
    .summary td { padding: 6px 10px; font-size: 12px; }
    .summary td.label { color: #4b5563; text-align: right; }
    .summary td.value { text-align: right; font-weight: 700; width: 40%; }
    .summary tr.total td {
        border-top: 2px solid #0b3d91;
        font-size: 14px;
        color: #0b3d91;
    }
    .summary tr.balance td {
        background: {{ $invoice->balance > 0 ? '#fef2f2' : '#f0fdf4' }};
        color: {{ $invoice->balance > 0 ? '#991b1b' : '#166534' }};
        font-size: 13px;
    }
    .notes { margin-top: 16px; font-size: 11px; color: #4b5563; }
    .notes .box { border-left: 3px solid #b8860b; background: #fffbeb; padding: 8px 12px; border-radius: 0 4px 4px 0; }
    .payinfo { margin-top: 12px; font-size: 10.5px; color: #4b5563; }
    .signatures { display: flex; justify-content: flex-end; margin-top: 34px; }
    .signatures .box { width: 42%; border-top: 1px solid #9ca3af; padding-top: 4px; text-align: center; font-size: 10.5px; }
    .footer { margin-top: 22px; text-align: center; font-size: 9.5px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
</style>
</head>
<body>
    <div class="header">
        <div>
            <p class="brand-name">DREAM FLY VISA CONSULTANCY (PVT) LTD</p>
            <p class="brand-sub">DREAMFLY VISA CONSULTANCY</p>
            <p class="reg">Reg. No: PV00293152</p>
        </div>
        <div class="contact">
            +94-76-227-5432<br>
            dreamflyaz@gmail.com<br>
            Main Street, Saaraiyady, Point Pedro.
        </div>
    </div>

    <div class="invoice-band">
        <div class="title">INVOICE NOTICE</div>
        <div class="status">{{ $statusLabel }}</div>
    </div>

    <div class="meta-grid">
        <div class="card">
            <h4>Billed To</h4>
            <table class="kv">
                <tr><td class="label">Name</td><td class="value">{{ $client?->full_name ?? '-' }}</td></tr>
                <tr><td class="label">Passport No</td><td class="value">{{ $client?->passport_no ?? '-' }}</td></tr>
                <tr><td class="label">Traveling Country</td><td class="value">{{ $client?->country ?? '-' }}</td></tr>
                <tr><td class="label">Phone</td><td class="value">{{ $client?->phone ?? '-' }}</td></tr>
            </table>
        </div>
        <div class="card">
            <h4>Invoice Details</h4>
            <table class="kv">
                <tr><td class="label">Reference No</td><td class="value">{{ $invoice->reference_no }}</td></tr>
                <tr><td class="label">Client Ref</td><td class="value">{{ $client?->reference_no ?? '-' }}</td></tr>
                <tr><td class="label">Issue Date</td><td class="value">{{ optional($invoice->issue_date)->format('d.m.Y') ?? '-' }}</td></tr>
                <tr><td class="label">Due Date</td><td class="value">{{ optional($invoice->due_date)->format('d.m.Y') ?? '-' }}</td></tr>
            </table>
        </div>
    </div>

    <div class="section-title">Charges</div>
    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr class="group">
                <td colspan="4">Service Fee</td>
            </tr>
            <tr>
                <td>Total Service Fee</td>
                <td class="num">1</td>
                <td class="num">{{ $money($invoice->total_service_fee) }}</td>
                <td class="num">{{ $money($invoice->total_service_fee) }}</td>
            </tr>
            <tr>
                <td>Less: Advance Already Paid</td>
                <td class="num">-</td>
                <td class="num">-</td>
                <td class="num">- {{ $money($invoice->advance_paid) }}</td>
            </tr>
            <tr>
                <td>Service Balance</td>
                <td class="num">-</td>
                <td class="num">-</td>
                <td class="num">{{ $money($serviceBalance) }}</td>
            </tr>

            <tr class="group">
                <td colspan="4">Visa &amp; Appointment Fees</td>
            </tr>
            <tr>
                <td>Visa Application Fee</td>
                <td class="num">1</td>
                <td class="num">{{ $money($invoice->application_fee) }}</td>
                <td class="num">{{ $money($invoice->application_fee) }}</td>
            </tr>
            <tr>
                <td>VFS Appointment Fee</td>
                <td class="num">1</td>
                <td class="num">{{ $money($invoice->vfs_fee) }}</td>
                <td class="num">{{ $money($invoice->vfs_fee) }}</td>
            </tr>

            @if ($invoice->items->isNotEmpty())
                <tr class="group">
                    <td colspan="4">Additional Charges</td>
                </tr>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->description }}@if ($item->category) <span style="color:#9ca3af;">({{ $item->category }})</span>@endif</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ $money($item->unit_price) }}</td>
                        <td class="num">{{ $money($item->amount + $item->tax) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>

    <table class="summary">
        <tr class="total">
            <td class="label">Total Amount Payable</td>
            <td class="value">{{ $money($invoice->total_payable) }}</td>
        </tr>
        <tr>
            <td class="label">Amount Paid</td>
            <td class="value">{{ $money($invoice->paid_amount) }}</td>
        </tr>
        <tr class="balance">
            <td class="label">Balance Due</td>
            <td class="value">{{ $money($invoice->balance) }}</td>
        </tr>
    </table>

    @if ($invoice->payments->isNotEmpty())
        <div class="section-title">Payment History</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td>{{ optional($payment->paid_at)->format('d.m.Y') ?? '-' }}</td>
                        <td>{{ $payment->method ?? '-' }}</td>
                        <td>{{ $payment->reference ?? '-' }}</td>
                        <td class="num">{{ $money($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (trim((string) $invoice->notes) !== '')
        <div class="notes">
            <div class="box">{!! nl2br(e($invoice->notes)) !!}</div>
        </div>
    @endif

    <p class="payinfo">
        Kindly arrange the above payment to proceed with your visa application. Once documents are prepared, the
        balance and the visa application fee must be settled at the time of applying for the visa. Whether or not the
        visa is granted, no additional consultancy payment is required.
    </p>

    <div class="signatures">
        <div class="box">Authorized Signature<br>P. Kemarupan, Director</div>
    </div>

    <div class="footer">DreamFly Visa Consultancy (Pvt) Ltd &middot; Reg. No: PV00293152 &middot; Generated {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
