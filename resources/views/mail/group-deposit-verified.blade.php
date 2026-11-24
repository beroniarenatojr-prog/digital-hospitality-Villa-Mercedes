<x-mail::message>
# Group Deposit Payment Verified

Hello {{ $guestName }},

We have verified your deposit payment of **{{ $amountPaid }}** for group booking **{{ $groupCode }}**.

<x-mail::panel>
**Payment Reference:** {{ $referenceNumber }}  
**Amount Verified:** {{ $amountPaid }}  
**Remaining Balance:** {{ $remainingBalance }} (payable upon check in)
</x-mail::panel>

**Reserved Services:**  
@foreach($lines as $line)
- **{{ $line['name'] }}** ({{ $line['code'] }}): {{ $line['schedule'] }} · {{ $line['guests'] }} guest(s)
@endforeach

@if($approved ?? false)
Your group booking is confirmed. Present your booking code or digital check in pass at physical check-in.
@else
Your transfer is verified; your booking still needs a separate staff approval and fresh availability check. Payment verification alone is not booking approval.
@endif

Accepted policy: {{ $policyVersion ?? 'Saved historical terms' }}. For confirmed-practice bookings, cancellation and full refund of verified funds remain eligible before physical check-in, including no-show. Staff receive the request and notify the owner; the owner approves refunds; administrators record that approval and the funds returned. Rescheduling has no notice deadline before physical check-in. Request, approval, and returned funds are separate records.

<x-mail::button :url="$url">
View Group Booking Details
</x-mail::button>

@if($resortPhone)
Questions? Call us at [{{ $resortPhone }}]({{ $resortPhoneHref }}).
@endif

Thanks,<br>
{{ config('resort.short_name') }}
</x-mail::message>
