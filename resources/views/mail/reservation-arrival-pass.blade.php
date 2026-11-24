<x-mail::message>
# Your reservation copy

Hello {{ $guestName }},

{{ $intro }}

<x-mail::panel>
**Reservation code**

{{ $code }}
</x-mail::panel>

**Stay details**  
Facility: {{ $facility }}  
Check-in: {{ $checkIn }}  
Check-out: {{ $checkOut }}  
Guests: {{ $guestCount }}  
@if($earlyHours > 0)
Early check-in: {{ $earlyHours }} hour(s)  
@endif
@if($lateHours > 0)
Late check-out: {{ $lateHours }} hour(s)  
@endif
Status: {{ $status }}  
Total: {{ $totalPrice }}  
Verified paid: {{ $verifiedPaid ?? 'See booking record' }}  
Remaining balance due at physical check-in: {{ $remainingBalance ?? 'See booking record' }}  
Accepted policy: {{ $policyVersion ?? 'Historical saved terms' }}

@if($confirmedPolicy ?? false)
Before physical check-in, including no-show, verified payments qualify for full refund. Staff receive requests and notify the owner; the owner approves refunds; administrators record that approval and the funds returned. Request, approval, and return are separate stages. Rescheduling has no notice deadline before physical check-in, subject to staff checks and guest acceptance of revised quotes.
@endif

@if(filled($specialRequests))
**Special requests**  
{{ $specialRequests }}
@endif

Staff can find your reservation with the last 6 characters of the code. You do not need to open the website or sign in.

@if($showPass)
<x-mail::button :url="$passUrl">
Open check in pass
</x-mail::button>
@endif

@if($resortPhone)
Questions? Call us at [{{ $resortPhone }}]({{ $resortPhoneHref }}).
@endif

Thanks,<br>
{{ config('resort.short_name') }}
</x-mail::message>
