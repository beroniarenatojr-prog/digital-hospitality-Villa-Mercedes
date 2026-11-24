<x-customer-layout>
    <x-slot name="title">Review quotation · {{ config('resort.name') }}</x-slot>
    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <a href="{{ route('customer.reservations.index', ['group' => $group->group_code]) }}" class="text-sm font-semibold text-primary hover:underline">← My reservations</a>
        <h1 class="mt-5 font-heading text-3xl font-semibold text-text">Review your quotation</h1>
        <p class="mt-2 text-text-muted">Request {{ $group->group_code }} · Staff has supplied an exact price for your selected services. Your request still needs resort approval and payment verification.</p>

        @if($errors->any())
            <div role="alert" class="mt-6 rounded-xl border border-danger bg-danger-light p-4 text-sm text-danger">
                <p class="font-semibold">Please correct these details:</p>
                <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="mt-7 rounded-2xl border border-border bg-surface p-5 sm:p-7 shadow-sm">
            <h2 class="font-heading text-xl font-semibold">Selected services</h2>
            <ul class="mt-3 divide-y divide-border">
                @foreach($group->reservations as $reservation)
                    <li class="py-3 flex flex-wrap justify-between gap-2 text-sm"><span class="font-medium">{{ $reservation->facility?->name ?? 'Service' }}</span><span class="text-text-muted">{{ $reservation->check_in?->timezone(config('resort.timezone'))->format('M d, Y g:i A') }} – {{ $reservation->check_out?->timezone(config('resort.timezone'))->format('M d, Y g:i A') }}</span></li>
                @endforeach
            </ul>
            <div class="mt-3 border-t border-border pt-4 flex justify-between font-semibold"><span>Quoted total</span><x-amount :value="$group->total_amount" /></div>
        </div>

        <form method="POST" action="{{ route('customer.grouped-booking.quote-deposit', $group) }}" enctype="multipart/form-data" class="mt-6 space-y-6 rounded-2xl border border-border bg-surface p-5 sm:p-7 shadow-sm">
            @csrf
            <input type="hidden" name="policy_version" value="{{ $group->policy_version }}">
            <section aria-labelledby="policy-heading">
                <h2 id="policy-heading" class="font-heading text-xl font-semibold">Before you pay</h2>
                @if($group->policy_version === config('resort.policy.confirmed_version'))
                <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-text-muted">
                    <li>Your proof stays <strong>Under review</strong> until staff verify the transfer. Your booking remains a request until the resort accepts it.</li>
                    <li>Verified payments are fully refundable before physical check-in, including no-show. The owner approves refunds; administrators record that approval and the funds returned.</li>
                    <li>Rescheduling has no notice deadline before physical check-in. Staff confirm availability and price differences; guest acceptance is required for replacements.</li>
                    <li>If the resort declines or cannot honor your request, received funds are refundable in full.</li>
                </ul>
                @else
                    <p class="text-sm">This quotation retains accepted policy {{ $group->policy_version }} and its saved amounts. Contact staff to review the historical terms before paying; current published terms do not replace them.</p>
                @endif
                <a class="mt-3 inline-block text-sm font-semibold text-primary underline" href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer">Read the complete resort terms</a>
            </section>

            @if($group->policy_version === config('resort.policy.confirmed_version'))
                <details class="rounded-xl border border-border p-4 text-sm">
                    <summary class="cursor-pointer font-semibold">Full booking and rescheduling terms</summary>
                    <div class="mt-3">@include('pages.partials.confirmed-booking-policy')</div>
                </details>
            @endif
            @if($destinations->isNotEmpty())
                <fieldset class="space-y-2">
                    <legend class="mb-2 font-semibold">Choose how much to pay</legend>
                    <label class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 text-sm">
                        <input type="radio" name="payment_percent" value="50" @checked(old('payment_percent', 50) == 50) required>
                        <span>50% down payment</span>
                        <strong class="ml-auto"><x-amount :value="$group->deposit_required" /></strong>
                    </label>
                    <label class="flex min-h-12 items-center gap-3 rounded-lg border border-border px-3 text-sm">
                        <input type="radio" name="payment_percent" value="100" @checked(old('payment_percent') == 100) required>
                        <span>Full payment</span>
                        <strong class="ml-auto"><x-amount :value="$group->total_amount" /></strong>
                    </label>
                </fieldset>
            @endif
            <fieldset class="space-y-3">
                <legend class="font-semibold">Resort receiving account</legend>
                @forelse($destinations as $destination)
                    <div class="group">
                    <label class="flex items-start gap-3 rounded-xl border border-border p-4 cursor-pointer">
                        <input type="radio" name="payment_destination_id" value="{{ $destination->id }}" required @checked(old('payment_destination_id', $destinations->count() === 1 ? $destination->id : null) == $destination->id) class="peer mt-1 text-primary">
                        <span class="text-sm peer-checked:[&_img]:block"><strong>{{ $destination->bank ?: $destination->method->label() }}</strong><br>{{ $destination->account_name }} · {{ $destination->account_number }}
                            @if($destination->qr_path)<img src="{{ route('payment-destinations.image', $destination, false) }}" alt="Payment QR for {{ $destination->account_name }}" class="mt-3 hidden h-36 w-36 object-contain">@endif
                        </span>
                    </label>
                    @if($destination->qr_path)
                        <div class="hidden flex-wrap gap-x-4 px-4 group-has-[:checked]:flex">
                            <a href="{{ route('payment-destinations.image', [$destination, 'download' => 1], false) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline">Download QR</a>
                            <a href="{{ route('payment-destinations.image', $destination, false) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline">View full size<span class="sr-only"> (opens in a new tab)</span></a>
                        </div>
                    @endif
                    </div>
                @empty
                    <p class="rounded-xl border border-warning bg-warning/10 p-4 text-sm">No receiving account is available. Contact the resort before transferring money.</p>
                @endforelse
            </fieldset>

            @if($destinations->isNotEmpty())
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium">Date you transferred<input type="date" name="payment_date" value="{{ old('payment_date') }}" max="{{ \App\Support\ResortTime::today()->toDateString() }}" required class="mt-1 block min-h-12 w-full rounded-lg border-border text-base"></label>
                <div class="sm:col-span-2">
                    <label for="proof" class="block text-sm font-medium">Upload your receipt</label>
                    <x-customer.payment-proof-input />
                </div>
            </div>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="policies_accepted" value="1" required class="mt-1 rounded border-border text-primary"><span>I have reviewed the exact quote and accept the resort payment and rescheduling terms above.</span></label>
            <button type="submit" class="w-full rounded-xl bg-primary px-5 py-3 font-semibold text-white">Submit receipt for review</button>
            @endif
        </form>
    </main>
</x-customer-layout>
