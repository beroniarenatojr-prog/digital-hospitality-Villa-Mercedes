<?php

namespace App\Services\Payments;

use App\Exceptions\Payment\PaymentGatewayException;
use App\Models\Payment;

/**
 * The default: no live gateway. Guests pay over the counter or send an e-wallet
 * transfer and upload the receipt for staff to verify.
 */
class ManualPaymentGateway implements PaymentGateway
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function label(): string
    {
        return 'Manual verification';
    }

    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession
    {
        throw new PaymentGatewayException(
            'Online checkout is not enabled for this resort. Send your payment to the posted GCash or Maya number and upload the receipt.'
        );
    }

    public function isPaid(string $sessionId): bool
    {
        return false;
    }
}
