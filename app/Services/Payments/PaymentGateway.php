<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Whether live checkout can be offered. False means the resort is running
     * on manual proof-of-payment only.
     */
    public function isConfigured(): bool;

    public function label(): string;

    /**
     * @throws \App\Exceptions\Payment\PaymentGatewayException
     */
    public function createCheckout(Payment $payment, string $successUrl, string $cancelUrl): CheckoutSession;

    /**
     * Whether the provider has actually received the money for this session.
     *
     * @throws \App\Exceptions\Payment\PaymentGatewayException
     */
    public function isPaid(string $sessionId): bool;
}
