<!DOCTYPE html>
@php
    /**
     * @var \Modules\Clients\Models\Client $client
     * @var \Modules\Clients\Models\ClientResponsibilityNotice $notice
     * @var \Illuminate\Support\Collection $documents
     *
     * NOTE ON WORDING: the numbered declaration below is the working default and
     * is still pending legal sign-off (same open item as the Tamil agreement).
     * Once the approved wording arrives it replaces the <ol class="terms"> block
     * verbatim - nothing else in this template needs to change. Operator-entered
     * additions ride in $notice->content and never overwrite the fixed clauses.
     */
@endphp
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Client Document Responsibility Notice - {{ $client->reference_no }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', 'Noto Sans', Arial, sans-serif;
        color: #1f2937;
        font-size: 12px;
        line-height: 1.55;
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
    .band {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 14px 0 16px;
    }
    .band .title { font-size: 19px; font-weight: 700; color: #0b3d91; letter-spacing: 0.5px; }
    .band .status {
        display: inline-block;
        padding: 5px 14px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #fff;
        background: {{ $notice->acknowledged ? '#166534' : '#0b3d91' }};
    }
    .meta-grid { display: flex; gap: 16px; margin-bottom: 18px; }
    .meta-grid .card { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
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
    ol.terms { margin: 4px 0 0 16px; padding: 0; }
    ol.terms li { margin-bottom: 7px; }
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
    table.lines td { padding: 7px 10px; border-bottom: 1px solid #eceff3; font-size: 11.5px; }
    .notes { margin-top: 14px; font-size: 11px; color: #4b5563; }
    .notes .box { border-left: 3px solid #b8860b; background: #fffbeb; padding: 8px 12px; border-radius: 0 4px 4px 0; }
    .ack {
        margin-top: 16px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 11px;
        background: {{ $notice->acknowledged ? '#f0fdf4' : '#fafbfc' }};
    }
    .signatures { display: flex; justify-content: space-between; margin-top: 34px; }
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

    <div class="band">
        <div class="title">CLIENT DOCUMENT RESPONSIBILITY NOTICE</div>
        <div class="status">{{ $notice->acknowledged ? 'Acknowledged' : 'Awaiting Acknowledgement' }}</div>
    </div>

    <div class="meta-grid">
        <div class="card">
            <h4>Client</h4>
            <table class="kv">
                <tr><td class="label">Name</td><td class="value">{{ $client->full_name ?? '-' }}</td></tr>
                <tr><td class="label">Client Ref</td><td class="value">{{ $client->reference_no ?? '-' }}</td></tr>
                <tr><td class="label">Passport No</td><td class="value">{{ $client->passport_no ?? '-' }}</td></tr>
                <tr><td class="label">NIC</td><td class="value">{{ $client->nic ?? '-' }}</td></tr>
            </table>
        </div>
        <div class="card">
            <h4>Application</h4>
            <table class="kv">
                <tr><td class="label">Traveling Country</td><td class="value">{{ $client->country ?? '-' }}</td></tr>
                <tr><td class="label">Visa Type</td><td class="value">{{ $client->visa_type ?? '-' }}</td></tr>
                <tr><td class="label">Phone</td><td class="value">{{ $client->phone ?? '-' }}</td></tr>
                <tr><td class="label">Issued</td><td class="value">{{ now()->format('d.m.Y') }}</td></tr>
            </table>
        </div>
    </div>

    <div class="section-title">Declaration &amp; Responsibility</div>
    <ol class="terms">
        <li>
            I confirm that every document and every piece of information I have supplied to Dream Fly Visa
            Consultancy (Pvt) Ltd for this visa application is <strong>genuine, accurate and complete</strong>,
            and that all of it relates to me or to the sponsor/inviter named in my application.
        </li>
        <li>
            I understand that Dream Fly Visa Consultancy prepares and submits my application on the basis of the
            documents I provide, and <strong>does not verify, certify or guarantee the authenticity</strong> of
            documents issued by third parties (banks, employers, authorities, hospitals, institutions or inviters).
        </li>
        <li>
            I accept <strong>full and sole responsibility</strong> for any document later found to be false,
            altered, forged or misleading, including any refusal, cancellation, entry ban, legal action or penalty
            imposed by the embassy, high commission, visa centre or any authority as a result.
        </li>
        <li>
            I understand that the <strong>final decision rests solely with the visa-issuing authority</strong>.
            Dream Fly Visa Consultancy does not promise or guarantee a visa outcome, and consultancy fees are for
            the professional service rendered, not for a particular result.
        </li>
        <li>
            I confirm that original documents handed over have been listed below, and that I will collect them
            back at the close of my case. Dream Fly Visa Consultancy is not responsible for documents retained by
            an embassy or visa centre.
        </li>
        <li>
            I undertake to inform Dream Fly Visa Consultancy immediately of any change in my circumstances,
            travel dates, contact details, or of any prior visa refusal not already disclosed.
        </li>
    </ol>

    @if ($documents->isNotEmpty())
        <div class="section-title">Documents Received From Client</div>
        <table class="lines">
            <thead>
                <tr>
                    <th style="width:6%;">#</th>
                    <th>Document</th>
                    <th style="width:22%;">Provided By</th>
                    <th style="width:20%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $index => $document)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $document['title'] }}</td>
                        <td>{{ $document['owner'] }}</td>
                        <td>{{ $document['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (trim((string) $notice->content) !== '')
        <div class="notes">
            <div class="box">{!! nl2br(e($notice->content)) !!}</div>
        </div>
    @endif

    <div class="ack">
        @if ($notice->acknowledged)
            <strong>Acknowledged by the client</strong> on
            {{ optional($notice->acknowledged_at)->format('d.m.Y H:i') }}
            @if ($notice->acknowledgement_method)
                via {{ str_replace('_', ' ', $notice->acknowledgement_method) }}
            @endif.
            @if (trim((string) $notice->acknowledgement_note) !== '')
                <br>{{ $notice->acknowledgement_note }}
            @endif
        @else
            By signing below, or by confirming receipt in writing through WhatsApp or email, I acknowledge that I
            have read and understood this notice in full.
        @endif
    </div>

    <div class="signatures">
        <div class="box">Client Signature<br>{{ $client->full_name }}</div>
        <div class="box">Authorized Signature<br>P. Kemarupan, Director</div>
    </div>

    <div class="footer">DreamFly Visa Consultancy (Pvt) Ltd &middot; Reg. No: PV00293152 &middot; Generated {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
