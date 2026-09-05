<?php

namespace App\Services\Payments;

use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayMongo checkout sessions for GCash, Maya and cards.
 *
 * Amounts are sent in centavos. The resort's existing proof-upload flow is left
 * untouched; this only adds a second, self-verifying path.
 */
class PayMongoGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return filled(config('payments.paymongo.secret_key'));
    }

    public function label(): string
    {
        return 'GCash / Maya / card via PayMongo';
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession
    {
        $this->guardConfigured();

        $reservation = $payment->reservation;

        $response = $this->request()->post('/checkout_sessions', [
            'data' => [
                'attributes' => [
                    'send_email_receipt' => false,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => "Reservation {$reservation->reservation_code} — {$reservation->facility->name}",
                    'reference_number' => $reservation->reservation_code,
                    'payment_method_types' => config('payments.paymongo.payment_methods'),
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'line_items' => [
                        [
                            'name' => $reservation->facility->name,
                            'quantity' => 1,
                            'currency' => config('payments.currency'),
                            'amount' => (int) round((float) $payment->amount * 100),
                        ],
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            Log::error('PayMongo checkout session failed', [
                'payment_id' => $payment->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new PaymentGatewayException('The payment provider rejected this checkout. Please try again or upload a receipt instead.');
        }

        $id = $response->json('data.id');
        $url = $response->json('data.attributes.checkout_url');

        if (! $id || ! $url) {
            throw new PaymentGatewayException('The payment provider returned an unexpected response.');
        }

        return new CheckoutSession($id, $url);
    }

    public function isPaid(string $sessionId): bool
    {
        $this->guardConfigured();

        $response = $this->request()->get("/checkout_sessions/{$sessionId}");

        if ($response->failed()) {
            throw new PaymentGatewayException('Could not confirm the payment status with the provider.');
        }

        foreach ($response->json('data.attributes.payments', []) as $providerPayment) {
            if (($providerPayment['attributes']['status'] ?? null) === 'paid') {
                return true;
            }
        }

        return false;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth((string) config('payments.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->baseUrl((string) config('payments.paymongo.base_url'));
    }

    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayException('PayMongo is selected as the gateway but no secret key is configured.');
        }
    }
}
