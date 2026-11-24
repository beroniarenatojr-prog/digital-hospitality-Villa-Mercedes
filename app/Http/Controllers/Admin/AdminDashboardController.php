<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Admin\ReportingService;
use App\Support\Admin\DashboardAttention;
use App\Support\LayoutBadges;
use App\Support\ResortTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService
    ) {}

    public function index(Request $request): View
    {
        // Today is a resort calendar day: staff arriving at 12:30 AM must see
        // today's arrivals, not yesterday's.
        $today = ResortTime::now();

        // Keep the first screen operational: staff should immediately see the
        // work that needs a decision, today’s arrivals, and the next stays.
        ['items' => $attention, 'total' => $outstandingTotal] = $this->attentionCounts($today);

        $requestsToday = $this->requestsSentToday($today);

        $arrivalsToday = Reservation::query()
            ->with(['user', 'facility'])
            ->arrivingOn($today)
            ->get();

        $departuresDue = Reservation::query()
            ->with(['user', 'facility'])
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->where('check_out', '<=', $today->copy()->endOfDay())
            ->get()
            ->filter(fn (Reservation $reservation): bool => $reservation->occupancyEnd()->lte(ResortTime::databaseNow()->endOfDay()))
            ->sortBy(fn (Reservation $reservation) => $reservation->occupancyEnd()->getTimestamp())
            ->values();

        $upcomingReservations = Reservation::query()
            ->with(['user', 'facility'])
            ->whereIn('status', Reservation::blockingStatuses())
            ->whereBetween('check_in', [
                $today->copy()->addDay()->startOfDay(),
                $today->copy()->addDays(7)->endOfDay(),
            ])
            ->orderBy('check_in')
            ->take(5)
            ->get();

        $occupancyByType = $this->reportingService->getOccupancyByType($today);
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();
        $todayBookingSummary = $this->reportingService->getBookingSummary($todayStart, $todayEnd);
        $todayRevenueSummary = $this->reportingService->getRevenueSummary($todayStart, $todayEnd);

        // Compute top-level operational cockpit KPIs
        $totalFacilities = Facility::where('is_active', true)->count();
        $totalOccupiedSpaces = Facility::where('is_active', true)
            ->whereHas('reservations', fn ($query) => $query->whereNotNull('checked_in_at')->whereNull('checked_out_at'))
            ->count();
        $paymentsVerifiedToday = Payment::where('status', PaymentStatus::Verified->value)
            ->where('verified_at', '>=', ResortTime::today()->utc())
            ->where('verified_at', '<', ResortTime::today()->addDay()->utc())
            ->sum('amount');
        $overallOccupancyPct = $totalFacilities > 0 ? (int) round(($totalOccupiedSpaces / $totalFacilities) * 100) : 0;

        $arrivalsCheckedInCount = Reservation::query()
            ->whereDate('check_in', $today->toDateString())
            ->whereNotNull('checked_in_at')
            ->count();
        $totalArrivalsDueCount = $arrivalsToday->count();

        // 7-day rolling resort rhythm (previous 3 days, today, next 3 days)
        $sevenDayTrend = $this->resortRhythmTrend($today);

        // Pending deposit proofs waiting for staff verification
        $pendingPayments = Payment::query()
            ->with(['reservation.user', 'reservation.facility', 'reservationGroup'])
            ->where('status', PaymentStatus::Pending->value)
            ->latest('id')
            ->take(6)
            ->get();

        // Recent operational activity
        $recentActivities = ActivityLog::query()
            ->with(['causer', 'subject'])
            ->latest('id')
            ->take(4)
            ->get();

        return view('admin.dashboard', compact(
            'today',
            'attention',
            'outstandingTotal',
            'requestsToday',
            'arrivalsToday',
            'departuresDue',
            'upcomingReservations',
            'occupancyByType',
            'todayBookingSummary',
            'todayRevenueSummary',
            'totalFacilities',
            'totalOccupiedSpaces',
            'paymentsVerifiedToday',
            'overallOccupancyPct',
            'arrivalsCheckedInCount',
            'totalArrivalsDueCount',
            'sevenDayTrend',
            'pendingPayments',
            'recentActivities'
        ));
    }

    /**
     * The list of requests on the dashboard, on its own, so the panel can ask
     * for it again without the rest of the page. It is the same query the
     * dashboard renders, which is why it lives here rather than in the view.
     */
    public function newRequests(): Response
    {
        // A poll answers for the shift the resort is in right now, so the day is
        // read from the resort clock on every call rather than when the page was
        // opened. A staff member working past midnight rolls onto the next day
        // without reloading.
        $today = ResortTime::now();

        // Built as a response rather than a bare view so the cache header has
        // somewhere to land: View::__call turns an unknown with*() into view
        // data, so a header asked for there is quietly never sent.
        return response()
            ->view('admin.dashboard.partials.new-requests', [
                'requestsToday' => $this->requestsSentToday($today),
            ])
            // Nothing between the resort and the browser may answer this with a
            // remembered list: a cached poll is a request nobody sees.
            ->header('Cache-Control', 'no-store');
    }

    /**
     * The counts that say what is waiting, on their own, so the panel showing
     * them can stop being a snapshot taken when the page was opened.
     */
    public function attention(): Response
    {
        ['items' => $attention, 'total' => $outstandingTotal] = $this->attentionCounts(ResortTime::now());

        return response()
            ->view('admin.dashboard.partials.attention', [
                'attention' => $attention,
                'outstandingTotal' => $outstandingTotal,
            ])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Everything the "Needs attention" panel states, counted once so the page
     * and the panel that refreshes it can never count it differently.
     *
     * The reservation and payment counts are the layout's own short-lived
     * counts, read rather than recomputed: the badges in the sidebar and this
     * panel are the same fact, and each is dropped the moment the row behind it
     * changes.
     *
     * @return array{items: list<array{label: string, description: string, count: int, url: string, action: string}>, total: int}
     */
    private function attentionCounts(Carbon $today): array
    {
        $items = DashboardAttention::items(
            $today,
            LayoutBadges::pendingReservationCount(),
            LayoutBadges::pendingPaymentCount(),
            Reservation::query()->arrivingOn($today)->count(),
        );

        return ['items' => $items, 'total' => DashboardAttention::total($items)];
    }

    /**
     * The newest thing staff have to read: requests customers sent in today.
     *
     * created_at is an instant stored in UTC, so the resort day is converted
     * into that base before the range is compared — the same reason a 12:30 AM
     * arrival used to fall off the arrival list until 8:00 AM.
     *
     * @return Collection<int, Reservation>
     */
    private function requestsSentToday(Carbon $today): Collection
    {
        return Reservation::query()
            ->with(['user', 'facility'])
            ->where('reservation_source', ReservationSource::Online->value)
            ->whereBetween('created_at', [
                $today->copy()->startOfDay()->timezone(config('app.timezone')),
                $today->copy()->endOfDay()->timezone(config('app.timezone')),
            ])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Compute a 7-day rolling operational rhythm: arrivals, departures, and active demand.
     *
     * @return array{days: list<array{date: string, dayName: string, label: string, isToday: bool, arrivals: int, departures: int}>, maxMovement: int}
     */
    private function resortRhythmTrend(Carbon $today): array
    {
        $startDate = $today->copy()->subDays(3)->startOfDay();
        $endDate = $today->copy()->addDays(3)->endOfDay();

        $reservations = Reservation::query()
            ->whereIn('status', [ReservationStatus::Approved->value, ReservationStatus::Completed->value])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('check_in', [$startDate, $endDate])
                    ->orWhereBetween('check_out', [$startDate, $endDate]);
            })
            ->get(['id', 'check_in', 'check_out', 'status']);

        $days = [];
        $maxMovement = 1;

        for ($i = -3; $i <= 3; $i++) {
            $cursor = $today->copy()->addDays($i);
            $dateStr = $cursor->toDateString();

            $arrivals = $reservations->filter(
                fn (Reservation $r) => $r->check_in && $r->check_in->toDateString() === $dateStr
            )->count();

            $departures = $reservations->filter(
                fn (Reservation $r) => $r->check_out && $r->check_out->toDateString() === $dateStr
            )->count();

            if ($arrivals + $departures > $maxMovement) {
                $maxMovement = $arrivals + $departures;
            }

            $days[] = [
                'date' => $dateStr,
                'dayName' => $cursor->format('D'),
                'label' => $cursor->format('M j'),
                'isToday' => $i === 0,
                'arrivals' => $arrivals,
                'departures' => $departures,
            ];
        }

        return [
            'days' => $days,
            'maxMovement' => max(4, $maxMovement),
        ];
    }
}
