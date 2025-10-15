<?php

namespace App\Enum;

enum PaymentStatus: int
{
    case PENDING = 0;
    case PAID_ONLINE = 1;
    case PAID_ONSTIE_CASH = 2;
    case PAID_ONSTIE_CARD = 3;
    case FAILED = 4;
    case REFUNDED = 5;
    case PARTIALLY_REFUNDED = 6;


    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::PAID_ONLINE => 'Paid Online',
            self::PAID_ONSTIE_CASH => 'Paid On site Cash',
            self::PAID_ONSTIE_CARD => 'Paid On site card',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
        };
    }
}
