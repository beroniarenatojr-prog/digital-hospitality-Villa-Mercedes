<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RescheduleReservationRequest;
use App\Http\Requests\Customer\StoreReservationRequest;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Reservation;
use App\Services\PricingService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $service,
        private readonly PricingService $pricingService
    ) {}

    public function index(Request $request): View
    {
        $query = Reservation::with(['user', 'facility.facilityType']);

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by Source
        if ($request->filled('source')) {
            $query->where('reservation_source', $request->input('source'));
        }

        // Filter by Facility Type
        if ($request->filled('facility_type')) {
            $typeModel = FacilityType::where('slug', $request->input('facility_type'))->first();
            if ($typeModel) {
                $query->whereHas('facility', fn ($q) => $q->where('facility_type_id', $typeModel->id));
            }
        }

        // Filter by Facility
        if ($request->filled('facility_id')) {
            $query->where('facility_id', $request->input('facility_id'));
        }

        // Filter by Dates (Check-in or Check-out overlaps with selected period)
        if ($request->filled('date_from')) {
            $query->where('check_out', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_until')) {
            $query->where('check_in', '<=', $request->input('date_until'));
        }

        // Search by Reservation Code or Customer Name
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('reservation_code', 'like', $search)
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('email', 'like', $search));
            });
        }

        $reservations = $query->orderBy('created_at', 'desc')->paginate(20);

        // Data for dropdowns
        $facilityTypes = FacilityType::where('is_active', true)->get();
        $facilities = Facility::where('is_active', true)->orderBy('name')->get();

        return view('admin.reservations.index', compact('reservations', 'facilityTypes', 'facilities'));
    }

    public function show(Reservation $reservation): View
    {
        $reservation->load(['user', 'facility.facilityType', 'payments']);

        $facilities = Facility::where('is_active', true)->orderBy('name')->get();

        return view('admin.reservations.show', compact('reservation', 'facilities'));
    }

    public function approve(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $this->service->changeStatus($reservation, ReservationStatus::Approved, $request->user());
        } catch (InvalidReservationTransitionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reservation approved successfully.');
    }

    public function reject(Request $request, Reservation $reservation): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->service->changeStatus($reservation, ReservationStatus::Rejected, $request->user(), $request->input('reason'));
        } catch (InvalidReservationTransitionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reservation rejected successfully.');
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->cancel($reservation, $request->user(), $request->input('reason'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reservation cancelled successfully.');
    }

    public function calculateReschedule(RescheduleReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));
        $checkIn = Carbon::parse($request->validated('check_in'));
        $checkOut = Carbon::parse($request->validated('check_out'));
        $pax = $request->validated('guestCount');

        try {
            $breakdown = $this->pricingService->calculate(
                $facility,
                $checkIn,
                $checkOut,
                0, // early check in hours not yet in form
                0, // late check out hours not yet in form
                $pax
            );

            return response()->json([
                'success' => true,
                'breakdown' => [
                    'base_amount' => $breakdown->baseAmount / 100,
                    'additional_charges' => ($breakdown->earlyCheckInCharge + $breakdown->lateCheckOutCharge + $breakdown->extraPersonCharge) / 100,
                    'total_amount' => $breakdown->totalAmount / 100,
                ],
                'facility_name' => $facility->name,
                'dates' => $checkIn->format('M d, Y') . ' - ' . $checkOut->format('M d, Y'),
                'pax' => $pax,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function reschedule(RescheduleReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        try {
            $this->service->reschedule(
                $reservation,
                $facility,
                Carbon::parse($request->validated('check_in')),
                Carbon::parse($request->validated('check_out')),
                $request->user(),
                $request->validated('guestCount')
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reservation rescheduled successfully.');
    }

    public function storeWalkIn(StoreReservationRequest $request): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        try {
            $reservation = $this->service->createReservation(
                $facility,
                $request->user(), // For now, the admin is treated as the customer.
                Carbon::parse($request->validated('check_in')),
                Carbon::parse($request->validated('check_out')),
                ReservationSource::WalkIn,
                $request->validated('guestCount')
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', 'Walk-in reservation created successfully.');
    }
}
