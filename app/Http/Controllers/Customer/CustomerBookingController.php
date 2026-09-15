<?php

namespace App\Http\Controllers\Customer;

use App\Enums\RateType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SendBookingMagicLinkRequest;
use App\Models\Facility;
use App\Models\SwimmingSession;
use App\Services\BookingMagicLinkService;
use App\Services\CottageBookingService;
use App\Services\Customer\CustomerReservationService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerBookingController extends Controller
{
    public function __construct(
        private readonly CustomerReservationService $reservationService,
        private readonly BookingMagicLinkService $magicLinkService,
        private readonly CottageBookingService $cottageBookingService,
    ) {}

    public function availability(Request $request, Facility $facility): View|RedirectResponse
    {
        abort_unless($facility->is_active, 404);
        if ($facility->isCottage()) {
            return redirect()->to(route('resort.show').'#cottage-reservations');
        }
        if (! $facility->isOnlineBookable()) {
            return redirect()->route('facilities.show', $facility);
        }

        $prefill = $this->resolvePrefill($request, $facility);

        return view('pages.service-availability', compact('facility', 'prefill'));
    }

    public function create(Request $request, Facility $facility): View|RedirectResponse
    {
        abort_if(! $facility->is_active, 404);

        if (! $facility->isOnlineBookable()) {
            return redirect()
                ->route('facilities.show', $facility)
                ->with('error', 'This package cannot be reserved online. Please call the resort to arrange it.');
        }

        $facility->load(['facilityType', 'galleryImages', 'rates']);

        if ($facility->isCottage()) {
            $sessions = SwimmingSession::query()->where('is_active', true)->orderBy('sort_order')->get();
            $selectedSession = $sessions->firstWhere('slug', $request->query('session')) ?? $sessions->first();
            $visitDate = $request->query('date', now()->addDay()->toDateString());

            return view('customer.booking.cottage', compact('facility', 'sessions', 'selectedSession', 'visitDate'));
        }

        $earlyCheckInRate = $facility->rates
            ->where('is_active', true)
            ->first(fn ($rate) => $rate->type === RateType::AdditionalCharge && $rate->name === PricingService::EARLY_CHECK_IN_RATE_NAME);

        $lateCheckOutRate = $facility->rates
            ->where('is_active', true)
            ->first(fn ($rate) => $rate->type === RateType::AdditionalCharge && $rate->name === PricingService::LATE_CHECK_OUT_RATE_NAME);

        $baseRate = $facility->rates
            ->where('is_active', true)
            ->first(fn ($rate) => $rate->type === RateType::Base);

        $extraPersonRate = $facility->rates
            ->where('is_active', true)
            ->first(fn ($rate) => $rate->type === RateType::ExtraPerson);

        $prefill = $this->resolvePrefill($request, $facility);

        return view('customer.booking.create', compact(
            'facility',
            'earlyCheckInRate',
            'lateCheckOutRate',
            'baseRate',
            'extraPersonRate',
            'prefill'
        ));
    }

    /**
     * Turn the dates picked on the public availability bar into wizard values.
     *
     * The bar only collects calendar dates, so the resort's published day-use and
     * overnight hours supply the times. Nothing is submitted for the guest.
     *
     * @return array{check_in: ?string, check_out: ?string, guest_count: ?int, preset: ?string, pax_tier: ?int, availability_checked: bool}
     */
    private function resolvePrefill(Request $request, Facility $facility): array
    {
        $paxTier = filter_var($request->query('pax_tier'), FILTER_VALIDATE_INT);
        $paxTier = $paxTier !== false && $paxTier > 0 ? $paxTier : null;
        $empty = [
            'check_in' => null,
            'check_out' => null,
            'guest_count' => null,
            'preset' => null,
            'pax_tier' => $paxTier,
            // This is only a UX continuation hint. The authenticated wizard still
            // calls the calculation endpoint before allowing the guest to proceed.
            'availability_checked' => $request->query('availability_checked') === '1',
        ];

        $guestCount = filter_var($request->query('guests'), FILTER_VALIDATE_INT);
        $maxGuests = $paxTier !== null
            ? min($facility->effectiveMaxCapacity() ?? $paxTier, $paxTier)
            : $facility->effectiveMaxCapacity();

        if ($guestCount !== false && $guestCount > 0) {
            $empty['guest_count'] = $maxGuests ? min($guestCount, $maxGuests) : $guestCount;
        }

        if ($request->filled('arrival') && $request->filled('departure')) {
            $selection = $request->validate([
                'arrival' => ['required', 'date_format:Y-m-d H:i'],
                'departure' => ['required', 'date_format:Y-m-d H:i', 'after:arrival'],
            ]);

            return [...$empty, 'check_in' => $selection['arrival'], 'check_out' => $selection['departure']];
        }

        $checkInDate = $this->parseDate($request->query('check_in'));

        if (! $checkInDate) {
            return $empty;
        }

        $checkOutDate = $this->parseDate($request->query('check_out')) ?? $checkInDate->copy()->addDay();

        if ($checkOutDate->lessThan($checkInDate)) {
            $checkOutDate = $checkInDate->copy()->addDay();
        }

        if ($facility->isFlyerPackage()) {
            [$arrival, $departure] = $facility->occupancyWindowForDate($checkInDate, $checkOutDate);

            return [
                ...$empty,
                'check_in' => $arrival->format('Y-m-d H:i'),
                'check_out' => $departure->format('Y-m-d H:i'),
                'preset' => $facility->stay_category?->value,
            ];
        }

        // A same-day request is a day-use visit; anything longer is an overnight stay.
        $preset = $checkOutDate->isSameDay($checkInDate) ? 'day_use' : 'overnight';

        if ($preset === 'day_use') {
            $start = config('resort.stay.day_use_hours.start');
            $end = config('resort.stay.day_use_hours.end');
        } else {
            $start = config('resort.stay.overnight_hours.check_in');
            $end = config('resort.stay.overnight_hours.check_out');
        }

        return [
            ...$empty,
            'check_in' => $checkInDate->copy()->setTimeFromTimeString($start)->format('Y-m-d H:i'),
            'check_out' => $checkOutDate->copy()->setTimeFromTimeString($end)->format('Y-m-d H:i'),
            'preset' => $preset,
        ];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function calculate(Request $request, Facility $facility): JsonResponse
    {
        abort_if(! $facility->is_active, 404);

        if ($facility->isCottage()) {
            $validated = $request->validate([
                'visit_date' => ['required', 'date_format:Y-m-d'],
                'swimming_session_id' => ['required', 'integer'],
                'guestCount' => ['required', 'integer', 'min:1'],
            ]);

            try {
                $resolved = $this->cottageBookingService->resolve($facility, $validated['swimming_session_id'], $validated['visit_date']);
                $availability = $this->reservationService->checkAvailability(
                    $facility, $resolved['check_in'], $resolved['check_out'], null, $validated['guestCount']
                );
                if (! $availability->isAvailable) {
                    return response()->json(['success' => false, 'message' => $availability->reason], 422);
                }

                $price = $this->cottageBookingService->price(
                    $facility, $resolved['session']->id, $resolved['check_in'], $resolved['check_out']
                );

                return response()->json(['success' => true, 'data' => [
                    'base_amount' => $price->baseAmount,
                    'early_check_in_charge' => 0,
                    'late_check_out_charge' => 0,
                    'extra_person_charge' => 0,
                    'total_amount' => $price->totalAmount,
                    'check_in' => $resolved['check_in']->toDateTimeString(),
                    'check_out' => $resolved['check_out']->toDateTimeString(),
                ]]);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : 'Unable to check this cottage. Please try another session.'], 422);
            }
        }

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => ['required', 'integer', 'min:1'],
            'pax_tier' => ['nullable', 'integer', 'min:1'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
        ]);

        try {
            $checkIn = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);
            $guestCount = $validated['guestCount'];
            $paxTier = isset($validated['pax_tier']) ? (int) $validated['pax_tier'] : null;
            $earlyCheckInHours = (int) ($validated['early_check_in_hours'] ?? 0);
            $lateCheckOutHours = (int) ($validated['late_check_out_hours'] ?? 0);
            $maxCapacity = $facility->effectiveMaxCapacity();
            if ($paxTier !== null) {
                $maxCapacity = $maxCapacity === null ? $paxTier : min($maxCapacity, $paxTier);
            }

            // 1. Check Availability
            $availability = $this->reservationService->checkAvailability(
                $facility,
                $checkIn,
                $checkOut,
                null,
                $guestCount,
                $earlyCheckInHours,
                $lateCheckOutHours,
                $paxTier
            );

            // Check capacity strictly here (though AvailabilityService does it, if it doesn't we enforce it)
            if ($maxCapacity !== null && $guestCount > $maxCapacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guest count exceeds facility maximum capacity.',
                ], 422);
            }

            if (! $availability->isAvailable) {
                return response()->json([
                    'success' => false,
                    'message' => $availability->reason ?: 'Facility is unavailable for the selected dates.',
                ], 422);
            }

            // 2. Estimate Price
            $price = $this->reservationService->getEstimatedPrice(
                $facility,
                $checkIn,
                $checkOut,
                $earlyCheckInHours,
                $lateCheckOutHours,
                $guestCount,
                $paxTier
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'base_amount' => $price->baseAmount,
                    'early_check_in_charge' => $price->earlyCheckInCharge,
                    'late_check_out_charge' => $price->lateCheckOutCharge,
                    'extra_person_charge' => $price->extraPersonCharge,
                    'total_amount' => $price->totalAmount,
                ],
            ]);

        } catch (\Exception $e) {
            // Map raw backend exceptions to safe user messages
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate pricing for this selection. Please try different dates or contact support.',
            ], 422);
        }
    }

    public function sendMagicLink(SendBookingMagicLinkRequest $request, Facility $facility): RedirectResponse
    {
        abort_if(! $facility->is_active || ! $facility->isOnlineBookable(), 404);

        $validated = $request->validated();

        if ((int) $validated['facility_id'] !== $facility->id) {
            abort(404);
        }

        $this->magicLinkService->send(
            $validated['email'],
            $validated,
            (string) $request->ip(),
        );

        return redirect()
            ->route('customer.booking.create', $facility)
            ->withInput($request->except('email'))
            ->with('success', BookingMagicLinkService::SENT_STATUS);
    }

    public function consumeMagicLink(Request $request, string $draft): RedirectResponse
    {
        try {
            $reservation = $this->magicLinkService->consume($draft);
        } catch (ValidationException $e) {
            $message = $e->errors()['email'][0] ?? 'This confirmation link is no longer valid.';
            $facilityId = $e->errors()['facility_id'][0] ?? null;

            if (filled($facilityId)) {
                return redirect()
                    ->route('customer.booking.create', $facilityId)
                    ->with('error', $message);
            }

            return redirect()
                ->route('home')
                ->with('error', $message);
        }

        $finishAccount = $request->user()?->isLightweight() || $request->user()?->needsContactDetails();

        return redirect()
            ->route('customer.reservations.show', $reservation)
            ->with('success', $finishAccount
                ? 'Reservation created. Add your name, mobile number, and a password in Profile so you can sign in next time.'
                : 'Reservation created. Save the arrival pass or keep the confirmation email — you will not need to sign in at the front desk.');
    }
}
