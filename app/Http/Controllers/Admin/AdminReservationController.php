<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RescheduleReservationRequest;
use App\Http\Requests\Customer\StoreReservationRequest;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $service
    ) {}

    public function index(Request $request): View
    {
        $reservations = Reservation::with(['user', 'facility'])->orderBy('created_at', 'desc')->get();

        return view('admin.reservations.index', compact('reservations'));
    }

    public function show(Reservation $reservation): View
    {
        $reservation->load(['user', 'facility', 'payments']);

        return view('admin.reservations.show', compact('reservation'));
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->service->approve($reservation, $request->user());

        return redirect()->back()->with('success', 'Reservation approved successfully.');
    }

    public function reject(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->service->reject($reservation, $request->user(), $request->input('reason'));

        return redirect()->back()->with('success', 'Reservation rejected successfully.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->service->cancel($reservation, $request->user(), $request->input('reason'));

        return redirect()->back()->with('success', 'Reservation cancelled successfully.');
    }

    public function reschedule(RescheduleReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        $this->service->reschedule(
            $reservation,
            $facility,
            Carbon::parse($request->validated('check_in')),
            Carbon::parse($request->validated('check_out')),
            $request->user(),
            $request->validated('pax')
        );

        return redirect()->back()->with('success', 'Reservation rescheduled successfully.');
    }

    public function storeWalkIn(StoreReservationRequest $request): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        // For WalkIn, the user creating it (Admin/Staff) might not be the "customer"
        // But for simplicity in Phase 4A, we pass the authenticated user.
        // A full WalkIn implementation would select/create a customer user first.
        $reservation = $this->service->createReservation(
            $facility,
            $request->user(),
            Carbon::parse($request->validated('check_in')),
            Carbon::parse($request->validated('check_out')),
            \App\Enums\ReservationSource::WalkIn,
            $request->validated('pax')
        );

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', 'Walk-in reservation created successfully.');
    }
}
