<?php

namespace App\Contracts;

/**
 * Every gateway (Razorpay, PayPal, Cashfree...) implements this contract,
 * so controllers never depend on one provider. Swapping providers means
 * writing a new class and changing one binding in AppServiceProvider.
 */
interface PaymentGateway
{
    /**
     * Create an order at the gateway.
     *
     * @param  int  $amount  Amount in the smallest unit (paise for INR).
     * @return array{id: string, amount: int, currency: string, status: string}
     */
    public function createOrder(int $amount, string $currency, string $receipt, array $notes = []): array;

    /** Verify the signature the checkout returns after a successful payment. */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool;

    /** Verify that a webhook request really came from the gateway. */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool;
}
