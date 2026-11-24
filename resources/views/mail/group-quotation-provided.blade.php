<x-mail::message>
# Quotation for Group Booking {{ $groupCode }}

Hello {{ $guestName }},

Staff at {{ config('resort.name') }} have reviewed your quotation request for group booking **{{ $groupCode }}**.

<x-mail::panel>
**Total Quoted Rate: {{ $quotedTotal }}**  
**Required 50% Down Payment: {{ $depositRequired }}**
</x-mail::panel>

@if(filled($notes))
**Staff Notes & Inclusions:**  
{{ $notes }}
@endif

**Services in your request:**  
@foreach($lines as $line)
- **{{ $line['name'] }}**: {{ $line['schedule'] }} ({{ $line['guests'] }} guest(s))
@endforeach

Accepted policy: {{ $policyVersion ?? 'Historical saved terms' }}.

@if($confirmedPolicy ?? false)
Review the complete quotation and choose at least the 50% down payment or 100% online payment. Proof stays under review until verified; staff approval is still required. Any remaining balance is due at physical check-in.

Before physical check-in, including no-show, verified payments qualify for full refund. Staff receive the request and notify the owner; the owner approves refunds; administrators record that approval and the funds returned. Request, approval, and money returned are separate stages. Rescheduling has no notice deadline before physical check-in, subject to availability, revised quote, and guest acceptance.
@else
This quotation retains its saved prices and accepted terms. Contact staff to review the historical policy before paying.
@endif

<x-mail::button :url="$url">
Review Quote & Pay Deposit
</x-mail::button>

@if($resortPhone)
Questions? Call us at [{{ $resortPhone }}]({{ $resortPhoneHref }}).
@endif

Thanks,<br>
{{ config('resort.short_name') }}
</x-mail::message>
