<?php

namespace App\Http\Controllers\Customer;

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
            $request->validated('guestCount')
        );

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation created successfully.');
    }

    public function reschedule(RescheduleReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        $this->service->rescheduleReservation(
            $request->user(),
            $reservation,
            $facility,
            Carbon::parse($request->validated('check_in')),
            Carbon::parse($request->validated('check_out')),
            $request->validated('guestCount')
        );

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation rescheduled successfully.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->service->cancelReservation($request->user(), $reservation, $request->input('reason'));

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
        ]);

        try {
            $facility = Facility::findOrFail($validated['facility_id']);
            $checkIn = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);
            $guestCount = $validated['guestCount'];

            $availability = $this->service->checkAvailability($facility, $checkIn, $checkOut, $reservation->id, $guestCount);

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
                0,
                0,
                $guestCount
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'base_amount' => $price->baseAmount,
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




