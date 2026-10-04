<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the contract to Razorpay. To switch gateway, change this one line.
        $this->app->singleton(PaymentGateway::class, fn () => new RazorpayGateway(
            key: (string) config('razorpay.key'),
            secret: (string) config('razorpay.secret'),
            webhookSecret: (string) config('razorpay.webhook_secret'),
            baseUrl: (string) config('razorpay.base_url'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
