<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Booking Summary · {{ $group->group_code }}</title>
    <style>
        @page { margin: 0.5in; size: portrait; }
        * { box-sizing: border-box; }
        body {
            color: #24252a;
            font-family: DejaVu Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 10pt;
            line-height: 1.35;
            margin: 0;
            padding: 20px;
        }
        .header { text-align: center; border-bottom: 2px solid #24252a; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 18pt; font-weight: bold; margin: 4px 0; color: #704140; }
        .subtitle { font-size: 11pt; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .meta-grid { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 9.5pt; }
        .meta-box { border: 1px solid #d0d2d6; border-radius: 6px; padding: 10px; width: 48%; }
        .meta-box h3 { margin: 0 0 6px; font-size: 10pt; text-transform: uppercase; color: #555; }
        table.services-table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 9.5pt; }
        table.services-table th, table.services-table td { border: 1px solid #d0d2d6; padding: 8px 10px; text-align: left; }
        table.services-table th { background: #f2f4f7; font-weight: bold; text-transform: uppercase; font-size: 8.5pt; }
        .text-right { text-align: right; }
        .financial-summary { margin-top: 18px; margin-left: auto; width: 45%; border-collapse: collapse; }
        .financial-summary td { padding: 4px 8px; }
        .financial-summary tr.total td { font-weight: bold; font-size: 11pt; border-top: 2px solid #24252a; }
        .footer { margin-top: 30px; font-size: 8.5pt; color: #666; border-top: 1px solid #d0d2d6; padding-top: 8px; text-align: center; }
        .btn-print { margin-bottom: 16px; padding: 8px 16px; background: #704140; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        @media print {
            .btn-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button type="button" class="btn-print" onclick="window.print()">Print Document</button>

    <div class="header">
        <div class="title">{{ config('resort.name', 'Villa Mercedes') }}</div>
        <div class="subtitle">Grouped Booking Summary &amp; Operations Sheet</div>
        <p style="margin: 4px 0; font-size: 9pt; color: #555;">Official Front Desk Operational Document</p>
    </div>

    <div class="meta-grid">
        <div class="meta-box">
            <h3>Booking Reference</h3>
            <p><strong>Group Code:</strong> {{ $group->group_code }}</p>
            <p><strong>Status:</strong> {{ $group->status->label() }}</p>
            <p><strong>Booking Source:</strong> {{ $group->source->label() }}</p>
            <p><strong>Date Filed:</strong> {{ $group->created_at->format('M d, Y g:i A') }}</p>
        </div>

        <div class="meta-box">
            <h3>Guest Information</h3>
            <p><strong>Name:</strong> {{ $group->guest_name }}</p>
            <p><strong>Email:</strong> {{ $group->guest_email }}</p>
            <p><strong>Phone:</strong> {{ $group->guest_phone }}</p>
            <p><strong>Origin / Address:</strong> {{ $group->guest_place_of_origin ?: 'On record' }}</p>
        </div>
    </div>

    @if($group->reservations->isNotEmpty())
        <div style="background: #f7f8fa; border: 1px solid #e2e4e8; border-radius: 6px; padding: 8px 12px; margin-bottom: 16px; font-size: 9.5pt;">
            <strong>Overall Visit Window:</strong>
            {{ $group->earliestCheckIn()?->format('F d, Y g:i A') }} to {{ $group->latestCheckOut()?->format('F d, Y g:i A') }}
        </div>
    @endif

    <table class="services-table">
        <thead>
            <tr>
                <th>Service / Space</th>
                <th>Schedule Details</th>
                <th>Guests</th>
                <th>Operational Status</th>
                <th class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($group->reservations as $reservation)
                <tr>
                    <td>
                        <strong>{{ $reservation->facility->name }}</strong><br>
                        <span style="font-size: 8.5pt; color: #666;">{{ $reservation->facility->facilityType?->name }}</span>
                    </td>
                    <td>
                        {{ $reservation->check_in->format('M d, Y g:i A') }} to<br>
                        {{ $reservation->check_out->format('M d, Y g:i A') }}
                    </td>
                    <td>{{ $reservation->guest_count }} guests</td>
                    <td>
                        <span style="text-transform: uppercase; font-size: 8.5pt; font-weight: bold;">
                            {{ $reservation->status->label() }}
                        </span>
                        @if($reservation->checked_in_at)
                            <br><span style="font-size: 8pt; color: #2e7d32;">In: {{ $reservation->checked_in_at->format('M d, Y g:i A') }}</span>
                        @endif
                    </td>
                    <td class="text-right font-bold">
                        <x-amount :value="$reservation->total_price" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="financial-summary">
        <tr>
            <td>Group Quoted Total:</td>
            <td class="text-right"><x-amount :value="$group->total_amount" /></td>
        </tr>
        <tr>
            <td>Required 50% Deposit:</td>
            <td class="text-right"><x-amount :value="$group->deposit_required ?? ($group->total_amount / 2)" /></td>
        </tr>
        <tr>
            <td>Verified Paid:</td>
            <td class="text-right" style="color: #2e7d32;"><x-amount :value="$group->verifiedPaymentsTotal()" /></td>
        </tr>
        <tr class="total">
            <td>Outstanding Balance:</td>
            <td class="text-right"><x-amount :value="$group->outstandingBalance()" /></td>
        </tr>
    </table>

    @if($group->admin_notes)
        <div style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 10px; font-size: 9pt;">
            <strong>Internal Staff Notes:</strong> {{ $group->admin_notes }}
        </div>
    @endif

    <div class="footer">
        Villa Mercedes Nature Farm &amp; Resort · Front Desk Management · Generated {{ now()->format('F d, Y g:i A') }} (PHT)
    </div>
</body>
</html>
