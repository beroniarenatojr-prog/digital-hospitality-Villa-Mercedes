<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

    public function index(): View
    {
        $payments = Payment::with(['reservation.user', 'reservation.facility'])->orderBy('created_at', 'desc')->get();

        return view('admin.payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['reservation.user', 'reservation.facility']);

        return view('admin.payments.show', compact('payment'));
    }

    public function verify(Request $request, Payment $payment): RedirectResponse
    {
        $this->service->verifyPayment($payment, $request->user());

        return redirect()->back()->with('success', 'Payment verified successfully.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $this->service->rejectPayment($payment, $request->user(), $request->input('reason'));

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
