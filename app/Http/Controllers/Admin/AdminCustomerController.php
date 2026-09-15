<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\CustomerManagementService;
use App\Support\LiveFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCustomerController extends Controller
{
    public function __construct(
        private readonly CustomerManagementService $service
    ) {}

    public function index(Request $request): View
    {
        $query = User::role(Role::Customer->value)
            ->withCount('reservations')
            ->withMax('reservations', 'check_in');

        // Search
        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        // Filter: has reservations
        if ($request->filled('has_reservations')) {
            if ($request->input('has_reservations') === '1') {
                $query->has('reservations');
            } else {
                $query->doesntHave('reservations');
            }
        }

        // Filter: registration date range
        if ($request->filled('registered_from')) {
            $query->where('created_at', '>=', $request->input('registered_from'));
        }
        if ($request->filled('registered_until')) {
            $query->where('created_at', '<=', $request->input('registered_until').' 23:59:59');
        }

        $customers = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        if (LiveFilter::wantsPartial($request)) {
            return view('admin.customers.partials.results', compact('customers'));
        }

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Request $request, int $customerId): View
    {
        $customer = $this->service->getCustomerWithReservations($request->user(), $customerId);

        // Load facility info for reservations
        $customer->load('reservations.facility.facilityType', 'reservations.payments');

        // Compute stats without N+1
        $reservations = $customer->reservations;
        $stats = [
            'total_reservations' => $reservations->count(),
            'upcoming' => $reservations->where('status', ReservationStatus::Approved)->where('check_in', '>', now())->count(),
            'completed' => $reservations->where('status', ReservationStatus::Completed)->count(),
            'cancelled' => $reservations->whereIn('status', [ReservationStatus::Cancelled, ReservationStatus::Rejected])->count(),
        ];

        // Payment stats from already-loaded payments
        $allPayments = $reservations->flatMap->payments;
        $paymentStats = [
            'total_payments' => $allPayments->count(),
            'verified_total' => $allPayments->where('status', PaymentStatus::Verified)->sum('amount'),
            'pending_count' => $allPayments->where('status', PaymentStatus::Pending)->count(),
            'rejected_count' => $allPayments->where('status', PaymentStatus::Rejected)->count(),
        ];

        return view('admin.customers.show', compact('customer', 'stats', 'paymentStats'));
    }
}
