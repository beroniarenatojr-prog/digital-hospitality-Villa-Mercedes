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
        $this->service->cancelReservation($request->user(), $reservation);

        return redirect()->route('customer.reservations.show', $reservation)
            ->with('success', 'Reservation cancelled successfully.');
    }
}




