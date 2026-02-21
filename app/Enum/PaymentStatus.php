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

    /**
     * Check if payment status is successful (paid)
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::PAID_ONLINE,
            self::PAID_ONSTIE_CASH,
            self::PAID_ONSTIE_CARD,
        ]);
    }

    /**
     * Check if payment status is pending
     */
    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if payment status is failed
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Check if payment status is refunded (full or partial)
     */
    public function isRefunded(): bool
    {
        return in_array($this, [
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
        ]);
    }

    /**
     * Get all successful payment statuses
     */
    public static function getSuccessfulStatuses(): array
    {
        return [
            self::PAID_ONLINE,
            self::PAID_ONSTIE_CASH,
            self::PAID_ONSTIE_CARD,
        ];
    }
}
