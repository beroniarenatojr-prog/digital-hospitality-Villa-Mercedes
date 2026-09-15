<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\Reservation\InvalidReservationTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWalkInReservationRequest;
use App\Http\Requests\Admin\RescheduleReservationRequest;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Reservation;
use App\Models\SwimmingSession;
use App\Services\Admin\DecisionSupportService;
use App\Services\PricingService;
use App\Services\CottageBookingService;
use App\Services\ReservationService;
use App\Support\AdminCatalogCache;
use App\Support\GuestInformation;
use App\Support\LiveFilter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $service,
        private readonly PricingService $pricingService,
        private readonly CottageBookingService $cottageBookingService,
        private readonly DecisionSupportService $decisionSupport
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

        if ($request->filled('search')) {
            $query->matchingGuestLookup((string) $request->input('search'));
        }

        $reservations = $query->orderBy('created_at', 'desc')->paginate(20);

        if (LiveFilter::wantsPartial($request)) {
            return view('admin.reservations.partials.results', compact('reservations'));
        }

        $arrivalsToday = Reservation::query()
            ->with(['user', 'facility.facilityType'])
            ->arrivingOn(now())
            ->get();

        $facilityTypes = FacilityType::where('is_active', true)->get();
        $filterFacilities = Facility::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $loadWalkInCatalog = $request->boolean('walk_in') || $request->session()->has('errors');
        $facilities = $loadWalkInCatalog ? AdminCatalogCache::bookableFacilities() : collect();

        return view('admin.reservations.index', compact(
            'reservations',
            'arrivalsToday',
            'facilityTypes',
            'filterFacilities',
            'facilities'
        ));
    }

    public function show(Reservation $reservation): View
    {
        $reservation->load(['user', 'facility.facilityType', 'payments', 'activities.causer']);

        $facilities = AdminCatalogCache::bookableFacilities();

        $impact = null;
        $extraHours = null;

        // Decision support only means something while the reservation can still
        // be acted on.
        if (in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
            $impact = $this->decisionSupport->analyze($reservation);
            $extraHours = $reservation->facility->isCottage()
                ? null
                : $this->decisionSupport->extraHourOptions($reservation);
        }

        $swimmingSessions = SwimmingSession::where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.reservations.show', compact('reservation', 'facilities', 'impact', 'extraHours', 'swimmingSessions'));
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

    public function confirmWithdrawal(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $this->service->confirmWithdrawal($reservation, $request->user());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Withdrawal confirmed. The reservation is cancelled.');
    }

    public function calculateReschedule(RescheduleReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));
        $checkIn = Carbon::parse($request->validated('check_in'));
        $checkOut = Carbon::parse($request->validated('check_out'));
        $pax = $request->validated('guestCount');
        $earlyCheckInHours = (int) ($request->validated('early_check_in_hours') ?? 0);
        $lateCheckOutHours = (int) ($request->validated('late_check_out_hours') ?? 0);

        try {
            $breakdown = $facility->isCottage()
                ? $this->cottageBookingService->price($facility, $request->validated('swimming_session_id'), $checkIn, $checkOut)
                : $this->pricingService->calculate(
                $facility,
                $checkIn,
                $checkOut,
                $earlyCheckInHours,
                $lateCheckOutHours,
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
                'dates' => $checkIn->format('M d, Y g:i A').' - '.$checkOut->format('M d, Y g:i A'),
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
                $request->validated('guestCount'),
                [
                    'early_check_in_hours' => (int) ($request->validated('early_check_in_hours') ?? 0),
                    'late_check_out_hours' => (int) ($request->validated('late_check_out_hours') ?? 0),
                    'swimming_session_id' => $request->validated('swimming_session_id'),
                ]
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reservation rescheduled successfully.');
    }

    public function checkIn(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $this->service->checkIn($reservation, $request->user());
        } catch (InvalidReservationTransitionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Guest checked in.');
    }

    public function checkOut(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $this->service->checkOut($reservation, $request->user());
        } catch (InvalidReservationTransitionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Guest checked out and the reservation is completed.');
    }

    public function complete(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $this->service->changeStatus($reservation, ReservationStatus::Completed, $request->user());
        } catch (InvalidReservationTransitionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Reservation marked as completed.');
    }

    public function storeWalkIn(StoreWalkInReservationRequest $request): RedirectResponse
    {
        $facility = Facility::findOrFail($request->validated('facility_id'));

        try {
            $reservation = $this->service->createReservation(
                $facility,
                $request->user(),
                Carbon::parse($request->validated('check_in')),
                Carbon::parse($request->validated('check_out')),
                ReservationSource::WalkIn,
                $request->validated('guestCount'),
                [
                    'early_check_in_hours' => (int) ($request->validated('early_check_in_hours') ?? 0),
                    'late_check_out_hours' => (int) ($request->validated('late_check_out_hours') ?? 0),
                    'admin_notes' => $request->validated('admin_notes'),
                    ...GuestInformation::fromValidated($request->validated()),
                ]
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.reservations.show', $reservation)
            ->with('success', 'Walk-in reservation created successfully.');
    }
}
