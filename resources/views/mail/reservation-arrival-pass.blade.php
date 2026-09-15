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
Total: ₱{{ $totalPrice }}

@if(filled($specialRequests))
**Special requests**  
{{ $specialRequests }}
@endif

Staff can find your reservation with the last 6 characters of the code. You do not need to open the website or sign in.

@if($showPass)
<x-mail::button :url="$passUrl">
Open arrival pass
</x-mail::button>
@endif

@if($resortPhone)
Questions? Call us at [{{ $resortPhone }}]({{ $resortPhoneHref }}).
@endif

Thanks,<br>
{{ config('resort.short_name') }}
</x-mail::message>
