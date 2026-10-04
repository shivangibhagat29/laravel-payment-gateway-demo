<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'razorpay.key' => 'rzp_test_key',
            'razorpay.secret' => 'test_secret',
            'razorpay.webhook_secret' => 'webhook_secret',
        ]);
    }

    public function test_it_creates_an_order_and_stores_amount_in_paise(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_TEST123', 'amount' => 49900, 'currency' => 'INR', 'status' => 'created',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/order', [
            'amount' => 499, 'name' => 'Asha', 'email' => 'asha@example.com',
        ]);

        $response->assertCreated()
            ->assertJson(['order_id' => 'order_TEST123', 'amount' => 49900, 'key' => 'rzp_test_key'])
            ->assertJsonMissing(['secret' => 'test_secret']);

        $this->assertDatabaseHas('payments', [
            'gateway_order_id' => 'order_TEST123', 'amount' => 49900, 'status' => 'created',
        ]);

        Http::assertSent(fn ($req) => $req['amount'] === 49900 && $req->hasHeader('Authorization'));
    }

    public function test_order_creation_validates_input(): void
    {
        $this->postJson('/api/payments/order', ['amount' => 0, 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'name', 'email']);
    }

    public function test_gateway_error_returns_502(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Bad key']], 401)]);

        $this->postJson('/api/payments/order', ['amount' => 100, 'name' => 'A', 'email' => 'a@example.com'])
            ->assertStatus(502);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_valid_signature_marks_payment_paid(): void
    {
        $payment = Payment::factory()->create(['gateway_order_id' => 'order_ABC']);
        $signature = hash_hmac('sha256', 'order_ABC|pay_XYZ', 'test_secret');

        $this->postJson('/api/payments/verify', [
            'razorpay_order_id' => 'order_ABC',
            'razorpay_payment_id' => 'pay_XYZ',
            'razorpay_signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'paid']);

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('pay_XYZ', $payment->gateway_payment_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $payment = Payment::factory()->create(['gateway_order_id' => 'order_ABC']);

        $this->postJson('/api/payments/verify', [
            'razorpay_order_id' => 'order_ABC',
            'razorpay_payment_id' => 'pay_XYZ',
            'razorpay_signature' => str_repeat('a', 64),
        ])->assertUnprocessable();

        $this->assertSame(PaymentStatus::Created, $payment->fresh()->status);
    }

    public function test_webhook_with_valid_signature_marks_payment_paid_only_once(): void
    {
        $payment = Payment::factory()->create(['gateway_order_id' => 'order_WH1']);
        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_WH1', 'order_id' => 'order_WH1', 'status' => 'captured']]],
        ]);

        $send = fn () => $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'webhook_secret'),
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_1',
        ], $body);

        $send()->assertOk()->assertJson(['message' => 'OK']);
        $send()->assertOk()->assertJson(['message' => 'Already processed.']); // Razorpay retry

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => 'fake',
        ], '{"event":"payment.captured"}')->assertStatus(400);

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_late_failed_event_does_not_downgrade_paid_payment(): void
    {
        $payment = Payment::factory()->create(['gateway_order_id' => 'order_P', 'status' => PaymentStatus::Paid]);
        $body = json_encode([
            'event' => 'payment.failed',
            'payload' => ['payment' => ['entity' => ['id' => 'pay_2', 'order_id' => 'order_P']]],
        ]);

        $this->call('POST', '/api/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'webhook_secret'),
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_2',
        ], $body)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }
}
