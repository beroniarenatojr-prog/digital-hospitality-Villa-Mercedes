<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Customer\CustomerPaymentService;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPaymentController extends Controller
{
    public function __construct(
        private readonly CustomerPaymentService $paymentService,
        private readonly FileUploadService $fileUploadService
    ) {}

    public function index(Request $request, Reservation $reservation): View
    {
        $payments = $this->paymentService->getPaymentsForReservation($request->user(), $reservation);

        return view('customer.payments.index', compact('reservation', 'payments'));
    }

    public function store(StorePaymentRequest $request, Reservation $reservation): RedirectResponse
    {
        // 1. Store the uploaded proof
        $fileInfo = $this->fileUploadService->store($request->file('proof'));
        $proofPath = $fileInfo['path'];

        try {
            // 2. Delegate to service to create payment
            $payment = $this->paymentService->submitPayment(
                $request->user(),
                $reservation,
                $request->validated('amount'),
                \App\Enums\PaymentMethod::from($request->validated('payment_method')),
                $proofPath
            );

            // Also map reference number directly to avoid rewriting Service
            if ($request->filled('reference_number')) {
                $payment->update(['reference_number' => $request->validated('reference_number')]);
            }

            return redirect()->route('customer.reservations.show', $reservation)
                ->with('success', 'Payment submitted successfully.');

        } catch (Exception $e) {
            // 3. Prevent orphaned files if payment creation fails
            $this->fileUploadService->delete($proofPath);
            throw $e;
        }
    }

    public function downloadProof(Request $request, Payment $payment): StreamedResponse
    {
        // Ownership verification (throws exception if not owned)
        $this->paymentService->getPaymentsForReservation($request->user(), $payment->reservation);

        if (! $payment->proof_path || ! Storage::disk('uploads')->exists($payment->proof_path)) {
            abort(404, 'Proof of payment not found.');
        }

        return Storage::disk('uploads')->download($payment->proof_path);
    }
}
