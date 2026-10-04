<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    /**
     * Step 1: create an order at Razorpay and save it locally as "created".
     * The browser then opens Razorpay Checkout with the returned order id.
     */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $amountInPaise = (int) round($request->float('amount') * 100);
        $currency = config('razorpay.currency');
        $receipt = 'rcpt_'.Str::ulid();

        try {
            $order = $this->gateway->createOrder($amountInPaise, $currency, $receipt, [
                'email' => $request->string('email')->toString(),
            ]);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $payment = Payment::create([
            'receipt' => $receipt,
            'gateway_order_id' => $order['id'],
            'amount' => $amountInPaise,
            'currency' => $currency,
            'status' => PaymentStatus::Created,
            'customer_name' => $request->string('name')->toString(),
            'customer_email' => $request->string('email')->toString(),
        ]);

        return response()->json([
            'key' => config('razorpay.key'), // public key only
            'order_id' => $payment->gateway_order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'name' => $payment->customer_name,
            'email' => $payment->customer_email,
        ], 201);
    }

    /**
     * Step 2: after checkout succeeds, the browser sends the ids + signature.
     * We verify the HMAC signature on the server before marking it paid.
     */
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $payment = Payment::where('gateway_order_id', $data['razorpay_order_id'])->first();

        if (! $payment) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $valid = $this->gateway->verifyPaymentSignature(
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        if (! $valid) {
            Log::warning('Invalid Razorpay payment signature', ['order' => $payment->gateway_order_id]);

            return response()->json(['message' => 'Payment verification failed.'], 422);
        }

        $payment->markPaid($data['razorpay_payment_id']);

        return response()->json([
            'message' => 'Payment successful.',
            'status' => $payment->status,
            'receipt' => $payment->receipt,
        ]);
    }

    public function show(string $receipt): JsonResponse
    {
        $payment = Payment::where('receipt', $receipt)->firstOrFail();

        return response()->json([
            'receipt' => $payment->receipt,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at,
        ]);
    }
}
