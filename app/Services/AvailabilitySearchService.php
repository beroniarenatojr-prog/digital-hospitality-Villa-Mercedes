<?php

namespace App\Services;

use App\Enums\BookingServiceType;
use App\Enums\FacilityStatus;
use App\Enums\FacilityType;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\ResortPool;
use App\Models\SwimmingSession;
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
        $message = $available ? null : $this->unavailableMessage($type, $period, $guests);

        return $this->payload(
            available: $available,
            status: $available ? 'available' : 'unavailable',
            message: $message,
            period: $period,
            results: $results->all(),
            alternatives: $available ? [] : $this->alternatives($type, $period, $guests, $sessionSlug),
        );
    }

    /**
     * @return array<string, mixed>
     */
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

        $this->availability->primeOverlaps($candidates, $period->arrival, $period->departure);

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
        $sessions = SwimmingSession::query()
            ->where('is_active', true)
            ->when($sessionSlug, fn ($query) => $query->where('slug', $sessionSlug))
            ->orderBy('sort_order')
            ->get();

        if ($sessions->isEmpty()) {
            return collect();
        }

        $cottages = $this->candidates(FacilityType::Cottage, $guests);

        if ($cottages->isEmpty()) {
            return collect();
        }

        $windows = $sessions->map(fn (SwimmingSession $session): array => $session->periodFor($period->checkInDate));
        $from = $windows->sortBy(fn (array $window): int => $window[0]->timestamp)->first()[0];
        $to = $windows->sortByDesc(fn (array $window): int => $window[1]->timestamp)->first()[1];

        $this->availability->primeOverlaps($cottages, $from, $to);

        return $cottages
            ->flatMap(function (Facility $cottage) use ($sessions, $period, $guests): Collection {
                return $sessions->map(function (SwimmingSession $session) use ($cottage, $period, $guests): ?array {
                    try {
                        $resolved = $this->cottages->resolve($cottage, $session, $period->checkInDate);
                    } catch (ValidationException) {
                        return null;
                    }

                    if (! $this->isOpen($cottage, $resolved['check_in'], $resolved['check_out'], $guests)) {
                        return null;
                    }

                    return $this->presentFacility(
                        $cottage,
                        $period,
                        $guests,
                        $session,
                        $resolved['check_in'],
                        $resolved['check_out'],
                    );
                });
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Facility>
     */
    private function candidates(FacilityType $type, int $guests): Collection
    {
        return $this->facilities->getSearchFacilitiesByType($type->value)
            ->filter(function (Facility $facility) use ($guests): bool {
                if ($facility->status !== FacilityStatus::Available) {
                    return false;
                }

                $maxCapacity = $facility->effectiveMaxCapacity();

                return $maxCapacity === null || $guests <= $maxCapacity;
            })
            ->values();
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

    private function isOpen(Facility $facility, Carbon $arrival, Carbon $departure, int $guests): bool
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
            'booking_url' => route('customer.booking.create', ['facility' => $facility, ...$bookingQuery]),
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

        return [$period->arrival, $period->departure];
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

        return '₱'.number_format($cents / 100, 2);
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
