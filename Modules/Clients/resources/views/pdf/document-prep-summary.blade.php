<!DOCTYPE html>
@php
    /**
     * @var \Modules\Clients\Models\Client $client
     * @var \Illuminate\Support\Collection $tasks
     */
@endphp
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Documentation Unit Summary - {{ $client->reference_no }}</title>
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
    .band { display: flex; justify-content: space-between; align-items: center; margin: 14px 0 16px; }
    .band .title { font-size: 19px; font-weight: 700; color: #0b3d91; letter-spacing: 0.5px; }
    .meta-grid { display: flex; gap: 16px; margin-bottom: 18px; }
    .meta-grid .card { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
    .meta-grid .card h4 { margin: 0 0 6px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; }
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
    table.lines td { padding: 7px 10px; border-bottom: 1px solid #eceff3; font-size: 11.5px; }
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
        <div class="title">DOCUMENTATION UNIT SUMMARY</div>
    </div>

    <div class="meta-grid">
        <div class="card">
            <h4>Client</h4>
            <table class="kv">
                <tr><td class="label">Name</td><td class="value">{{ $client->full_name ?? '-' }}</td></tr>
                <tr><td class="label">Client Ref</td><td class="value">{{ $client->reference_no ?? '-' }}</td></tr>
                <tr><td class="label">Traveling Country</td><td class="value">{{ $client->country ?? '-' }}</td></tr>
            </table>
        </div>
        <div class="card">
            <h4>Report</h4>
            <table class="kv">
                <tr><td class="label">Generated</td><td class="value">{{ now()->format('d.m.Y H:i') }}</td></tr>
                <tr><td class="label">Tasks Completed</td><td class="value">{{ $tasks->where('status', 'completed')->count() }} / {{ $tasks->count() }}</td></tr>
            </table>
        </div>
    </div>

    <div class="section-title">Documentation Unit Tasks</div>
    <table class="lines">
        <thead>
            <tr>
                <th style="width:6%;">#</th>
                <th>Task</th>
                <th style="width:18%;">Assigned To</th>
                <th style="width:14%;">Status</th>
                <th style="width:16%;">Due</th>
                <th style="width:20%;">Attached File</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tasks as $index => $task)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $task->title }}@if($task->notes)<br><span style="color:#6b7280;font-size:10px;">{{ $task->notes }}</span>@endif</td>
                    <td>{{ $task->assignedUser?->name ?? $task->assigned_role ?? 'Unassigned' }}</td>
                    <td>{{ str_replace('_', ' ', $task->status) }}</td>
                    <td>{{ $task->due_at?->format('d.m.Y') ?? '-' }}</td>
                    <td>{{ $task->file?->original_name ?? 'None' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No Documentation Unit tasks recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">DreamFly Visa Consultancy (Pvt) Ltd &middot; Reg. No: PV00293152 &middot; Generated {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
