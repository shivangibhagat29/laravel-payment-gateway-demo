<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'receipt' => 'rcpt_'.Str::ulid(),
            'gateway_order_id' => 'order_'.Str::random(14),
            'amount' => 49900,
            'currency' => 'INR',
            'status' => PaymentStatus::Created,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
        ];
    }
}
