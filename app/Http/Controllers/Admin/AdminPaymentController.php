<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\UnauthorizedPaymentActionException;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $service
    ) {}

    public function index(Request $request): View
    {
        $query = Payment::with(['reservation.user', 'reservation.facility.facilityType']);

        // Filter by Payment Status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by Payment Method
        if ($request->filled('method')) {
            $query->where('method', $request->input('method'));
        }

        // Filter by Date Range
        if ($request->filled('date_from')) {
            $query->where('payment_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_until')) {
            $query->where('payment_date', '<=', $request->input('date_until'));
        }

        // Filter by Facility
        if ($request->filled('facility_id')) {
            $query->whereHas('reservation', fn ($q) => $q->where('facility_id', $request->input('facility_id')));
        }

        // Filter by Facility Type
        if ($request->filled('facility_type')) {
            $typeModel = FacilityType::where('slug', $request->input('facility_type'))->first();
            if ($typeModel) {
                $query->whereHas('reservation.facility', fn ($q) => $q->where('facility_type_id', $typeModel->id));
            }
        }

        // Search by Reservation Code or Customer Name
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('reservation', fn ($rq) => $rq->where('reservation_code', 'like', $search))
                  ->orWhereHas('reservation.user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('email', 'like', $search));
            });
        }

        // Prioritize pending payments, then most recent
        $payments = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                          ->orderBy('created_at', 'desc')
                          ->paginate(20);

        // Data for dropdowns
        $facilityTypes = FacilityType::where('is_active', true)->get();
        $facilities = Facility::where('is_active', true)->orderBy('name')->get();

        return view('admin.payments.index', compact('payments', 'facilityTypes', 'facilities'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['reservation.user', 'reservation.facility.facilityType', 'reservation.payments', 'verifiedBy']);

        return view('admin.payments.show', compact('payment'));
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        try {
            $this->service->verifyPayment($payment, $request->user());
        } catch (InvalidPaymentTransitionException|UnauthorizedPaymentActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment verified successfully.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->service->rejectPayment($payment, $request->user(), $request->input('reason'));
        } catch (InvalidPaymentTransitionException|UnauthorizedPaymentActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment rejected successfully.');
    }

    public function downloadProof(Payment $payment): StreamedResponse
    {
        if (! $payment->proof_path || ! Storage::disk('uploads')->exists($payment->proof_path)) {
            abort(404, 'Proof of payment not found.');
        }

        return Storage::disk('uploads')->download($payment->proof_path);
    }
}
