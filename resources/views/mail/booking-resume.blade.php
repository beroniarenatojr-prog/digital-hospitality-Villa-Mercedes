<x-mail::message>
# Finish your booking

Hello {{ $guestName }},

You had a booking ready to confirm and left before it was submitted. Nothing is held, so here is the way back — the dates, the party size and any companion names you filled in are still there.

<x-mail::panel>
**{{ $facility }}**

Check in: {{ $checkIn }}  
Check out: {{ $checkOut }}  
Guests: {{ $guestCount }}
</x-mail::panel>

<x-mail::button :url="$url">
Finish my booking
</x-mail::button>

The link works for {{ $expireHours }} hours. It is not a hold: if someone else has taken those dates, the page will say so and show you what is free instead. The total is calculated again when you open it, so you always confirm the current price.

@if($resortPhone)
Questions? Call us at [{{ $resortPhone }}]({{ $resortPhoneHref }}).
@endif

Thanks,<br>
{{ config('resort.short_name') }}
</x-mail::message>
