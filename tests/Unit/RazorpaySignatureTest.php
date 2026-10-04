<?php

namespace Tests\Unit;

use App\Services\Payments\RazorpayGateway;
use PHPUnit\Framework\TestCase;

class RazorpaySignatureTest extends TestCase
{
    private RazorpayGateway $gateway;

    protected function setUp(): void
    {
        $this->gateway = new RazorpayGateway('key', 'secret', 'wh_secret', 'https://api.razorpay.com/v1');
    }

    public function test_payment_signature_matches_order_pipe_payment(): void
    {
        $sig = hash_hmac('sha256', 'order_1|pay_1', 'secret');

        $this->assertTrue($this->gateway->verifyPaymentSignature('order_1', 'pay_1', $sig));
        $this->assertFalse($this->gateway->verifyPaymentSignature('order_1', 'pay_2', $sig));
    }

    public function test_webhook_signature_uses_raw_body_and_webhook_secret(): void
    {
        $body = '{"event":"payment.captured"}';

        $this->assertTrue($this->gateway->verifyWebhookSignature($body, hash_hmac('sha256', $body, 'wh_secret')));
        $this->assertFalse($this->gateway->verifyWebhookSignature($body, hash_hmac('sha256', $body, 'secret')));
    }
}
