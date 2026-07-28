<!DOCTYPE html>
<html lang="ta">
<head>
<meta charset="UTF-8">
<title>Service Agreement - {{ $agreement->reference_no }}</title>
<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }
    body {
        /* Nirmala UI/Noto Sans Tamil are proper Unicode-shaped Tamil fonts.
           Latha/Vijaya are legacy TSCII-era fonts kept off this list on purpose -
           they mis-shape conjunct glyphs when fed standard Unicode Tamil text. */
        font-family: 'Noto Sans Tamil', 'Nirmala UI', Arial, sans-serif;
        color: #1f2937;
        font-size: 12px;
        line-height: 1.55;
        margin: 0;
        padding: 24px 36px;
    }
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #0b3d91;
        padding-bottom: 12px;
        margin-bottom: 18px;
    }
    .header .brand-name {
        font-size: 20px;
        font-weight: 700;
        color: #0b3d91;
        margin: 0;
    }
    .header .brand-sub {
        font-size: 11px;
        color: #b8860b;
        font-weight: 600;
        letter-spacing: 1px;
    }
    .header .reg {
        font-size: 10px;
        color: #4b5563;
    }
    .header .contact {
        text-align: right;
        font-size: 10.5px;
        color: #374151;
    }
    h1.title {
        text-align: center;
        font-size: 16px;
        color: #0b3d91;
        margin: 0 0 4px;
    }
    .title-en {
        text-align: center;
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 18px;
    }
    .section-title {
        background: #0b3d91;
        color: #fff;
        padding: 4px 8px;
        font-weight: 700;
        font-size: 12px;
        border-radius: 3px;
        margin: 16px 0 8px;
    }
    table.details { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    table.details td { padding: 3px 4px; vertical-align: top; }
    table.details td.label { width: 34%; color: #4b5563; }
    table.details td.value { font-weight: 600; }
    ul.package { margin: 4px 0 8px 18px; padding: 0; }
    ul.package li { margin-bottom: 3px; }
    table.fees { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.fees td { padding: 5px 6px; border: 1px solid #d1d5db; }
    table.fees td.fee-label { background: #f3f4f6; }
    table.fees td.fee-value { text-align: right; font-weight: 700; width: 30%; }
    .note {
        font-size: 10.5px;
        color: #4b5563;
        margin-top: 10px;
    }
    .signatures {
        display: flex;
        justify-content: space-between;
        margin-top: 40px;
    }
    .signatures .box { width: 45%; border-top: 1px solid #9ca3af; padding-top: 4px; text-align: center; font-size: 10.5px; }
    .footer {
        margin-top: 24px;
        text-align: center;
        font-size: 9.5px;
        color: #9ca3af;
    }
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

    <h1 class="title">சேவை ஒப்பந்தம் (SERVICE AGREEMENT)</h1>
    <p class="title-en">Reference No: {{ $agreement->reference_no }} &nbsp;|&nbsp; Date: {{ $agreement->created_at?->format('d.m.Y') }}</p>

    <div class="section-title">1. வாடிக்கையாளர் (CLIENT)</div>
    <table class="details">
        <tr>
            <td class="label">முழு பெயர் (Full Name)</td>
            <td class="value">{{ $agreement->client_name }}</td>
        </tr>
        <tr>
            <td class="label">முகவரி (Address)</td>
            <td class="value">{{ $agreement->client_address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">மொபைல் எண் (Phone)</td>
            <td class="value">{{ $agreement->client_phone ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">தேசிய அடையாள அட்டை (NIC)</td>
            <td class="value">{{ $agreement->client_nic ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">பாஸ்போர்ட் எண் (Passport No)</td>
            <td class="value">{{ $agreement->client_passport_no ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">மின்னஞ்சல் (Email)</td>
            <td class="value">{{ $agreement->client_email ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">விசா வகை / நாடு (Visa Type / Country)</td>
            <td class="value">{{ $agreement->visa_type ?? '-' }} / {{ $agreement->country ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">2. சேர்க்கப்பட்ட சேவைகள் (CONSULTANCY PACKAGE)</div>
    <ul class="package">
        <li>விசா ஆலோசனை மற்றும் பயண வழிகாட்டுதல்</li>
        <li>ஆவணங்களை சேகரிப்பதற்கான வழிகாட்டுதல்</li>
        <li>தனிப்பயனாக்கப்பட்ட மற்றும் தனித்துவமான விசா ஆவணங்களை தயாரித்தல்</li>
        <li>வழக்கறிஞர் தொடர்பான ஆவணங்கள் (தேவைப்பட்டால்)</li>
        <li>பயண காப்பீடு ஏற்பாடு</li>
        <li>விமான டிக்கெட் முன்பதிவு உதவி</li>
        <li>ஹோட்டல் முன்பதிவு உதவி</li>
        <li>பயண திட்டம் (Itinerary) தயாரித்தல்</li>
        <li>விசா விண்ணப்ப வழிகாட்டுதல் மற்றும் சமர்ப்பித்தல்</li>
        <li>VFS நேரமதிப்பு (Appointment) முன்பதிவு மற்றும் ஒருங்கிணைப்பு</li>
        <li>விசா முடிவு பெறும் வரை உதவி</li>
    </ul>

    <div class="section-title">3. கட்டணங்கள் & செலுத்தல் (FEES & PAYMENTS)</div>
    <table class="fees">
        <tr>
            <td class="fee-label">மொத்த கட்டணம் (Total Service Fee)</td>
            <td class="fee-value">LKR {{ number_format($agreement->total_fee) }}</td>
        </tr>
        <tr>
            <td class="fee-label">முன்பணம் (Advance Paid)</td>
            <td class="fee-value">LKR {{ number_format($agreement->advance_paid) }}</td>
        </tr>
        <tr>
            <td class="fee-label">மீதம் கட்டணம் (Balance)</td>
            <td class="fee-value">LKR {{ number_format($agreement->balance) }}</td>
        </tr>
    </table>
    <p class="note">
        ஆவணங்கள் தயாரித்த பின் விசாவிற்கு விண்ணப்பிக்கும் நேரத்தில் மீதித் தொகை மற்றும் விசா விண்ணப்பத் தொகை
        செலுத்தப்பட வேண்டும். விசா கிடைத்தாலும் கிடைக்காவிட்டாலும், எங்களிடம் கூடுதல் பணம் செலுத்த வேண்டியதில்லை.
    </p>

    <div class="signatures">
        <div class="box">கையொழுத்து (Client)<br>{{ $agreement->client_name }}</div>
        <div class="box">கையொழுத்து (Director)<br>P. Kemarupan</div>
    </div>

    <div class="footer">DreamFly Visa Consultancy (Pvt) Ltd &middot; Reg. No: PV00293152 &middot; Generated {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
