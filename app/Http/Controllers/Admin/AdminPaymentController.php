<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Payment\InvalidPaymentTransitionException;
use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Payment\UnauthorizedPaymentActionException;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Payment;
use App\Services\FileUploadService;
use App\Services\PaymentService;
use App\Support\LiveFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPaymentController extends Controller
{
    public function storeOnSite(Request $request, \App\Models\Reservation $reservation): RedirectResponse
    {
        $request->validate(['collected_at' => ['required', 'date']]);
        $request->merge(['collected_at' => \Carbon\Carbon::parse($request->input('collected_at'), \App\Support\DepositDeadline::TIMEZONE)->setTimezone(config('app.timezone'))->toDateTimeString()]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', \Illuminate\Validation\Rule::in(\App\Enums\PaymentMethod::values())],
            'reference_number' => ['nullable', 'required_unless:method,cash', 'string', 'max:255'],
            'collected_at' => ['required', 'date', 'before_or_equal:now'],
            'reconciliation_note' => ['nullable', 'string', 'max:500'], 'funds_received' => ['accepted'],
        ]);
        try { $this->service->recordOnSite($reservation, $request->user(), $data); }
        catch (InvalidPaymentAmountException|UnauthorizedPaymentActionException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'On-site payment recorded.');
    }
    public function __construct(
        private readonly PaymentService $service,
        private readonly FileUploadService $fileUploadService
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

        // Search the reservation’s guest details, including walk-ins where the
        // account holder may be a staff member rather than the guest.
        if ($request->filled('search')) {
            $query->whereHas('reservation', fn ($reservationQuery) => $reservationQuery
                ->matchingGuestLookup((string) $request->input('search')));
        }

        $overdueReviews = Payment::where('source', 'customer_deposit')->where('status', 'pending')->where('created_at', '<=', now()->subHours(24))->count();
        // Escalate the oldest waiting proofs before other payments.
        $payments = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'pending' THEN created_at END ASC")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if (LiveFilter::wantsPartial($request)) {
            return view('admin.payments.partials.results', compact('payments'));
        }

        // Data for dropdowns
        $facilityTypes = FacilityType::where('is_active', true)->get();
        $facilities = Facility::where('is_active', true)->orderBy('name')->get();

        return view('admin.payments.index', compact('payments', 'facilityTypes', 'facilities', 'overdueReviews'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['reservation.user', 'reservation.facility.facilityType', 'reservation.payments', 'verifiedBy']);

        return view('admin.payments.show', compact('payment'));
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate(['overpayment_note' => ['nullable', 'string', 'max:500'], 'funds_received' => [$payment->source === 'customer_deposit' ? 'accepted' : 'nullable']]);

        try {
            $this->service->verifyPayment($payment, $request->user(), $request->input('overpayment_note'));
        } catch (InvalidPaymentAmountException|InvalidPaymentTransitionException|UnauthorizedPaymentActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment verified successfully.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'funds_not_received' => [$payment->source === 'customer_deposit' ? 'accepted' : 'nullable'],
        ]);

        try {
            $this->service->rejectPayment($payment, $request->user(), $request->input('reason'));
        } catch (InvalidPaymentTransitionException|UnauthorizedPaymentActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment rejected successfully.');
    }

    public function requestTopUp(Request $request, \App\Models\Reservation $reservation): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        try { $this->service->requestDepositTopUp($reservation, $request->user(), $request->string('reason')->toString()); }
        catch (InvalidPaymentAmountException|UnauthorizedPaymentActionException $e) { return back()->with('error', $e->getMessage()); }
        return back()->with('success', 'Deposit top-up requested. The customer can see the amount and new deadline.');
    }

    public function viewProof(Payment $payment): StreamedResponse
    {
        return $this->fileUploadService->view((string) $payment->proof_path);
    }

    public function downloadProof(Payment $payment): StreamedResponse
    {
        return $this->fileUploadService->download((string) $payment->proof_path);
    }
}
