# Laravel Payment Gateway Demo (Razorpay)

![tests](https://github.com/shivangibhagat29/laravel-payment-gateway-demo/actions/workflows/tests.yml/badge.svg)

A small Laravel project showing how I build **production-style payment integrations**: order creation, server-side signature verification, and idempotent webhooks.

It is a clean demo written for this portfolio. Client code I've worked on is private.

## What it shows

| Area | How it's done |
|---|---|
| Gateway abstraction | `PaymentGateway` interface + `RazorpayGateway`, bound in `AppServiceProvider`, so a different gateway (PayPal, Cashfree) can be swapped in by changing one line |
| Money handling | Amounts stored as integer **paise**, never floats |
| Payment verification | HMAC-SHA256 of `order_id|payment_id` checked on the server with `hash_equals` (constant-time) |
| Webhooks | Raw-body signature check, `webhook_events` table with a unique index so retries are processed **once** |
| Safe state changes | A late `payment.failed` event can't downgrade a payment that's already `paid` |
| Resilience | HTTP client timeout, retry on network errors only, gateway errors logged without secrets, `502` to the client |
| Validation & limits | Form Requests, `throttle:20,1` on payment routes |
| Tests | Feature tests with `Http::fake()` (no real API calls) + unit tests for signatures; CI on GitHub Actions |

## Flow

```
Browser                    Laravel                         Razorpay
  | POST /api/payments/order |                                |
  |------------------------->| create order (Basic Auth) ---->|
  |<-- order_id, public key -|<------------- order_xxx -------|
  | open Razorpay Checkout ---------------------------------->|
  |<------------------- payment_id + signature ---------------|
  | POST /api/payments/verify|                                |
  |------------------------->| verify HMAC -> mark paid        |
  |                          |<--- webhook payment.captured ---|  (source of truth,
  |                          | verify, dedupe, mark paid       |   works even if the
                                                                    browser closed)
```

## API

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/payments/order` | Create order `{amount, name, email}` |
| POST | `/api/payments/verify` | Verify `{razorpay_order_id, razorpay_payment_id, razorpay_signature}` |
| GET | `/api/payments/{receipt}` | Payment status |
| POST | `/api/webhooks/razorpay` | Razorpay webhook (signature required) |

## Run locally

```bash
git clone https://github.com/shivangibhagat29/laravel-payment-gateway-demo.git
cd laravel-payment-gateway-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
php artisan serve
```

Add your **test-mode** keys from the Razorpay dashboard to `.env`:

```
RAZORPAY_KEY=rzp_test_xxxxx
RAZORPAY_SECRET=xxxxx
RAZORPAY_WEBHOOK_SECRET=xxxxx
```

Open http://127.0.0.1:8000 and pay with Razorpay's test cards.

## Key files

- `app/Contracts/PaymentGateway.php`: gateway contract
- `app/Services/Payments/RazorpayGateway.php`: API calls + signature checks
- `app/Http/Controllers/PaymentController.php`: order + verify
- `app/Http/Controllers/RazorpayWebhookController.php`: idempotent webhook
- `tests/Feature/PaymentFlowTest.php`: end-to-end tests with faked HTTP

## Author

**Shivangi**, Senior PHP/Laravel Developer · [LinkedIn](https://www.linkedin.com/in/shivangi-bhagat)
