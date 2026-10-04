<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api and have no CSRF check.

Route::middleware('throttle:20,1')->group(function () {
    Route::post('/payments/order', [PaymentController::class, 'createOrder']);
    Route::post('/payments/verify', [PaymentController::class, 'verify']);
    Route::get('/payments/{receipt}', [PaymentController::class, 'show']);
});

Route::post('/webhooks/razorpay', RazorpayWebhookController::class);
