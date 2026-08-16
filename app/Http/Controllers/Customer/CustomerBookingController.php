<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Services\Customer\CustomerReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CustomerBookingController extends Controller
{
    public function __construct(
        private readonly CustomerReservationService $reservationService
    ) {}

    public function create(Facility $facility): View
    {
        abort_if(! $facility->is_active, 404);

        $facility->load(['facilityType', 'galleryImages']);

        return view('customer.booking.create', compact('facility'));
    }

    public function calculate(Request $request, Facility $facility): JsonResponse
    {
        abort_if(! $facility->is_active, 404);

        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $checkIn = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);
            $guestCount = $validated['guestCount'];

            // 1. Check Availability
            $availability = $this->reservationService->checkAvailability($facility, $checkIn, $checkOut);
            
            // Check capacity strictly here (though AvailabilityService does it, if it doesn't we enforce it)
            if ($guestCount > $facility->effectiveMaxCapacity()) {
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
                0, // early checkin
                0, // late checkout
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
            // Map raw backend exceptions to safe user messages
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate pricing for this selection. Please try different dates or contact support.'
            ], 422);
        }
    }
}
