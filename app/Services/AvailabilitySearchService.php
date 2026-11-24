<?php

namespace App\Services;

use App\Enums\BookingServiceType;
use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Enums\RateType;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Models\Facility;
use App\Models\ResortPool;
use App\Models\SwimmingSession;
use App\Support\Money;
use App\Support\StayPeriod;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AvailabilitySearchService
{
    private const UNAVAILABLE_MESSAGE = 'Not available on the selected date/time.';

    private const ALTERNATIVE_LIMIT = 6;

    /** How many days either side of the guest's window are checked for space. */
    public const NEARBY_RADIUS = 3;

    /** How many nearby dates a guest is offered before the rest are dropped. */
    public const NEARBY_LIMIT = 3;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly FacilityQueryService $facilities,
        private readonly CottageBookingService $cottages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function search(
        BookingServiceType $type,
        Carbon $checkInDate,
        Carbon $checkOutDate,
        int $guests,
        ?string $sessionSlug = null,
    ): array {
        $period = StayPeriod::fromDates($checkInDate->toDateString(), $checkOutDate->toDateString());

        if ($type === BookingServiceType::Pool) {
            return $this->poolResponse($period, $guests, $sessionSlug);
        }

        $results = $type === BookingServiceType::Cottage
            ? $this->searchCottages($period, $guests, $sessionSlug)
            : $this->searchFacilities($type->facilityType(), $period, $guests);

        $available = $results->isNotEmpty();
        $status = $available ? \App\Enums\ServiceAvailability::Available : $this->emptyResultStatus($type, $period, $guests, $sessionSlug);
        $message = $available ? null : $status->label().' for the selected period. '.$this->unavailableMessage($type, $period, $guests);

        return $this->payload(
            available: $available,
            status: $status->value,
            message: $message,
            period: $period,
            results: $results->all(),
            alternatives: $available ? [] : $this->alternatives($type, $period, $guests, $sessionSlug),
        );
    }

    /**
     * Narrow a catalog down to the spaces that are genuinely open.
     *
     * This is the picker's question — "which of these can I actually book?" —
     * asked of the same rules that answer the availability search, so a card the
     * picker offers and a card the search refuses can never be the same space.
     * Two deliberate differences:
     *
     *  - A `null` period asks a smaller question — does this space fit the party?
     *    — and leaves the catalog otherwise as the resort published it. Use it
     *    when the guest has not given dates yet.
     *  - Cottages are booked per swimming session, so a cottage counts as open
     *    when the session it is being asked about (or any active session, when
     *    none was chosen) can host that visit.
     *
     * @param  Collection<int, Facility>  $facilities  the catalog to narrow, in the caller's order
     * @return Collection<int, Facility>
     */
    public function openAmong(
        Collection $facilities,
        ?StayPeriod $period,
        ?int $guests = null,
        ?string $sessionSlug = null,
    ): Collection {
        $catalog = $facilities
            ->filter(fn (Facility $facility): bool => $period instanceof StayPeriod
                // With dates, the operational status is part of the answer and
                // `isAvailable` below gives it. Without them the picker still
                // shows the published catalog as it is: a space under
                // maintenance is the resort's own notice, not a booking answer.
                ? $this->passesCapacity($facility, $guests)
                : $this->fitsParty($facility, $guests))
            ->values();

        if (! $period instanceof StayPeriod || $catalog->isEmpty()) {
            return $catalog;
        }

        $spaces = $catalog->reject(fn (Facility $facility): bool => $facility->isCottage())->values();

        if ($spaces->isNotEmpty()) {
            $this->availability->primeOverlaps($spaces, $period->arrival->copy()->startOfDay(), $period->departure->copy()->addDay()->endOfDay());
        }

        $openIds = $spaces
            ->filter(function (Facility $facility) use ($period, $guests): bool {
                [$arrival, $departure] = $this->windowFor($facility, $period);

                return $this->isOpen($facility, $arrival, $departure, $guests);
            })
            ->pluck('id')
            ->merge(
                $this->openCottagesAmong(
                    $catalog->filter(fn (Facility $facility): bool => $facility->isCottage())->values(),
                    $period,
                    $guests,
                    $sessionSlug,
                )->pluck('id')
            )
            ->all();

        return $catalog
            ->filter(fn (Facility $facility): bool => in_array($facility->id, $openIds, true))
            ->values();
    }

    /**
     * How many spaces each service has open for a window — the picker's tabs.
     *
     * Counts exist so "nothing here" can point somewhere instead of leaving the
     * guest to guess which of the four services to try next.
     *
     * @return array<string, int> keyed by facility type slug
     */
    public function openCountsByType(StayPeriod $period, ?int $guests = null, ?string $sessionSlug = null): array
    {
        $counts = [];

        foreach (FacilityType::cases() as $type) {
            $counts[$type->value] = $this->openAmong(
                $this->candidates($type, $guests),
                $period,
                $guests,
                $sessionSlug,
            )->count();
        }

        return $counts;
    }

    /**
     * Nearby windows that do have something free.
     *
     * "Nothing is free for those dates" is a true answer and a useless one: a
     * guest who has picked one weekend out of a month, and found the resort busy on
     * it, has usually picked the one day that is. So the days either side of their
     * window are asked the same question, of the same rules and for the same party,
     * nearest first — one day later, one day earlier, two days later, and so on —
     * and the ones with space are offered as dates to click.
     *
     * Only ever asked when a list came back empty, so the ordinary picker pays
     * nothing for it, and it stops as soon as it has enough. That is also why the
     * early exit is safe: the walk is already in distance order, so the suggestions
     * that survive are the nearest ones.
     *
     * `checked` says whether the question could be asked at all: a service with
     * nothing published on it has no days to suggest, and telling that guest the
     * neighbouring dates are "booked" would be a plain lie.
     *
     * @return array{checked: bool, dates: list<array{period: StayPeriod, shift: int, count: int}>}
     */
    public function nearbyOpenWindows(
        FacilityType $type,
        StayPeriod $period,
        ?int $guests = null,
        ?string $sessionSlug = null,
        int $limit = self::NEARBY_LIMIT,
        int $radius = self::NEARBY_RADIUS,
    ): array {
        // The catalog does not change with the dates, so it is read once: only the
        // window moves.
        $catalog = $this->candidates($type, $guests);

        if ($catalog->isEmpty()) {
            return ['checked' => false, 'dates' => []];
        }

        $found = [];

        for ($distance = 1; $distance <= $radius && count($found) < $limit; $distance++) {
            foreach ([$distance, -$distance] as $shift) {
                $shifted = StayPeriod::fromDates(
                    Carbon::parse($period->checkInDate)->addDays($shift)->toDateString(),
                    Carbon::parse($period->checkOutDate)->addDays($shift)->toDateString(),
                );

                // A window that has already started is not a suggestion, it is a
                // mistake — a guest cannot book yesterday.
                if ($shifted->arrival->isPast()) {
                    continue;
                }

                $count = $this->openAmong($catalog, $shifted, $guests, $sessionSlug)->count();

                if ($count > 0) {
                    $found[] = ['period' => $shifted, 'shift' => $shift, 'count' => $count];
                }
            }
        }

        return ['checked' => true, 'dates' => $found];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResultStatus(BookingServiceType $type, StayPeriod $period, int $guests, ?string $sessionSlug): \App\Enums\ServiceAvailability
    {
        $facilityType = $type->facilityType();
        if (! $facilityType) return \App\Enums\ServiceAvailability::Unavailable;
        $catalog = $this->facilities->getSearchFacilitiesByType($facilityType->value)
            ->filter(fn (Facility $facility) => $this->fitsParty($facility, $guests));
        $states = [];
        foreach ($catalog as $facility) {
            $windows = $facility->isCottage()
                ? $this->activeSessions($sessionSlug)->map(fn ($session) => $this->cottageWindow($facility, $session, $period))->filter()->all()
                : [$this->windowFor($facility, $period)];
            foreach ($windows as [$start, $end]) {
                $states[] = $this->availability->isAvailable($facility, $start, $end, guestCount: $guests)->status();
            }
        }
        if (in_array(\App\Enums\ServiceAvailability::FullyBooked, $states, true)) return \App\Enums\ServiceAvailability::FullyBooked;
        if ($states !== [] && collect($states)->every(fn ($state) => $state === \App\Enums\ServiceAvailability::TemporarilyClosed)) return \App\Enums\ServiceAvailability::TemporarilyClosed;

        return \App\Enums\ServiceAvailability::Unavailable;
    }

    private function poolResponse(StayPeriod $period, int $guests, ?string $sessionSlug): array
    {
        $pools = ResortPool::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ResortPool $pool): array => $this->presentPool($pool))
            ->values();

        return $this->payload(
            available: false,
            status: 'walk_in',
            message: 'Pool entrance is paid at the gate and does not require a reservation. Sign in only if you want to reserve a cottage or package for these dates.',
            period: $period,
            results: $pools->all(),
            alternatives: $this->alternatives(BookingServiceType::Pool, $period, $guests, $sessionSlug),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchFacilities(?FacilityType $type, StayPeriod $period, int $guests): Collection
    {
        if (! $type instanceof FacilityType) {
            return collect();
        }

        $candidates = $this->candidates($type, $guests);

        $this->availability->primeOverlaps($candidates, $period->arrival->copy()->startOfDay(), $period->departure->copy()->addDay()->endOfDay());

        return $candidates
            ->map(function (Facility $facility) use ($period, $guests): ?array {
                [$arrival, $departure] = $this->windowFor($facility, $period);

                if (! $this->isOpen($facility, $arrival, $departure, $guests)) {
                    return null;
                }

                return $this->presentFacility($facility, $period, $guests, arrival: $arrival, departure: $departure);
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchCottages(StayPeriod $period, int $guests, ?string $sessionSlug): Collection
    {
        $sessions = $this->activeSessions($sessionSlug);

        if ($sessions->isEmpty()) {
            return collect();
        }

        $cottages = $this->candidates(FacilityType::Cottage, $guests);

        if ($cottages->isEmpty()) {
            return collect();
        }

        [$from, $to] = $this->sessionSpan($sessions, $period);

        $this->availability->primeOverlaps($cottages, $from, $to);

        return $cottages
            ->flatMap(function (Facility $cottage) use ($sessions, $period, $guests): Collection {
                return $sessions->map(function (SwimmingSession $session) use ($cottage, $period, $guests): ?array {
                    $window = $this->cottageWindow($cottage, $session, $period);

                    if ($window === null || ! $this->isOpen($cottage, $window[0], $window[1], $guests)) {
                        return null;
                    }

                    return $this->presentFacility(
                        $cottage,
                        $period,
                        $guests,
                        $session,
                        $window[0],
                        $window[1],
                    );
                });
            })
            ->filter()
            ->values();
    }

    /**
     * The cottages among these that can host the visit — one row per cottage, not
     * per session, because this answers "can I book this one?" rather than "what
     * can I book?".
     *
     * @param  Collection<int, Facility>  $cottages
     * @return Collection<int, Facility>
     */
    private function openCottagesAmong(
        Collection $cottages,
        StayPeriod $period,
        ?int $guests,
        ?string $sessionSlug,
    ): Collection {
        $sessions = $this->activeSessions($sessionSlug);

        if ($sessions->isEmpty() || $cottages->isEmpty()) {
            return collect();
        }

        [$from, $to] = $this->sessionSpan($sessions, $period);

        $this->availability->primeOverlaps($cottages, $from, $to);

        return $cottages
            ->filter(function (Facility $cottage) use ($sessions, $period, $guests): bool {
                return $sessions->contains(function (SwimmingSession $session) use ($cottage, $period, $guests): bool {
                    $window = $this->cottageWindow($cottage, $session, $period);

                    return $window !== null && $this->isOpen($cottage, $window[0], $window[1], $guests);
                });
            })
            ->values();
    }

    /**
     * The sessions a cottage visit can be booked into: the requested one, or all
     * of them when the guest has not chosen.
     *
     * @return Collection<int, SwimmingSession>
     */
    private function activeSessions(?string $sessionSlug): Collection
    {
        return SwimmingSession::query()
            ->where('is_active', true)
            ->when($sessionSlug, fn ($query) => $query->where('slug', $sessionSlug))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * The window that covers every session of the visit date, so one priming pass
     * has every candidate a session could collide with.
     *
     * @param  Collection<int, SwimmingSession>  $sessions
     * @return array{0: Carbon, 1: Carbon}
     */
    private function sessionSpan(Collection $sessions, StayPeriod $period): array
    {
        $windows = $sessions->map(fn (SwimmingSession $session): array => $session->periodFor($period->checkInDate));

        return [
            $windows->sortBy(fn (array $window): int => $window[0]->timestamp)->first()[0],
            $windows->sortByDesc(fn (array $window): int => $window[1]->timestamp)->first()[1],
        ];
    }

    /**
     * The occupancy window this cottage needs for this session, or null when the
     * session cannot host the visit at all (past, or outside the resort's rules).
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function cottageWindow(Facility $cottage, SwimmingSession $session, StayPeriod $period): ?array
    {
        try {
            $resolved = $this->cottages->resolve($cottage, $session, $period->checkInDate);
        } catch (ValidationException) {
            return null;
        }

        return [$resolved['check_in'], $resolved['check_out']];
    }

    /**
     * The published catalog of one service: everything a guest could be shown for
     * this party size, before any dates are considered.
     *
     * @return Collection<int, Facility>
     */
    private function candidates(FacilityType $type, ?int $guests): Collection
    {
        return $this->facilities->getSearchFacilitiesByType($type->value)
            ->filter(fn (Facility $facility): bool => $this->passesCapacity($facility, $guests))
            ->values();
    }

    /**
     * Whether a space is on offer at all for this party: published, and able to
     * take the guests.
     */
    private function passesCapacity(Facility $facility, ?int $guests): bool
    {
        return $facility->status === FacilityStatus::Available
            && $this->fitsParty($facility, $guests);
    }

    /**
     * Whether a space can take this many guests. A `null` count asks nothing and
     * every space fits; a space with no published ceiling fits anything.
     */
    private function fitsParty(Facility $facility, ?int $guests): bool
    {
        if ($guests === null) {
            return true;
        }

        $maxCapacity = $facility->effectiveMaxCapacity();

        return $maxCapacity === null || $guests <= $maxCapacity;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alternatives(BookingServiceType $selected, StayPeriod $period, int $guests, ?string $sessionSlug): array
    {
        $suggestions = collect();

        foreach ($selected->alternativeTypes() as $type) {
            $matches = $type === BookingServiceType::Cottage
                ? $this->searchCottages($period, $guests, $sessionSlug)
                : $this->searchFacilities($type->facilityType(), $period, $guests);

            $suggestions = $suggestions->concat($matches->take(2));

            if ($suggestions->count() >= self::ALTERNATIVE_LIMIT) {
                break;
            }
        }

        return $suggestions->take(self::ALTERNATIVE_LIMIT)->values()->all();
    }

    private function isOpen(Facility $facility, Carbon $arrival, Carbon $departure, ?int $guests): bool
    {
        try {
            return $this->availability->isAvailable($facility, $arrival, $departure, null, $guests)->isAvailable;
        } catch (InvalidAvailabilityPeriodException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentFacility(
        Facility $facility,
        StayPeriod $period,
        int $guests,
        ?SwimmingSession $session = null,
        ?Carbon $arrival = null,
        ?Carbon $departure = null,
    ): array {
        $arrival ??= $period->arrival;
        $departure ??= $period->departure;
        $baseRate = $facility->rates
            ->filter(fn ($rate): bool => $rate->is_active && $rate->type === RateType::Base)
            ->sortBy(fn ($rate): float => (float) $rate->amount)
            ->first();

        $bookingQuery = $session instanceof SwimmingSession
            ? ['date' => $period->checkInDate, 'session' => $session->slug, 'guests' => $guests]
            : ['arrival' => $arrival->format('Y-m-d H:i'), 'departure' => $departure->format('Y-m-d H:i'), 'guests' => $guests];

        return [
            'id' => $facility->id,
            'name' => $facility->name,
            'type' => $facility->facilityType?->slug,
            'type_label' => $session instanceof SwimmingSession
                ? trim(($facility->facilityType?->name ?? 'Cottage').' · '.$session->name)
                : ($facility->facilityType?->name ?? $facility->name),
            'description' => Str::limit((string) $facility->description, 160),
            'capacity' => $facility->effectiveMaxCapacity(),
            'image_url' => $this->coverUrl($facility),
            // Availability browsing shows the published starting rate only. The
            // date-range quote is calculated later in the reservation flow.
            'price_cents' => $baseRate ? (int) round(((float) $baseRate->amount) * 100) : null,
            'price_label' => $baseRate ? $this->formatPrice((int) round(((float) $baseRate->amount) * 100)) : null,
            'price_unit' => $baseRate?->unit,
            'booking_url' => route('customer.grouped-booking.workspace', ['facility_id' => $facility->id, ...$bookingQuery]),
            'detail_url' => route('facilities.show', ['facility' => $facility, ...$bookingQuery, 'availability_checked' => 1]),
            'detail_query' => [...$bookingQuery, 'availability_checked' => 1],
            'session' => $session?->slug,
            'bookable' => $facility->isOnlineBookable(),
        ];
    }

    private function coverUrl(Facility $facility): ?string
    {
        if (filled($facility->cover_image_path)) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            return $disk->url($facility->cover_image_path);
        }

        if ($facility->relationLoaded('galleryImages')) {
            return $facility->galleryImages->first()?->url();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPool(ResortPool $pool): array
    {
        return [
            'id' => $pool->id,
            'name' => $pool->name,
            'type' => BookingServiceType::Pool->value,
            'type_label' => $pool->is_package_only ? 'Package only' : 'Walk-in',
            'description' => Str::limit((string) $pool->description, 180),
            'capacity' => null,
            'image_url' => $pool->imageUrl(),
            'price_cents' => null,
            'price_label' => null,
            'booking_url' => null,
            'detail_url' => route('resort.show'),
            'session' => null,
            'bookable' => false,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function windowFor(Facility $facility, StayPeriod $period): array
    {
        if ($facility->isFlyerPackage()) {
            return $facility->occupancyWindowForDate(
                Carbon::parse($period->checkInDate),
                Carbon::parse($period->checkOutDate)
            );
        }

        return app(ReservationSchedule::class)->windowForDates($facility, Carbon::parse($period->checkInDate), Carbon::parse($period->checkOutDate));
    }

    private function unavailableMessage(BookingServiceType $type, StayPeriod $period, int $guests): string
    {
        if ($type === BookingServiceType::FunctionHall) {
            $sapphire = $this->candidates(FacilityType::FunctionHall, $guests)
                ->first(fn (Facility $facility): bool => $facility->name === 'Sapphire Hall');

            if ($sapphire instanceof Facility) {
                [$arrival, $departure] = $this->windowFor($sapphire, $period);
                $result = $this->availability->isAvailable($sapphire, $arrival, $departure, null, $guests);

                if (! $result->isAvailable) {
                    return $result->reason ?: AvailabilityService::SAPPHIRE_UNAVAILABLE;
                }
            }
        }

        return self::UNAVAILABLE_MESSAGE;
    }

    private function formatPrice(?int $cents): ?string
    {
        if ($cents === null) {
            return null;
        }

        return Money::of($cents / 100);
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<array<string, mixed>>  $alternatives
     * @return array<string, mixed>
     */
    private function payload(
        bool $available,
        string $status,
        ?string $message,
        StayPeriod $period,
        array $results,
        array $alternatives,
    ): array {
        return [
            'available' => $available,
            'status' => $status,
            'message' => $message,
            'period' => $period->toArray(),
            'results' => $results,
            'alternatives' => $alternatives,
        ];
    }
}
