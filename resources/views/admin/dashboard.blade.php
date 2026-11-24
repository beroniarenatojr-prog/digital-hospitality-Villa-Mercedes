@php
    // The resort clock, not the UTC clock: these links and the header date must
    // name the resort day the staff are working.
    $todayDate = $today->toDateString();
@endphp

<x-admin-layout>
    <x-slot name="header">Today’s operations</x-slot>

    <x-admin.page-header title="Today’s operations">
        <x-slot:actions>
            {{-- The resort day, stated beside the page's own actions. The
                 dashboard is about one shift, and this is the shift the resort
                 is in — read from the resort's clock, not the server's. --}}
            <span class="text-sm font-medium text-text-muted">{{ $today->format('l, F j, Y') }}</span>
            <x-admin.button variant="secondary" :href="route('admin.reports.index')">View reports</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- The counts a shift changes without anyone touching this screen: a
         booking that arrives, a proof verified at the desk beside you, an
         check in checked in. The panel reads itself again on a timer and copies
         the total across with the counts, so the header can never add up
         differently from the rows under it. --}}
    <x-admin.panel
        class="mb-6"
        title="Needs attention"
        :padding="false"
        data-live-panel
        data-live-panel-url="{{ route('admin.dashboard.attention') }}"
        data-live-panel-interval="20000"
    >
        <x-slot:actions>
            <span data-panel-summary class="text-xs font-medium text-text-muted tabular-nums">
                {{ \App\Support\Admin\DashboardAttention::summary($outstandingTotal) }}
            </span>
        </x-slot:actions>

        @include('admin.dashboard.partials.attention', ['attention' => $attention, 'outstandingTotal' => $outstandingTotal])

        @if ($pendingPayments->isNotEmpty())
            <div class="border-t border-border bg-muted/20 px-5 py-3.5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Deposit Proofs Awaiting Verification</span>
                    <a href="{{ route('admin.payments.index', ['status' => 'pending']) }}" class="text-xs font-semibold text-primary hover:text-primary-dark">
                        View all ({{ $pendingPayments->count() }}) &rarr;
                    </a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($pendingPayments as $payment)
                        @php
                            $reservation = $payment->reservation;
                            $group = $payment->reservationGroup;
                            $reservationCode = $reservation?->reservation_code ?? ($group ? 'Group ' . $group->group_code : 'N/A');
                            $guestName = $reservation?->guestName() ?? $group?->guest_name ?? 'Guest unavailable';
                            $facilityName = $reservation?->facility?->name ?? ($group ? 'Group booking' : 'Facility unavailable');
                            $depositPayload = [
                                'id' => $payment->id,
                                'amount' => \App\Support\Money::format($payment->amount),
                                'method' => $payment->method?->label() ?? 'Direct Transfer',
                                'reference' => $payment->reference_number ?: 'None provided',
                                'date' => $payment->payment_date ? $payment->payment_date->format('M j, Y') : 'Recent',
                                'proofUrl' => route('admin.payments.view', $payment),
                                'downloadUrl' => route('admin.payments.download', $payment),
                                'isImage' => $payment->isImageProof(),
                                'isPdf' => $payment->isPdfProof(),
                                'hasProof' => (bool) $payment->proof_path,
                                'reservationCode' => $reservationCode,
                                'guestName' => $guestName,
                                'facilityName' => $facilityName,
                                'verifyUrl' => route('admin.payments.verify', $payment),
                                'rejectUrl' => route('admin.payments.reject', $payment),
                                'showUrl' => route('admin.payments.show', $payment),
                            ];
                        @endphp
                        <button
                            type="button"
                            @click="$dispatch('open-deposit-drawer', @js($depositPayload))"
                            class="flex items-center justify-between p-3 rounded-lg border border-border bg-surface hover:border-sand-dark text-left transition shadow-2xs group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-bold text-text truncate group-hover:text-primary">
                                    {{ $guestName }}
                                </p>
                                <p class="text-[11px] text-text-muted truncate">
                                    {{ $reservationCode }} · {{ $payment->reference_number ?: 'Ref pending' }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs font-bold text-secondary tabular-nums">
                                    <span class="text-text-muted">₱</span>{{ \App\Support\Money::format($payment->amount) }}
                                </span>
                                <span class="block text-[10px] text-primary font-semibold">Review &rarr;</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </x-admin.panel>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-admin.stat label="Spaces in use" :value="$totalOccupiedSpaces" :note="'Of '.$totalFacilities.' active spaces · guests currently checked in'" />
        <x-admin.stat label="Payments verified today" :value="\App\Support\Money::format($paymentsVerifiedToday)" note="All methods · by verification date" />
        <x-admin.stat label="Arrivals today" :value="$totalArrivalsDueCount + $arrivalsCheckedInCount" :note="$totalArrivalsDueCount.' awaiting check-in · '.$arrivalsCheckedInCount.' checked in'" />
    </div>
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-admin.panel
            title="Arriving today"
            class="[&>div:first-child]:sm:flex-nowrap [&>div:first-child>div:first-child]:sm:flex-1"
            :badge="$arrivalsToday->count()"
            description="Guests expected to check in today."
            :padding="false"
        >
            <x-slot:actions>
                <x-admin.button variant="secondary" :href="route('admin.reservations.index', ['arriving_today' => 1])">Open full list</x-admin.button>
            </x-slot:actions>

            @forelse ($arrivalsToday as $reservation)
                @php
                    $arrivalPayload = [
                        'id' => $reservation->id,
                        'code' => $reservation->reservation_code,
                        'guest' => $reservation->guestName(),
                        'email' => $reservation->guestEmail() ?? 'None on file',
                        'phone' => $reservation->guestPhone() ?? 'None on file',
                        'facility' => $reservation->facility->name,
                        'facilityType' => $reservation->facility->facilityType?->name ?? 'Accommodation',
                        'checkIn' => $reservation->check_in->format('M j, Y g:i A'),
                        'checkOut' => $reservation->check_out->format('M j, Y g:i A'),
                        'guests' => $reservation->guest_count,
                        'total' => \App\Support\Money::format($reservation->total_price),
                        'remaining' => \App\Support\Money::format($reservation->getRemainingBalance()),
                        'status' => $reservation->status->label(),
                        'statusTone' => $reservation->status->tone(),
                        'checkInUrl' => route('admin.reservations.check-in', $reservation),
                        'showUrl' => route('admin.reservations.show', $reservation),
                    ];
                @endphp
                <a
                    href="{{ route('admin.reservations.show', $reservation) }}"
                    @click.prevent="$dispatch('open-arrival-drawer', @js($arrivalPayload))"
                    class="flex items-center gap-3 border-b border-border px-5 py-3.5 transition-colors duration-150 last:border-b-0 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"
                >
                    <span class="w-16 shrink-0 text-sm font-semibold tabular-nums text-primary">{{ $reservation->check_in->format('g:i A') }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-text">{{ $reservation->guestName() }}</span>
                        <span class="block truncate text-xs text-text-muted">{{ $reservation->facility->name }} · {{ $reservation->reservation_code }}</span>
                    </span>
                    <span class="hidden sm:inline-flex items-center rounded border border-border px-2 py-0.5 text-xs font-semibold text-text hover:bg-muted">
                        Check in…
                    </span>
                    <x-badge :status="$reservation->status" variant="compact" />
                </a>
            @empty
                <x-admin.empty
                    icon="reservations"
                    title="No check ins today"
                    description="Stays due to arrive today appear here with their check in time."
                />
            @endforelse
        </x-admin.panel>

        <x-admin.panel
            title="Checkouts due today"
            class="[&>div:first-child]:sm:flex-nowrap [&>div:first-child>div:first-child]:sm:flex-1"
            :badge="$departuresDue->count()"
            description="Stays due to leave today or overdue."
            :padding="false"
        >
            <x-slot:actions>
                <x-admin.button variant="secondary" :href="route('admin.reports.operational', ['ops' => 'occupancy'])">Open checked-in list</x-admin.button>
            </x-slot:actions>
            @forelse ($departuresDue as $reservation)
                <a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center gap-3 border-b border-border px-5 py-3.5 transition-colors duration-150 last:border-b-0 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                    <span class="w-16 shrink-0 text-sm font-semibold tabular-nums text-primary">{{ $reservation->occupancyEnd()->format('g:i A') }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-text">{{ $reservation->guestName() }}</span>
                        <span class="block truncate text-xs text-text-muted">{{ $reservation->facility->name }} · {{ $reservation->reservation_code }}</span>
                        @if ($reservation->occupancyEnd()->lt(\App\Support\ResortTime::databaseNow()))
                            <span class="block text-xs font-semibold text-danger">Overdue checkout</span>
                        @endif
                    </span>
                    <span class="inline-flex min-h-[44px] shrink-0 items-center rounded-lg border border-border bg-surface px-3 text-xs font-semibold text-primary">View booking</span>
                </a>
            @empty
                <x-admin.empty
                    icon="reservations"
                    title="No check outs due"
                    description="Checked-in stays scheduled to leave today appear here."
                />
            @endforelse
        </x-admin.panel>

        <x-admin.panel
            class="lg:col-span-2"
            title="Next 7 days"
            :badge="$upcomingReservations->count()"
            description="The next five stays in the coming week, ordered by check in."
            :padding="false"
        >
            <x-slot:actions>
                <x-admin.button variant="secondary" :href="route('admin.reservations.index')">Open reservations</x-admin.button>
            </x-slot:actions>

            @forelse ($upcomingReservations as $reservation)
                <a href="{{ route('admin.reservations.show', $reservation) }}" class="flex items-center gap-3 border-b border-border px-5 py-3.5 transition-colors duration-150 last:border-b-0 hover:bg-primary-light/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary">
                    <span class="w-16 shrink-0 text-sm font-semibold tabular-nums text-primary">{{ $reservation->check_in->format('M j, Y') }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-text">{{ $reservation->guestName() }}</span>
                        <span class="block truncate text-xs text-text-muted">{{ $reservation->facility->name }} · arrives {{ $reservation->check_in->format('g:i A') }}</span>
                    </span>
                    <x-badge :status="$reservation->status" variant="compact" />
                </a>
            @empty
                <x-admin.empty
                    icon="reservations"
                    title="No stays in the next 7 days"
                    description="Confirmed and pending bookings with a check in inside the week appear here."
                />
            @endforelse
        </x-admin.panel>
    </div>

    {{-- Bottom Operational Grids: Spaces Booked Today & Recent Activity Stream --}}
    {{-- Visual Centerpiece: Arrivals and departures SVG Trend Chart + New Requests Stream --}}
    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
        {{-- Left: 7-Day Rolling Trend Line & Movement Chart (7 cols on lg) --}}
        <div class="lg:col-span-7">
            <x-admin.panel
                title="Arrivals and departures"
                description="Scheduled bookings across the previous three days, today, and the next three days."
                :padding="true"
            >
                <x-slot:actions>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-medium text-secondary">
                            <span class="h-2.5 w-2.5 rounded-full bg-secondary"></span> Check ins
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-medium text-primary">
                            <span class="h-2.5 w-2.5 rounded-full bg-primary"></span> Check outs
                        </span>
                    </div>
                </x-slot:actions>

                {{-- Pure SVG Responsive Rhythm Chart (zero JS bloat, accessible server SVG) --}}
                <div class="pt-2">
                    <div class="overflow-x-auto" tabindex="0" aria-label="7-day resort operations rhythm chart">
                        <svg viewBox="0 0 700 180" class="w-full min-w-[500px]" role="img" aria-label="Daily arrivals and departures across seven days">
                            <defs>
                                <linearGradient id="rhythmSageGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#6B7F5B" stop-opacity="0.25"/>
                                    <stop offset="100%" stop-color="#6B7F5B" stop-opacity="0.0"/>
                                </linearGradient>
                            </defs>

                            {{-- Grid lines --}}
                            <line x1="50" y1="30" x2="680" y2="30" stroke="#D9C9B2" stroke-dasharray="3,3" opacity="0.6"/>
                            <line x1="50" y1="80" x2="680" y2="80" stroke="#D9C9B2" stroke-dasharray="3,3" opacity="0.6"/>
                            <line x1="50" y1="130" x2="680" y2="130" stroke="#D9C9B2" opacity="0.8"/>

                            @php
                                $maxMove = $sevenDayTrend['maxMovement'];
                                $stepX = 600 / 6; // 7 points
                                $pointsArr = [];
                                $pointsDep = [];

                                foreach ($sevenDayTrend['days'] as $idx => $d) {
                                    $x = 60 + ($idx * $stepX);
                                    $yArr = 130 - (100 * ($d['arrivals'] / $maxMove));
                                    $yDep = 130 - (100 * ($d['departures'] / $maxMove));
                                    $pointsArr[] = "{$x},{$yArr}";
                                    $pointsDep[] = "{$x},{$yDep}";
                                }
                            @endphp

                            {{-- Shaded trend path for arrivals --}}
                            <polygon
                                points="60,130 {{ implode(' ', $pointsArr) }} 660,130"
                                fill="url(#rhythmSageGrad)"
                            />

                            {{-- Arrivals Trend Line (Sage) --}}
                            <polyline
                                fill="none"
                                stroke="#6B7F5B"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                points="{{ implode(' ', $pointsArr) }}"
                            />

                            {{-- Departures Trend Line (Primary Earth Brown) --}}
                            <polyline
                                fill="none"
                                stroke="#623F1F"
                                stroke-width="2"
                                stroke-dasharray="4,3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                points="{{ implode(' ', $pointsDep) }}"
                            />

                            {{-- Points & Day Columns --}}
                            @foreach ($sevenDayTrend['days'] as $idx => $day)
                                @php
                                    $x = 60 + ($idx * $stepX);
                                    $yArr = 130 - (100 * ($day['arrivals'] / $maxMove));
                                    $yDep = 130 - (100 * ($day['departures'] / $maxMove));
                                @endphp

                                {{-- Today Column Marker --}}
                                @if ($day['isToday'])
                                    <rect x="{{ $x - 24 }}" y="15" width="48" height="120" rx="6" fill="#623F1F" fill-opacity="0.06"/>
                                @endif

                                {{-- Arrivals Point (Sage circle) --}}
                                <circle cx="{{ $x }}" cy="{{ $yArr }}" r="{{ $day['isToday'] ? '5' : '4' }}" fill="#6B7F5B" stroke="#FFFDF8" stroke-width="2">
                                    <title>{{ $day['label'] }} · {{ $day['arrivals'] }} Check ins</title>
                                </circle>

                                {{-- Departures Point (Brown circle) --}}
                                <circle cx="{{ $x }}" cy="{{ $yDep }}" r="{{ $day['isToday'] ? '4.5' : '3.5' }}" fill="#623F1F" stroke="#FFFDF8" stroke-width="1.5">
                                    <title>{{ $day['label'] }} · {{ $day['departures'] }} Check outs</title>
                                </circle>

                                {{-- X-axis Labels --}}
                                <text
                                    x="{{ $x }}"
                                    y="152"
                                    text-anchor="middle"
                                    font-size="11"
                                    font-weight="{{ $day['isToday'] ? 'bold' : 'normal' }}"
                                    fill="{{ $day['isToday'] ? '#623F1F' : '#596356' }}"
                                >
                                    {{ $day['dayName'] }}
                                </text>
                                <text
                                    x="{{ $x }}"
                                    y="166"
                                    text-anchor="middle"
                                    font-size="10"
                                    fill="{{ $day['isToday'] ? '#623F1F' : '#8C8E8B' }}"
                                >
                                    {{ $day['label'] }}
                                </text>
                            @endforeach
                        </svg>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-border pt-3 text-xs text-text-muted">
                        <span>Solid line: Scheduled check ins · Dashed line: Scheduled check outs</span>
                        <a href="{{ route('admin.decision-board') }}" class="font-semibold text-primary hover:text-primary-dark">
                            View reservation calendar &rarr;
                        </a>
                    </div>
                </div>
            </x-admin.panel>
        </div>

        {{-- Right: What the counts above cannot say on their own: which request is waiting (5 cols on lg) --}}
        <div class="self-start lg:col-span-5">
            <x-admin.panel
                title="New requests today"
                :badge="$requestsToday->count()"
                description="Requests sent in today, oldest first."
                :padding="false"
                data-live-panel
                data-live-panel-url="{{ route('admin.dashboard.new-requests') }}"
                data-live-panel-interval="20000"
                data-live-panel-noun="request"
            >
                <x-slot:actions>
                    <span data-live-panel-status role="status" class="text-xs font-medium text-text-muted">Live</span>
                    <x-admin.button variant="secondary" :href="route('admin.reservations.index', ['status' => 'pending'])">Review pending requests</x-admin.button>
                </x-slot:actions>

                @include('admin.dashboard.partials.new-requests', ['requestsToday' => $requestsToday])
            </x-admin.panel>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.panel title="Spaces booked today" description="Active spaces with a blocking reservation overlapping today.">
            <div class="space-y-4">
                @forelse ($occupancyByType as $row)
                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between gap-3">
                            <p class="text-sm font-semibold text-text">{{ $row['label'] }}</p>
                            <p class="text-xs font-medium text-text-muted tabular-nums">{{ $row['occupied'] }} of {{ $row['total'] }} · {{ $row['pct'] }}%</p>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-muted" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $row['pct'] }}" aria-label="{{ $row['label'] }} booked today">
                            <div class="h-full rounded-full bg-primary" @style(['width: '.$row['pct'].'%'])></div>
                        </div>
                    </div>
                @empty
                    <x-admin.empty
                        icon="facilities"
                        title="No active spaces to measure"
                        description="Occupancy appears once facilities are active and reservations overlap today."
                    />
                @endforelse
            </div>
        </x-admin.panel>

        {{-- Recent Activity Feed (as seen in Acme, Petalytics, and CozyDash) --}}
        <x-admin.panel title="Recent activity" description="Audit log of recent system and front-desk actions." :padding="false">
            <x-slot:actions>
                <a href="{{ route('admin.activity-log.index') }}" class="rounded text-sm font-semibold text-primary hover:text-primary-dark">
                    View all
                </a>
            </x-slot:actions>

            @forelse ($recentActivities as $activity)
                <div class="flex items-center gap-3 border-b border-border px-5 py-3.5 last:border-b-0 hover:bg-muted/30">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold text-text-muted" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr($activity->actorName(), 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-text">
                            {{ $activity->actorName() }}
                            <span class="font-normal text-text-muted">· {{ $activity->actionLabel() }}</span>
                        </p>
                        @if ($activity->subjectLabel())
                            <p class="truncate text-xs text-text-muted">
                                @if ($activity->subjectUrl())
                                    <a href="{{ $activity->subjectUrl() }}" class="font-medium text-primary hover:underline">
                                        {{ $activity->subjectLabel() }}
                                    </a>
                                @else
                                    {{ $activity->subjectLabel() }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-text-muted">
                        {{ $activity->created_at->diffForHumans(short: true) }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-text-muted">
                    No recent activity recorded yet.
                </div>
            @endforelse
        </x-admin.panel>
    </div>

    {{-- Today at a glance --}}
    <x-admin.panel title="Today at a glance" description="Recorded activity for the resort day." :padding="false" class="mb-6">
        <dl class="grid grid-cols-2 divide-x divide-border sm:grid-cols-4 sm:divide-x">
            <div class="px-4 py-4 first:pl-5">
                <dt class="text-xs text-text-muted">Bookings created</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-text">{{ number_format($todayBookingSummary['total']) }}</dd>
            </div>
            <div class="px-4 py-4">
                <dt class="text-xs text-text-muted">Booking value</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-text"><x-amount :value="$todayRevenueSummary['booking_value']" /></dd>
            </div>
            <div class="px-4 py-4">
                <dt class="text-xs text-text-muted">Verified cash</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-secondary"><x-amount :value="$todayRevenueSummary['verified_payment_revenue']" /></dd>
            </div>
            <div class="px-4 py-4 last:pr-5">
                <dt class="text-xs text-text-muted">Outstanding</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-accent"><x-amount :value="$todayRevenueSummary['outstanding_balance']" /></dd>
            </div>
        </dl>
    </x-admin.panel>

    {{-- Slide-Over Quick Review Drawer for Requests --}}
    <div
        x-data="{
            open: false,
            rejecting: false,
            request: null,
            init() {
                window.addEventListener('open-request-drawer', (e) => {
                    this.request = e.detail;
                    this.rejecting = false;
                    this.open = true;
                });
            },
            close() {
                this.open = false;
                this.rejecting = false;
            }
        }"
        @keydown.escape.window="close()"
        class="relative z-50"
        aria-labelledby="slide-over-title"
        role="dialog"
        aria-modal="true"
    >
        {{-- Backdrop --}}
        <div
            x-cloak
            x-show="open"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-primary/40 transition-opacity backdrop-blur-xs"
            @click="close()"
        ></div>

        <div x-cloak x-show="open" class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div
                        x-show="open"
                        x-transition:enter="transform transition ease-in-out duration-300"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transform transition ease-in-out duration-300"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="pointer-events-auto w-screen max-w-md border-l border-border bg-surface shadow-2xl flex flex-col"
                    >
                        {{-- Drawer Header --}}
                        <div class="flex items-center justify-between border-b border-border bg-muted/40 px-6 py-4">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-primary">Booking Triage</span>
                                <h2 id="slide-over-title" class="text-base font-bold text-text" x-text="request ? request.code : 'Review Request'"></h2>
                            </div>
                            <button
                                type="button"
                                @click="close()"
                                class="rounded-lg p-1.5 text-text-muted hover:bg-muted hover:text-text focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <span class="sr-only">Close review drawer</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Drawer Body --}}
                        <template x-if="request">
                        <div class="flex-1 overflow-y-auto p-6 space-y-5">
                            {{-- Guest Profile Card --}}
                            <div class="rounded-xl border border-border bg-surface p-4 space-y-1">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Lead Guest</span>
                                <p class="text-base font-bold text-text" x-text="request.guest"></p>
                                <p class="text-xs text-text-muted" x-show="request.bookedBy">
                                    Booked by: <span class="font-medium text-text" x-text="request.bookedBy"></span>
                                </p>
                                <div class="mt-2 text-xs text-text-muted space-y-0.5 pt-2 border-t border-border">
                                    <p>Email: <span class="text-text font-medium" x-text="request.email"></span></p>
                                    <p>Phone: <span class="text-text font-medium" x-text="request.phone"></span></p>
                                </div>
                            </div>

                            {{-- Stay Particulars --}}
                            <div class="rounded-xl border border-border bg-surface p-4 space-y-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Stay Details</span>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-bold text-text" x-text="request.facility"></p>
                                        <p class="text-xs text-text-muted" x-text="request.facilityType"></p>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded font-semibold"
                                          :class="'bg-' + request.statusTone + '-light text-' + request.statusTone"
                                          x-text="request.status">
                                    </span>
                                </div>
                                <div class="text-xs text-text-muted space-y-1 pt-2 border-t border-border">
                                    <div class="flex justify-between">
                                        <span>Check-in:</span>
                                        <span class="font-medium text-text tabular-nums" x-text="request.checkIn"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Check-out:</span>
                                        <span class="font-medium text-text tabular-nums" x-text="request.checkOut"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Guest count:</span>
                                        <span class="font-medium text-text" x-text="request.guests + ' guest' + (request.guests > 1 ? 's' : '')"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Financial Total --}}
                            <div class="rounded-xl border border-border bg-surface p-4 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Quoted Total</span>
                                <span class="text-lg font-bold text-text tabular-nums">
                                    <span class="text-text-muted">₱</span><span x-text="request.total"></span>
                                </span>
                            </div>

                            {{-- Special Requests / Notes --}}
                            <div x-show="request.specialRequests" class="rounded-xl border border-border bg-muted/40 p-4">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Special Requests</span>
                                <p class="mt-1 text-xs text-text" x-text="request.specialRequests"></p>
                            </div>

                            {{-- Rejection Form (Revealed when staff clicks Decline) --}}
                            <div x-show="rejecting" class="rounded-xl border border-danger/40 bg-danger-light/30 p-4 space-y-3">
                                <p class="text-xs font-bold text-danger">State reason for declining request:</p>
                                <form method="POST" :action="request.rejectUrl" class="space-y-3">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <textarea
                                        name="reason"
                                        rows="3"
                                        required
                                        placeholder="Reason sent to guest (e.g. facility undergoing scheduled maintenance)..."
                                        class="admin-input text-xs w-full"
                                    ></textarea>
                                    <div class="flex gap-2">
                                        <button
                                            type="submit"
                                            class="flex-1 rounded-lg bg-danger px-3 py-2 text-xs font-bold text-white hover:bg-danger/90"
                                        >
                                            Confirm Rejection
                                        </button>
                                        <button
                                            type="button"
                                            @click="rejecting = false"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-medium text-text hover:bg-muted"
                                        >
                                            Back
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        </template>
                        {{-- Drawer Actions Footer --}}
                        <div class="border-t border-border bg-surface p-4 space-y-2">
                            <div x-show="!rejecting && request && request.statusValue === 'pending'" class="flex gap-2">
                                <form method="POST" :action="request ? request.approveUrl : '#'" class="flex-1">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-hover shadow-xs transition"
                                    >
                                        Approve Request
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    @click="rejecting = true"
                                    class="rounded-lg border border-danger px-4 py-2.5 text-xs font-semibold text-danger hover:bg-danger-light transition"
                                >
                                    Decline…
                                </button>
                            </div>

                            <a
                                :href="request ? request.showUrl : '#'"
                                class="block text-center text-xs font-semibold text-text-muted hover:text-text py-1"
                            >
                                Open full reservation record &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Slide-Over Quick Review Drawer for Deposit Proofs --}}
    <div
        x-data="{
            open: false,
            rejecting: false,
            deposit: null,
            init() {
                window.addEventListener('open-deposit-drawer', (e) => {
                    this.deposit = e.detail;
                    this.rejecting = false;
                    this.open = true;
                });
            },
            close() {
                this.open = false;
                this.rejecting = false;
            }
        }"
        @keydown.escape.window="close()"
        class="relative z-50"
        aria-labelledby="deposit-drawer-title"
        role="dialog"
        aria-modal="true"
    >
        {{-- Backdrop --}}
        <div
            x-cloak
            x-show="open"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-primary/40 transition-opacity backdrop-blur-xs"
            @click="close()"
        ></div>

        <div x-cloak x-show="open" class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div
                        x-show="open"
                        x-transition:enter="transform transition ease-in-out duration-300"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transform transition ease-in-out duration-300"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="pointer-events-auto w-screen max-w-lg border-l border-border bg-surface shadow-2xl flex flex-col"
                    >
                        {{-- Drawer Header --}}
                        <div class="flex items-center justify-between border-b border-border bg-muted/40 px-6 py-4">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-secondary">Deposit Verification</span>
                                <h2 id="deposit-drawer-title" class="text-base font-bold text-text" x-text="deposit ? ('Payment #' + deposit.id + ' · ' + deposit.reservationCode) : 'Deposit Review'"></h2>
                            </div>
                            <button
                                type="button"
                                @click="close()"
                                class="rounded-lg p-1.5 text-text-muted hover:bg-muted hover:text-text focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <span class="sr-only">Close review drawer</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Drawer Body --}}
                        <template x-if="deposit">
                        <div class="flex-1 overflow-y-auto p-6 space-y-5">
                            {{-- Payment Summary Card --}}
                            <div class="rounded-xl border border-border bg-surface p-4 flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Recorded Amount</span>
                                    <p class="text-xl font-bold text-secondary tabular-nums mt-0.5">
                                        <span class="text-text-muted">₱</span><span x-text="deposit.amount"></span>
                                    </p>
                                    <p class="text-xs text-text-muted" x-text="deposit.method + ' · ' + deposit.date"></p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-text-muted block">Ref Number</span>
                                    <span class="font-mono text-xs font-bold text-text block bg-muted/60 px-2 py-1 rounded" x-text="deposit.reference"></span>
                                </div>
                            </div>

                            {{-- Guest & Space Association --}}
                            <div class="rounded-xl border border-border bg-surface p-4 text-xs space-y-1">
                                <span class="font-bold uppercase tracking-wider text-text-muted">Reservation Particulars</span>
                                <p class="text-sm font-bold text-text" x-text="deposit.guestName"></p>
                                <p class="text-text-muted" x-text="deposit.facilityName + ' · ' + deposit.reservationCode"></p>
                            </div>

                            {{-- Proof Document Preview Well --}}
                            <div class="rounded-xl border border-border bg-muted/30 p-4 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Uploaded Slip</span>
                                    <a :href="deposit.proofUrl" target="_blank" class="text-xs font-semibold text-primary hover:underline">
                                        Open full size &rarr;
                                    </a>
                                </div>
                                <template x-if="deposit.hasProof && deposit.isImage">
                                    <div class="overflow-hidden rounded-lg border border-border bg-black/5 max-h-72 flex items-center justify-center">
                                        <img :src="deposit.proofUrl" alt="Payment proof" class="max-h-72 object-contain w-full">
                                    </div>
                                </template>
                                <template x-if="deposit.hasProof && deposit.isPdf">
                                    <iframe :src="deposit.proofUrl" title="Deposit slip" class="w-full h-64 rounded-lg border border-border bg-surface"></iframe>
                                </template>
                                <template x-if="!deposit.hasProof">
                                    <p class="text-xs text-text-muted py-4 text-center">No digital attachment uploaded.</p>
                                </template>
                            </div>

                            {{-- Rejection Note (Revealed on click) --}}
                            <div x-show="rejecting" class="rounded-xl border border-danger/40 bg-danger-light/30 p-4 space-y-3">
                                <p class="text-xs font-bold text-danger">Decline Reason (shared with guest):</p>
                                <form method="POST" :action="deposit.rejectUrl" class="space-y-3">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <textarea
                                        name="reason"
                                        rows="3"
                                        required
                                        placeholder="Explain issue (e.g. invalid reference number, unreadable slip, amount mismatch)..."
                                        class="admin-input text-xs w-full"
                                    ></textarea>
                                    <div class="flex gap-2">
                                        <button
                                            type="submit"
                                            class="flex-1 rounded-lg bg-danger px-3 py-2 text-xs font-bold text-white hover:bg-danger/90"
                                        >
                                            Confirm Decline
                                        </button>
                                        <button
                                            type="button"
                                            @click="rejecting = false"
                                            class="rounded-lg border border-border px-3 py-2 text-xs font-medium text-text hover:bg-muted"
                                        >
                                            Back
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Drawer Actions Footer --}}
                        </template>
                        <div class="border-t border-border bg-surface p-4 space-y-2">
                            <div x-show="!rejecting" class="flex gap-2">
                                <form method="POST" :action="deposit ? deposit.verifyUrl : '#'" class="flex-1">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <button
                                        type="submit"
                                        class="w-full rounded-lg bg-secondary px-4 py-2.5 text-xs font-bold text-white hover:bg-secondary/90 shadow-xs transition"
                                    >
                                        Verify &amp; Credit Deposit
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    @click="rejecting = true"
                                    class="rounded-lg border border-danger px-4 py-2.5 text-xs font-semibold text-danger hover:bg-danger-light transition"
                                >
                                    Decline Slip…
                                </button>
                            </div>

                            <a
                                :href="deposit ? deposit.showUrl : '#'"
                                class="block text-center text-xs font-semibold text-text-muted hover:text-text py-1"
                            >
                                Open full payment record &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Slide-Over Quick Review Drawer for Arrival Check-Ins --}}
    <div
        x-data="{
            open: false,
            arrival: null,
            init() {
                window.addEventListener('open-arrival-drawer', (e) => {
                    this.arrival = e.detail;
                    this.open = true;
                });
            },
            close() {
                this.open = false;
            }
        }"
        @keydown.escape.window="close()"
        class="relative z-50"
        aria-labelledby="arrival-drawer-title"
        role="dialog"
        aria-modal="true"
    >
        {{-- Backdrop --}}
        <div
            x-cloak
            x-show="open"
            x-transition:enter="ease-in-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-300"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-primary/40 transition-opacity backdrop-blur-xs"
            @click="close()"
        ></div>

        <div x-cloak x-show="open" class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div
                        x-show="open"
                        x-transition:enter="transform transition ease-in-out duration-300"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transform transition ease-in-out duration-300"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="pointer-events-auto w-screen max-w-md border-l border-border bg-surface shadow-2xl flex flex-col"
                    >
                        {{-- Drawer Header --}}
                        <div class="flex items-center justify-between border-b border-border bg-muted/40 px-6 py-4">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-primary">Front Desk Roll Call</span>
                                <h2 id="arrival-drawer-title" class="text-base font-bold text-text" x-text="arrival ? arrival.code : 'Check in'"></h2>
                            </div>
                            <button
                                type="button"
                                @click="close()"
                                class="rounded-lg p-1.5 text-text-muted hover:bg-muted hover:text-text focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <span class="sr-only">Close check-in drawer</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Drawer Body --}}
                        <template x-if="arrival">
                        <div class="flex-1 overflow-y-auto p-6 space-y-5">
                            {{-- Guest Identity --}}
                            <div class="rounded-xl border border-border bg-surface p-4 space-y-1">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Guest Identification</span>
                                <p class="text-base font-bold text-text" x-text="arrival.guest"></p>
                                <div class="mt-2 text-xs text-text-muted space-y-0.5 pt-2 border-t border-border">
                                    <p>Phone: <span class="text-text font-medium" x-text="arrival.phone"></span></p>
                                    <p>Email: <span class="text-text font-medium" x-text="arrival.email"></span></p>
                                </div>
                            </div>

                            {{-- Assigned Space --}}
                            <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Assigned Space &amp; Key</span>
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-base font-bold text-text" x-text="arrival.facility"></p>
                                        <p class="text-xs text-text-muted" x-text="arrival.facilityType"></p>
                                    </div>
                                    <span class="text-xs px-2.5 py-1 rounded-full font-bold bg-primary-light text-primary" x-text="arrival.guests + ' Guests'"></span>
                                </div>
                                <div class="text-xs text-text-muted space-y-1 pt-2 border-t border-border">
                                    <div class="flex justify-between">
                                        <span>Check-in:</span>
                                        <span class="font-medium text-text tabular-nums" x-text="arrival.checkIn"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Expected check-out:</span>
                                        <span class="font-medium text-text tabular-nums" x-text="arrival.checkOut"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Financial Standing at Desk --}}
                            <div class="rounded-xl border border-border bg-surface p-4 space-y-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-text-muted">Folio Balance</span>
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs text-text-muted">Total Quoted:</span>
                                    <span class="text-sm font-semibold text-text tabular-nums">
                                        <span class="text-text-muted">₱</span><span x-text="arrival.total"></span>
                                    </span>
                                </div>
                                <div class="flex justify-between items-baseline pt-2 border-t border-border">
                                    <span class="text-xs font-bold text-text">Remaining Desk Balance:</span>
                                    <span class="text-base font-bold text-accent tabular-nums">
                                        <span class="text-text-muted">₱</span><span x-text="arrival.remaining"></span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        </template>
                        {{-- Drawer Actions Footer --}}
                        <div class="border-t border-border bg-surface p-4 space-y-2">
                            <form method="POST" :action="arrival ? arrival.checkInUrl : '#'">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="hidden" name="_method" value="PATCH">
                                <button
                                    type="submit"
                                    class="w-full rounded-lg bg-primary px-4 py-2.5 text-xs font-bold text-white hover:bg-primary-hover shadow-xs transition"
                                >
                                    Confirm Check-in &amp; Hand Over Key
                                </button>
                            </form>

                            <a
                                :href="arrival ? arrival.showUrl : '#'"
                                class="block text-center text-xs font-semibold text-text-muted hover:text-text py-1"
                            >
                                Open reservation details &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-admin-layout>
