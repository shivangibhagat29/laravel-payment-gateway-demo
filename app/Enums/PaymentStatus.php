<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';   // order created, user has not paid yet
    case Paid = 'paid';         // signature verified / payment captured
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this !== self::Created;
    }
}
