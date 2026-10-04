<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt')->unique();
            $table->string('gateway_order_id')->unique();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->unsignedBigInteger('amount'); // paise — never store money as float
            $table->string('currency', 3)->default('INR');
            $table->string('status', 20)->default('created')->index();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
