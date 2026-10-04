<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay integration using Laravel's HTTP client (no SDK needed).
 * Docs: https://razorpay.com/docs/api/orders/
 */
class RazorpayGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $webhookSecret,
        private readonly string $baseUrl,
    ) {
    }

    public function createOrder(int $amount, string $currency, string $receipt, array $notes = []): array
    {
        try {
            $response = Http::withBasicAuth($this->key, $this->secret)
                ->acceptJson()
                ->timeout(10)
                // Retry only on network errors — a 4xx (bad key, bad amount) won't fix itself.
                ->retry(2, 200, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->post("{$this->baseUrl}/orders", [
                    'amount' => $amount,
                    'currency' => $currency,
                    'receipt' => $receipt,
                    'notes' => (object) $notes,
                ]);
        } catch (ConnectionException $e) {
            throw new PaymentGatewayException('Payment gateway is unreachable.', previous: $e);
        }

        if ($response->failed()) {
            // Log the gateway error for debugging, but never log the secret.
            Log::warning('Razorpay order creation failed', [
                'status' => $response->status(),
                'error' => $response->json('error.description'),
                'receipt' => $receipt,
            ]);

            throw new PaymentGatewayException('Could not create payment order.');
        }

        return $response->json();
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        // Razorpay signs "order_id|payment_id" with the key secret (HMAC-SHA256).
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $this->secret);

        // hash_equals = constant-time compare, prevents timing attacks.
        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        // Must use the RAW body — re-encoding the JSON would change the bytes.
        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
