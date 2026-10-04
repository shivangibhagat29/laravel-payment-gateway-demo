<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt',
        'gateway_order_id',
        'gateway_payment_id',
        'amount',
        'currency',
        'status',
        'customer_name',
        'customer_email',
        'paid_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function markPaid(string $paymentId): void
    {
        // Idempotent: a payment can be confirmed by BOTH the browser callback
        // and the webhook. Whichever arrives second is a no-op.
        if ($this->status === PaymentStatus::Paid) {
            return;
        }

        $this->update([
            'gateway_payment_id' => $paymentId,
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function markFailed(?string $paymentId, ?string $reason): void
    {
        // Never downgrade a successful payment because of a late/duplicate event.
        if ($this->status === PaymentStatus::Paid) {
            return;
        }

        $this->update([
            'gateway_payment_id' => $paymentId,
            'status' => PaymentStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
