@props(['facility', 'initial' => [], 'prefix' => 'schedule', 'errorExpression' => 'null'])
@php
    $policy = app(\App\Services\ReservationSchedule::class)->configuration($facility);
    $mode = $policy['mode'];
@endphp
<div x-data="reservationSchedule(@js($policy), @js($initial))" class="space-y-4" data-reservation-schedule="{{ $mode }}">
    @if($mode === 'room')
        <aside x-show="!sameDay" class="flex gap-3 rounded-xl border border-primary/25 bg-primary/5 p-4" role="note" aria-label="Important room schedule notice">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" d="M12 10v6m0-9h.01" />
            </svg>
            <div class="text-sm text-text">
                <p class="font-semibold text-primary">Important: standard room times</p>
                <p class="mt-1">Your room stay begins at <strong>2:00 PM on your check in date</strong> and ends at <strong>12:00 PM (noon) on your check out date</strong>. Please choose your dates with these times in mind. Early check-in is a request only and remains subject to availability.</p>
            </div>
        </aside>
    @elseif($mode === 'package')
        <p class="text-sm text-text-muted"><strong>{{ $facility->stay_category->label() }}</strong> · {{ $facility->stayScheduleLabel() }}. These package hours are fixed.</p>
    @elseif($mode === 'hall')
        <p class="text-sm text-text-muted">Four standard hours. Your event, including extensions, must be within 9 AM–10 PM.</p>
    @endif
    <p x-show="scheduleMessage" x-cloak x-text="scheduleMessage" class="text-sm text-warning" role="status"></p>
    @if($mode === 'room')
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="sameDay" @change="earlyHours = 0; sync()"> Same-day room use · one full base rate, no automatic early or late fee</label>
        <div x-show="sameDay" class="grid grid-cols-2 gap-4">
            <label>Check in time<input type="time" x-model="startTime" @change="sync()" class="admin-input"></label>
            <label>Check out time<input type="time" x-model="roomEndTime" @change="sync()" class="admin-input"></label>
        </div>
    @endif
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input-label :for="$prefix.'-date'" :value="$facility->isFunctionHall() ? 'Event date' : 'Check in date'" />
            <x-text-input :id="$prefix.'-date'" type="date" x-model="visitDate" @change="sync()" :min="$policy['today']" required class="mt-2 block w-full min-h-11" />
        </div>
        @if($policy['overnight'])
            <div @if($mode === 'room') x-show="!sameDay" @endif>
                <x-input-label :for="$prefix.'-departure'" value="Check out date" />
                <x-text-input x-bind:disabled="sameDay" :id="$prefix.'-departure'" type="date" x-model="departureDate" @change="sync()" x-bind:min="visitDate" required class="mt-2 block w-full min-h-11" />
            </div>
        @endif
        @if($mode === 'opal')
            <div>
                <x-input-label :for="$prefix.'-slot'" value="Opal Hall schedule" />
                <select id="{{ $prefix }}-slot" x-model="slot" @change="sync()" class="mt-2 block w-full min-h-11 rounded-lg border-border bg-surface text-text focus:border-primary focus:ring-primary">
                    <option value="day">Day slot · 9 AM–5 PM</option>
                    <option value="night">Night slot · 5 PM–10 PM</option>
                </select>
            </div>
        @elseif($mode === 'hall')
            <div>
                <x-input-label :for="$prefix.'-start'" value="Event start" />
                <x-text-input :id="$prefix.'-start'" type="time" x-model="startTime" @change="sync()" :min="$policy['hallOpen']" x-bind:max="latestStart" step="1800" required class="mt-2 block w-full min-h-11" />
            </div>
            @if($policy['extensionHourCents'] > 0)
                <div>
                    <x-input-label :for="$prefix.'-extension'" value="Hall extension (hours)" />
                    <select id="{{ $prefix }}-extension" x-model.number="extensionHours" @change="sync()" class="mt-2 block w-full min-h-11 rounded-lg border-border bg-surface text-text focus:border-primary focus:ring-primary">
                        <template x-for="hours in Array.from({length: maxExtension + 1}, (_, i) => i)" :key="hours">
                            <option :value="hours" :selected="hours === Number(extensionHours)" x-text="hours === 0 ? 'No extension' : hours + (hours === 1 ? ' hour' : ' hours')"></option>
                        </template>
                    </select>
                    <p class="mt-2 text-sm text-text-muted"><x-amount :value="$policy['extensionHourCents'] / 100" /> per extension hour.</p>
                </div>
            @endif
            <p class="self-center text-sm text-text">Event ends at <strong x-text="endLabel"></strong>.</p>
        @elseif($mode === 'room')
            <div x-show="!sameDay">
                <x-input-label :for="$prefix.'-early'" value="Early check-in request" />
                <select x-bind:disabled="sameDay" id="{{ $prefix }}-early" x-model.number="earlyHours" @change="sync()" aria-describedby="{{ $prefix }}-early-note {{ $prefix }}-error" :aria-invalid="earlyHours > 0 && !!({{ $errorExpression }})" :class="earlyHours > 0 && ({{ $errorExpression }}) ? 'border-danger ring-1 ring-danger' : ''" class="mt-2 block w-full min-h-11 rounded-lg border-border bg-surface text-text focus:border-primary focus:ring-primary">
                    <option value="0">Standard · 2 PM</option>
                    <option value="1">1 hour early · 1 PM</option>
                    <option value="2">2 hours early · 12 PM</option>
                </select>
                <p id="{{ $prefix }}-early-note" class="mt-2 text-sm text-text-muted">Subject to availability. Unavailable if another reservation occupies the room during those hours.</p>
                @php($earlyRate = app(\App\Services\PricingService::class)->hourlyChargeAmount($facility, \App\Services\PricingService::EARLY_CHECK_IN_RATE_NAME))
                @if($earlyRate !== null)
                    <p class="mt-1 text-sm text-text-muted"><x-amount :value="$earlyRate" /> per early check-in hour.</p>
                @endif
            </div>
        @endif
    </div>
    @if($facility->isFunctionHall())
        <p class="text-sm text-text-muted">Staff begin clearing the venue 30 minutes before your booked event ends.</p>
    @endif
    <p class="text-sm text-text-muted">Check out beyond the booked time may incur an additional fee. Put any late check out request in notes; it requires staff approval.</p>
    <p id="{{ $prefix }}-error" x-show="{{ $errorExpression }}" x-cloak x-text="{{ $errorExpression }}" class="text-sm text-danger" role="alert"></p>
</div>
