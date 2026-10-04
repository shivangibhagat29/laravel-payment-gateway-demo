<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhooks are the source of truth: if the user closes the browser
 * right after paying, the verify call never happens — but Razorpay
 * still calls this endpoint server-to-server.
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');

        if ($signature === '' || ! $gateway->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Rejected Razorpay webhook: bad signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $event = $payload['event'] ?? 'unknown';
        // Razorpay sends a unique id per event; fall back to a body hash.
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $rawBody));

        try {
            DB::transaction(function () use ($event, $eventId, $payload) {
                // Unique index makes duplicates fail here => processed only once.
                WebhookEvent::create([
                    'gateway' => 'razorpay',
                    'event_id' => $eventId,
                    'event_type' => $event,
                    'payload' => $payload,
                ]);

                $this->handle($event, $payload);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Already processed.']);
        }

        // Always answer 200 quickly; Razorpay retries on non-2xx responses.
        return response()->json(['message' => 'OK']);
    }

    private function handle(string $event, array $payload): void
    {
        $entity = $payload['payload']['payment']['entity'] ?? null;

        if (! $entity || empty($entity['order_id'])) {
            return; // event we don't track (e.g. refunds) — ignore safely
        }

        $payment = Payment::where('gateway_order_id', $entity['order_id'])->lockForUpdate()->first();

        if (! $payment) {
            Log::info('Webhook for unknown order', ['order' => $entity['order_id']]);

            return;
        }

        match ($event) {
            'payment.captured', 'order.paid' => $payment->markPaid($entity['id']),
            'payment.failed' => $payment->markFailed($entity['id'] ?? null, $entity['error_description'] ?? null),
            default => null,
        };
    }
}
