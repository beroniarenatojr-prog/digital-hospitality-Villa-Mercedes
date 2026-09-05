<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\Customer\ReservationPolicyException;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Exceptions\Reservation\ReservationCancellationException;
use App\Exceptions\Reservation\ReservationConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RescheduleReservationRequest;
use App\Http\Requests\Customer\StoreReservationRequest;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\Customer\CustomerReservationService;
use Carbon\Carbon;
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
        $reservations = $this->service->getMyReservations($request->user());

        return view('customer.reservations.index', compact('reservations'));
    }

    public function show(Request $request, Reservation $reservation): View
    {
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);

        return view('customer.reservations.show', compact('reservation'));
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));
        
        $reservation = $this->service->createReservation(
            $request->user(),
            $facility,
            Carbon::parse($request->validated('check_in')),
            Carbon::parse($request->validated('check_out')),
            $request->validated('guestCount'),
            [
                'early_check_in_hours' => (int) ($request->validated('early_check_in_hours') ?? 0),
                'late_check_out_hours' => (int) ($request->validated('late_check_out_hours') ?? 0),
            ]
        );

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation created successfully.');
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
        try {
            $this->service->cancelReservation($request->user(), $reservation, $request->input('reason'));
        } catch (ReservationPolicyException|ReservationCancellationException $e) {
            return redirect()->route('customer.reservations.show', $reservation)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation cancelled successfully.');
    }

    public function calculateReschedule(Request $request, Reservation $reservation): \Illuminate\Http\JsonResponse
    {
        // Must own the reservation
        $reservation = $this->service->getMyReservation($request->user(), $reservation->id);
        
        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => ['required', 'integer', 'min:1'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:' . config('resort.stay.max_additional_hours', 12)],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:' . config('resort.stay.max_additional_hours', 12)],
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
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate pricing for this selection. Please try different dates or contact support.',
                'debug' => $e->getMessage()
            ], 422);
        }
    }
}




