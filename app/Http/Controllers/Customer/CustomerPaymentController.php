<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\Payment\InvalidPaymentAmountException;
use App\Exceptions\Payment\PaymentGatewayException;
use App\Exceptions\Payment\PaymentOwnershipException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StartOnlinePaymentRequest;
use App\Http\Requests\Customer\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\Customer\CustomerPaymentService;
use App\Services\FileUploadService;
use App\Services\Payments\OnlinePaymentService;
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
        private readonly FileUploadService $fileUploadService,
        private readonly OnlinePaymentService $onlinePaymentService
    ) {}

    public function index(Request $request, Reservation $reservation): View
    {
        $payments = $this->paymentService->getPaymentsForReservation($request->user(), $reservation);

        return view('customer.payments.index', [
            'reservation' => $reservation,
            'payments' => $payments,
            'onlineCheckoutAvailable' => $this->onlinePaymentService->isAvailable(),
            'gatewayLabel' => $this->onlinePaymentService->gatewayLabel(),
        ]);
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

    public function checkout(StartOnlinePaymentRequest $request, Reservation $reservation): RedirectResponse
    {
        try {
            $url = $this->onlinePaymentService->startCheckout(
                $request->user(),
                $reservation,
                (float) $request->validated('amount'),
                \App\Enums\PaymentMethod::from($request->validated('payment_method'))
            );

            return redirect()->away($url);
        } catch (PaymentOwnershipException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidPaymentAmountException|PaymentGatewayException $e) {
            return redirect()->route('customer.payments.index', $reservation)
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Landing point after the guest finishes at the provider. The provider's own
     * API is the source of truth here, not this redirect.
     */
    public function returnFromGateway(Request $request, Payment $payment): RedirectResponse
    {
        $reservation = $payment->reservation;

        try {
            $paid = $this->onlinePaymentService->confirm($request->user(), $payment);
        } catch (PaymentOwnershipException $e) {
            abort(403, $e->getMessage());
        } catch (PaymentGatewayException $e) {
            return redirect()->route('customer.payments.index', $reservation)
                ->with('error', $e->getMessage().' Your payment may still be processing — refresh this page in a few minutes.');
        }

        return redirect()->route('customer.payments.index', $reservation)
            ->with(
                $paid ? 'success' : 'error',
                $paid
                    ? 'Payment received. Your reservation ledger has been updated.'
                    : 'We have not received a confirmation from the payment provider yet. It may still be processing.'
            );
    }

    public function cancelGateway(Request $request, Payment $payment): RedirectResponse
    {
        $reservation = $payment->reservation;

        try {
            $this->onlinePaymentService->abandon($request->user(), $payment);
        } catch (PaymentOwnershipException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('customer.payments.index', $reservation)
            ->with('error', 'Online payment cancelled. You can try again or upload a receipt instead.');
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
