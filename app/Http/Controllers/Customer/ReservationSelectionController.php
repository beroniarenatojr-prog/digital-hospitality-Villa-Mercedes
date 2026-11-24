<?php

namespace App\Http\Controllers\Customer;

use App\Enums\FacilityType;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Services\AvailabilitySearchService;
use App\Services\FacilityQueryService;
use App\Support\StayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ReservationSelectionController extends Controller
{
    /**
     * The selection keys, in the order a link reads them.
     *
     * Every link this page builds goes through `canonical()`, so one filter is
     * one URL whatever order the query arrived in: a guest who reloads, or shares
     * the address, gets the same list back — and a test can name the address it
     * expects instead of accepting any arrangement of it.
     */
    private const SELECTION_KEYS = ['check_in', 'check_out', 'date', 'session', 'guests', 'arrival', 'departure'];

    public function __invoke(
        Request $request,
        AvailabilitySearchService $availability,
        FacilityQueryService $catalog,
    ) {
        $request->validate([
            'type' => ['nullable', 'string', Rule::enum(FacilityType::class)], 'check_in' => ['nullable', 'date_format:Y-m-d'],
            'check_out' => ['nullable', 'date_format:Y-m-d'], 'guests' => ['nullable', 'integer', 'min:1', 'max:500'],
            'date' => ['nullable', 'date_format:Y-m-d'], 'arrival' => ['nullable', 'date_format:Y-m-d H:i'],
            'departure' => ['nullable', 'date_format:Y-m-d H:i'],
            // A session is named the same way here as in the public availability
            // search, and a link naming a session that does not exist is refused
            // rather than quietly reinterpreted as "any session" — an answer to a
            // different question is worse than no answer.
            'session' => ['nullable', 'string', 'max:50', Rule::exists('swimming_sessions', 'slug')->where('is_active', true)],
        ]);

        $type = (string) $request->query('type', FacilityType::Room->value);
        $facilityType = FacilityType::tryFrom($type);
        abort_if($facilityType === null, 404);

        $guests = $request->filled('guests') ? max(1, (int) $request->query('guests')) : null;
        $session = $request->filled('session') ? (string) $request->query('session') : null;
        $period = $this->period($facilityType, $request);

        // A guest picker, so it shows the published catalog: `listed()` keeps
        // inventory-only facilities (the Private Function Hall) out of it —
        // they are sold inside a package, and picking one here would only walk
        // the guest into a 404 on the way to booking.
        $published = Facility::query()->listed()->whereHas('facilityType', fn ($q) => $q->where('slug', $type))
            ->with('facilityType', 'rates', 'galleryImages', 'amenities')->orderBy('name')->get();

        // Which of them the guest can actually book is an availability question,
        // so it is asked of the service that answers every other one — including
        // the public availability search — rather than re-derived here, where the
        // rules would drift the first time either side changed.
        $open = $availability->openAmong($published, $period, $guests, $session);

        // ...and the order they come back in is "what the guest asked for": the
        // party they named, the rate they will be charged, and whether there is a
        // photo to judge. A–Z is the one order that answers none of those. The
        // full open list is shown on one page so a guest does not page through
        // rooms that are already filtered to what they can book.
        $facilities = $catalog->rankForGuests($open, $guests)->values();

        // With dates in hand, every service can say how much it has free, so a
        // guest who finds nothing under one tab is pointed at the next instead of
        // having to try all four.
        $openByService = $period instanceof StayPeriod
            ? $availability->openCountsByType($period, $guests, $session)
            : [];

        // Nothing free on the guest's dates is a true answer and a dead end, so the
        // days either side of their window are checked too — for their service and
        // their party — and the nearest ones with space are offered as dates. Only
        // asked when the list came back empty: the ordinary picker does not pay for
        // a search nobody needs.
        $nearby = $period instanceof StayPeriod && $facilities->isEmpty()
            ? $availability->nearbyOpenWindows($facilityType, $period, $guests, $session)
            : ['checked' => false, 'dates' => []];

        $nearbyDates = $nearby['dates'];
        $nearbyChecked = $nearby['checked'];
        $nearbyRadius = AvailabilitySearchService::NEARBY_RADIUS;

        // Whatever the guest chose filters the list *and* travels with them: the
        // space page forwards it to the wizard, which prefills it, so the dates
        // are entered once and read back twice.
        $selection = $this->canonical($request->only(self::SELECTION_KEYS));

        $selectionByService = [];

        foreach (FacilityType::cases() as $candidate) {
            $selectionByService[$candidate->value] = $candidate->value === $type
                ? $selection
                : $this->selectionFor($candidate->value, $selection);
        }

        return view('customer.booking.select', compact(
            'type',
            'facilities',
            'selection',
            'selectionByService',
            'period',
            'guests',
            'openByService',
            'nearbyDates',
            'nearbyChecked',
            'nearbyRadius',
        ));
    }

    /**
     * The window the guest asked to filter by, or null when they have not given
     * dates yet.
     *
     * The picker collects calendar dates — the same values the wizard prefills
     * from — so the resort's published hours supply the times, exactly as the
     * public availability search does. A request that already carries precise
     * times (a space page hands back the window it was asked about) filters on
     * those instead: the wizard will prefill the same times, and a filter that
     * disagrees with the next step is worse than no filter at all.
     */
    private function period(FacilityType $type, Request $request): ?StayPeriod
    {
        $arrival = $this->moment($request->query('arrival'));
        $departure = $this->moment($request->query('departure'));
        $checkOut = $this->date($request->query('check_out'));

        // A cottage is booked into a swimming session on a visit date rather than
        // for a stay, so its filter asks for that one date.
        if ($type === FacilityType::Cottage) {
            $visit = $this->date($request->query('date'))
                ?? $this->date($request->query('check_in'))
                ?? $arrival?->toDateString();

            return $visit === null ? null : StayPeriod::fromDates($visit, $checkOut ?? $visit);
        }

        if ($arrival instanceof Carbon && $departure instanceof Carbon && $arrival->lessThan($departure)) {
            return new StayPeriod(
                $arrival,
                $departure,
                $arrival->isSameDay($departure) ? 'day_use' : 'overnight',
                $arrival->toDateString(),
                $departure->toDateString(),
            );
        }

        $checkIn = $this->date($request->query('check_in'));

        return $checkIn === null ? null : StayPeriod::fromDates($checkIn, $checkOut ?? $checkIn);
    }

    /**
     * A calendar date from the query string, or null when it is missing. The
     * format is already vetted by the request validation; parsing here keeps a
     * malformed value from reaching `StayPeriod` as a fatal error.
     */
    private function date(mixed $value): ?string
    {
        return $this->parse($value, 'Y-m-d')?->toDateString();
    }

    /**
     * A date-and-time from the query string, or null when it is missing or does
     * not fit the shape the wizard reads back.
     */
    private function moment(mixed $value): ?Carbon
    {
        return $this->parse($value, 'Y-m-d H:i');
    }

    private function parse(mixed $value, string $format): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat($format, trim($value));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The selection to carry into another service's tab.
     *
     * A cottage filter is a visit date and a session; every other service filters
     * by an arrival and a departure. Switching tabs therefore *translates* rather
     * than copies: a guest who filtered cottages by date lands on rooms filtered
     * by that same day, instead of on an unfiltered list they have to fill in
     * again. Party size follows either way.
     *
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    private function selectionFor(string $type, array $selection): array
    {
        $party = Arr::only($selection, ['guests', 'arrival', 'departure']);

        if ($type === FacilityType::Cottage->value) {
            $visit = $this->date($selection['date'] ?? null)
                ?? $this->date($selection['check_in'] ?? null)
                ?? $this->moment($selection['arrival'] ?? null)?->toDateString();

            return $this->canonical([
                'date' => $visit,
                'session' => $selection['session'] ?? null,
                ...$party,
            ]);
        }

        return $this->canonical([
            'check_in' => $this->date($selection['check_in'] ?? null) ?? $this->date($selection['date'] ?? null),
            'check_out' => $this->date($selection['check_out'] ?? null),
            ...$party,
        ]);
    }

    /**
     * The selection with its empty values dropped and its keys in one order.
     *
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    private function canonical(array $selection): array
    {
        $ordered = [];

        foreach (self::SELECTION_KEYS as $key) {
            if (filled($selection[$key] ?? null)) {
                $ordered[$key] = $selection[$key];
            }
        }

        return $ordered;
    }
}
