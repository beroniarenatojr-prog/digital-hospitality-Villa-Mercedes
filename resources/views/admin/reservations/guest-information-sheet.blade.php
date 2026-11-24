<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Guest Information Sheet</title>
    <style>
        @page { margin: 0.48in 0.62in 0.5in; }
        * { box-sizing: border-box; }
        body {
            color: #24252a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            line-height: 1.24;
            margin: 0;
        }
        .sheet { width: 100%; }
        .brand { text-align: center; }
        .brand-lockup {
            border-collapse: collapse;
            margin: 0 auto 8px;
        }
        .brand-lockup td { padding: 0; vertical-align: middle; }
        .brand-mark { height: 0.68in; width: 1.15in; object-fit: contain; }
        .brand-copy { padding-left: 12px !important; text-align: left; white-space: nowrap; }
        .brand-name {
            color: #343239;
            font-size: 26pt;
            font-weight: 400;
            letter-spacing: 0.025em;
            line-height: 0.95;
        }
        .brand-name .villa { color: #704140; }
        .tagline {
            color: #27262c;
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.25em;
            margin-top: 6px;
            text-align: center;
        }
        .contact { font-size: 10pt; margin-top: 3px; }
        .email { color: #496b8c; text-decoration: underline; }
        .accreditation {
            font-size: 10pt;
            font-style: italic;
            font-weight: bold;
            margin-top: 5px;
        }
        .meta {
            border-collapse: collapse;
            margin-top: 34px;
            width: 100%;
        }
        .meta td { padding: 0; width: 50%; }
        .meta .right { text-align: right; }
        .meta-value {
            border-bottom: 1px solid #252832;
            display: inline-block;
            font-weight: bold;
            min-width: 0.64in;
            padding: 0 4px 2px;
            text-align: center;
        }
        .meta-date { min-width: 1.78in; }
        .form-title {
            color: #cf3659;
            font-size: 17pt;
            font-weight: bold;
            letter-spacing: 0.02em;
            margin: 39px 0 28px;
            text-align: center;
        }
        .field-pair,
        .entry {
            border-collapse: collapse;
            table-layout: auto;
            width: 100%;
        }
        .field-pair > tbody > tr > td { padding: 0 0 9px; vertical-align: bottom; }
        .field-pair .left-field { padding-right: 24px; width: 58%; }
        .field-pair .right-field { padding-left: 12px; width: 42%; }
        .entry .field-label {
            padding: 0 6px 2px 0;
            white-space: nowrap;
            width: 1%;
        }
        .entry .field-value {
            border-bottom: 1px solid #252832;
            height: 18px;
            overflow: hidden;
            padding: 0 4px 2px;
            vertical-align: bottom;
            white-space: nowrap;
        }
        .full-field { margin-bottom: 9px; }
        .section-title { font-size: 12pt; margin: 34px 0 8px; }
        .companion { border-collapse: collapse; display: table; margin: 5px 0; width: 100%; }
        .companion-number {
            display: table-cell;
            padding-right: 8px;
            text-align: right;
            vertical-align: bottom;
            width: 0.38in;
        }
        .companion-line {
            border-bottom: 1px solid #252832;
            display: table-cell;
            height: 19px;
            overflow: hidden;
            padding: 0 5px 2px;
            vertical-align: bottom;
            white-space: nowrap;
        }
        .overflow { font-size: 8pt; margin: 6px 0 0 0.38in; }
        .footer-note { font-size: 10pt; margin: 33px 0 0 0.2in; }
        .thanks { display: block; font-size: 10.5pt; margin-top: 8px; }
        .signature { margin: 34px 0 0 auto; text-align: center; width: 2.5in; }
        .signature-line { border-bottom: 1px solid #252832; height: 18px; }
        .signature-label { font-size: 10pt; margin-top: 4px; }
    </style>
</head>
<body>
    <main class="sheet">
        <header class="brand">
            <table class="brand-lockup" role="presentation">
                <tr>
                    @if ($logoDataUri)
                        <td><img class="brand-mark" src="{{ $logoDataUri }}" alt=""></td>
                    @endif
                    <td class="brand-copy">
                        <div class="brand-name"><span class="villa">VILLA</span> MERCEDES</div>
                        <div class="tagline">NATURE FARM &amp; RESORT</div>
                    </td>
                </tr>
            </table>
            <div class="contact">{{ $documentDetails['location'] }}</div>
            <div class="contact">Contact Nos. {{ $documentDetails['contacts'] }}</div>
            <div class="contact email">{{ $documentDetails['email'] }}</div>
            <div class="accreditation">({{ $documentDetails['accreditation'] }})</div>
        </header>

        <table class="meta" role="presentation">
            <tr>
                <td>Control #: <span class="meta-value">{{ $reservation->guest_sheet_control_number }}</span></td>
                <td class="right">Date: <span class="meta-value meta-date">{{ $reservation->check_in->format('F j, Y') }}</span></td>
            </tr>
        </table>

        <h1 class="form-title">GUEST INFORMATION SHEET</h1>

        <section class="fields">
            @php
                $fieldRows = [
                    ['Date:', $reservation->check_in->format('F j, Y'), 'Valid ID#:', $guest['valid_id']],
                    ['Name:', $guest['name'], 'Gender:', $guest['gender']],
                    ['Place of Origin:', $guest['place_of_origin'], 'Contact #:', $guest['phone']],
                    ['Nationality:', $guest['nationality'], 'Email Add:', $guest['email']],
                ];
            @endphp

            @foreach ($fieldRows as [$leftLabel, $leftValue, $rightLabel, $rightValue])
                <table class="field-pair" role="presentation">
                    <tr>
                        <td class="left-field">
                            <table class="entry" role="presentation"><tr>
                                <td class="field-label">{{ $leftLabel }}</td>
                                <td class="field-value">{{ $leftValue ?: ' ' }}</td>
                            </tr></table>
                        </td>
                        <td class="right-field">
                            <table class="entry" role="presentation"><tr>
                                <td class="field-label">{{ $rightLabel }}</td>
                                <td class="field-value">{{ $rightValue ?: ' ' }}</td>
                            </tr></table>
                        </td>
                    </tr>
                </table>
            @endforeach

            <table class="entry full-field" role="presentation"><tr>
                <td class="field-label">Company Name/Agency:</td>
                <td class="field-value">{{ $guest['company'] ?: ' ' }}</td>
            </tr></table>
            <table class="entry full-field" role="presentation"><tr>
                <td class="field-label">Room #:</td>
                <td class="field-value">{{ $reservation->facility?->name ?: ' ' }}</td>
            </tr></table>
        </section>

        @if($reservation->late_check_out_hours > 0)
            <p>Approved check out: {{ $reservation->occupancyEnd()->format('F j, Y g:i A') }} Philippine time ({{ $reservation->late_check_out_hours }} late hour(s)).</p>
        @endif

        <section>
            <h2 class="section-title">Other Guest Names:</h2>
            @for ($i = 0; $i < 5; $i++)
                <div class="companion">
                    <div class="companion-number">{{ $i + 1 }}</div>
                    <div class="companion-line">{{ $companions[$i] ?? '' }}</div>
                </div>
            @endfor
            @if (count($companions) > 5)
                <p class="overflow"><strong>Additional guests:</strong> {{ implode(', ', array_slice($companions, 5)) }}</p>
            @endif
        </section>

        <p class="footer-note">This information Form is for the Hotel's Record - filing and security purpose.<span class="thanks">Thank you!</span></p>

        <div class="signature">
            <div class="signature-line"></div>
            <div class="signature-label">Signature</div>
        </div>
    </main>
</body>
</html>
