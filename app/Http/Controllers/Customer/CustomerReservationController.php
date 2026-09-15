<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ReservationStatus;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Exceptions\Customer\ReservationPolicyException;
use App\Exceptions\Pricing\InvalidPricingPeriodException;
use App\Exceptions\Pricing\NoApplicableRateException;
use App\Exceptions\Pricing\OverlappingSeasonalRatesException;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationCancellationException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RescheduleReservationRequest;
use App\Http\Requests\Customer\StoreReservationRequest;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\Customer\CustomerReservationService;
use App\Support\GuestInformation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerReservationController extends Controller
{
    public function __construct(
        private readonly CustomerReservationService $service
    ) {}

    public function index(Request $request): View
    {
        $tabs = $this->service->reservationTabs($request->user());
        $activeTab = $this->service->resolveReservationTab((string) $request->query('tab', ''), $tabs);
        $reservations = $this->service->getMyReservations($request->user(), $activeTab);

        return view('customer.reservations.index', compact('reservations', 'tabs', 'activeTab'));
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);

        return view('customer.reservations.show', compact('reservation'));
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        try {
            $reservation = $this->service->createReservation(
                $request->user(),
                $facility,
                Carbon::parse($request->validated('check_in')),
                Carbon::parse($request->validated('check_out')),
                $request->validated('guestCount'),
                [
                    'early_check_in_hours' => (int) ($request->validated('early_check_in_hours') ?? 0),
                    'late_check_out_hours' => (int) ($request->validated('late_check_out_hours') ?? 0),
                    'swimming_session_id' => $request->validated('swimming_session_id'),
                    'pax_tier' => $request->validated('pax_tier'),
                    'special_requests' => $request->validated('special_requests'),
                    'policies_accepted_at' => now(),
                    ...GuestInformation::fromValidated($request->validated()),
                ]
            );
        } catch (
            ReservationConflictException|
            InvalidAvailabilityPeriodException|
            InvalidPricingPeriodException|
            NoApplicableRateException|
            OverlappingSeasonalRatesException $e
        ) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($reservation->deposit_required !== null ? 'customer.payments.index' : 'customer.reservations.show', $reservation)
            ->with('success', $reservation->deposit_required !== null
                ? 'Your reservation is held temporarily. Submit the QR deposit before the deadline to secure it.'
                : 'Your request was sent to the resort for review.');
    }

    public function reschedule(RescheduleReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        try {
            $this->service->rescheduleReservation(
                $request->user(),
                $reservation,
                $facility,
                Carbon::parse($request->validated('check_in')),
                Carbon::parse($request->validated('check_out')),
                $request->validated('guestCount'),
                [
                    'early_check_in_hours' => (int) ($request->validated('early_check_in_hours') ?? 0),
                    'late_check_out_hours' => (int) ($request->validated('late_check_out_hours') ?? 0),
                ]
            );
        } catch (ReservationPolicyException|ReservationConflictException|InvalidReservationTransitionException $e) {
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation rescheduled successfully.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);
        $reason = $request->input('reason');

        try {
            if ($reservation->status === ReservationStatus::Pending) {
                $this->service->requestCancellation($request->user(), $reservation, $reason);

                return redirect()->route('customer.reservations.show', $reservation)
                    ->with('success', 'Withdrawal requested. The resort will confirm it. If you changed your mind, call the resort.');
            }

            $this->service->cancelReservation($request->user(), $reservation, $reason);
        } catch (ReservationPolicyException|ReservationCancellationException|InvalidReservationTransitionException $e) {
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation cancelled successfully.');
    }

    public function calculateReschedule(Request $request, Reservation $reservation): JsonResponse
    {
        // Must own the reservation
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => ['required', 'integer', 'min:1'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
        ]);

        try {
            $facility = Facility::findOrFail($validated['facility_id']);
            $checkIn = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);
            $guestCount = $validated['guestCount'];
            $earlyCheckInHours = (int) ($validated['early_check_in_hours'] ?? 0);
            $lateCheckOutHours = (int) ($validated['late_check_out_hours'] ?? 0);

            $availability = $this->service->checkAvailability(
                $facility,
                $checkIn,
                $checkOut,
                $reservation->id,
                $guestCount,
                $earlyCheckInHours,
                $lateCheckOutHours
            );

            if (! $availability->isAvailable) {
                return response()->json([
                    'success' => false,
                    'message' => $availability->reason ?: 'Facility is unavailable for the selected dates.',
                ], 422);
            }

            $price = $this->service->getEstimatedPrice(
                $facility,
                $checkIn,
                $checkOut,
                $earlyCheckInHours,
                $lateCheckOutHours,
                $guestCount
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
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate pricing for this selection. Please try different dates or contact support.',
            ], 422);
        }
    }
}
