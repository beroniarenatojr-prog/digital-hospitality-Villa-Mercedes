<?php

namespace App\Http\Controllers\Customer;

use App\Enums\ReservationRecordView;
use App\Exceptions\Availability\InvalidAvailabilityPeriodException;
use App\Exceptions\Customer\ReservationPolicyException;
use App\Exceptions\Pricing\InvalidPricingPeriodException;
use App\Exceptions\Pricing\NoApplicableRateException;
use App\Exceptions\Pricing\OverlappingSeasonalRatesException;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RescheduleReservationRequest;
use App\Http\Requests\Customer\StoreReservationRequest;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\Customer\CustomerReservationService;
use App\Services\Customer\UnfinishedBookings;
use App\Support\BookingDraft;
use App\Support\GuestInformation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerReservationController extends Controller
{
    public function __construct(
        private readonly CustomerReservationService $service,
        private readonly UnfinishedBookings $unfinished,
    ) {}

    public function index(Request $request): View
    {
        $tabs = $this->service->reservationTabs($request->user());
        $activeTab = $this->service->resolveReservationTab((string) $request->query('tab', ''), $tabs);
        $reservations = $this->service->getMyReservations($request->user(), $activeTab);

        // Bookings that were never submitted are not reservations, so they are not
        // in a tab — but they are the guest's own unfinished work, and the page
        // that lists their bookings is where they would look for it.
        $unfinished = $this->unfinished->for($request->user());

        return view('customer.reservations.index', compact('reservations', 'tabs', 'activeTab', 'unfinished'));
    }

    public function showGroup(Request $request, \App\Models\ReservationGroup $group): View
    {
        abort_unless($group->user_id === $request->user()->id, 403);
        $group->load(['reservations' => fn ($query) => $query->where('user_id', $request->user()->id)->with(['facility', 'payments'])]);

        return view('customer.reservations.group-show', compact('group'));
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);

        // One record, one facet at a time. The facet is part of the address so a
        // guest can bookmark "the arrival bit" of their booking, and so the tabs
        // are real links rather than JavaScript that the back button cannot undo.
        $recordView = ReservationRecordView::fromQuery($request->query('view'));

        return view('customer.reservations.show', compact('reservation', 'recordView'));
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        try {
            $this->service->createReservation(
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
                    'infants_count' => (int) ($request->validated('infants_count') ?? 0),
                    'children_count' => (int) ($request->validated('children_count') ?? 0),
                    'pets_included' => $request->boolean('pets_included'),
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

        // The booking exists now, so the wizard's draft for this space has done
        // its job: leaving it behind would fill the next booking's step 1 with
        // this one's dates.
        BookingDraft::forget($facility);

        // The request is not a booking yet, so the guest is left where its state
        // is: My reservations, on the Review pending tab. The next thing that
        // happens is a person at the resort, and they are emailed either way —
        // which is why the same page is where the deposit will open from.
        $confirmationMessage = 'Your reservation request was submitted. It is now in Review pending. Verify at least the 50% down payment before confirmation; 100% online payment is allowed. Uploaded proof remains under review until staff verify it. Any remaining balance is due at physical check-in.';

        return redirect()->route('customer.reservations.index', ['tab' => 'pending'])
            ->with('success', $confirmationMessage);
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
                    ...($request->has('special_requests') ? ['special_requests' => $request->validated('special_requests')] : []),
                ]
            );
        } catch (ReservationPolicyException|ReservationConflictException|InvalidReservationTransitionException $e) {
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', $reservation->policy_version === config('resort.policy.confirmed_version')
                ? 'Revised quote proposed. Review and accept it in Booking discussions; staff must recheck availability before confirming.'
                : 'Reservation rescheduled successfully.');
    }

    /**
     * Ask staff to withdraw a reservation that is still waiting for approval.
     *
     * This is the only self-service exit a guest has. A booking is never
     * cancelled from here: the status stays Pending until staff confirm, and a
     * reservation that is already approved is refused by the service.
     */
    public function withdraw(Request $request, Reservation $reservation): RedirectResponse
    {
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);
        $reason = $request->input('reason');

        try {
            $this->service->requestCancellation($request->user(), $reservation, $reason);
        } catch (ReservationPolicyException|InvalidReservationTransitionException $e) {
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', $reservation->policy_version === config('resort.policy.confirmed_version')
                ? 'Cancellation/refund requested. Staff notify the owner; owner approval and money returned are separate recorded stages.'
                : 'Withdrawal requested. The resort will confirm it. If you changed your mind, call the resort.');
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
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:2'],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:0'],
        ]);

        try {
            $facility = Facility::findOrFail($validated['facility_id']);
            $checkIn = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);
            $guestCount = $validated['guestCount'];
            $earlyCheckInHours = (int) ($validated['early_check_in_hours'] ?? 0);
            $lateCheckOutHours = (int) ($validated['late_check_out_hours'] ?? 0);
            $paxTier = $facility->id === $reservation->facility_id ? $reservation->pax_tier : null;

            $availability = $this->service->checkAvailability(
                $facility,
                $checkIn,
                $checkOut,
                $reservation->id,
                $guestCount,
                $earlyCheckInHours,
                $lateCheckOutHours,
                $paxTier
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
                $guestCount,
                $paxTier
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'base_amount' => $price->baseAmount,
                    'early_check_in_charge' => $price->earlyCheckInCharge,
                    'late_check_out_charge' => $price->lateCheckOutCharge,
                    'hall_extension_charge' => $price->hallExtensionCharge,
                    'extra_person_charge' => $price->extraPersonCharge,
                    'total_amount' => $price->totalAmount,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate pricing for this selection. Please try different dates or contact support.',
            ], 422);
        }
    }
}
