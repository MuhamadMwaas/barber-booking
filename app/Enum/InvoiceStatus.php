<?php

namespace App\Enum;

enum InvoiceStatus: int
{
    case DRAFT = 0;
    case PENDING = 1;
    case PAID = 2;
    case PARTIALLY_PAID = 3;
    case CANCELLED = -1;
    case REFUNDED = -2;
    case OVERDUE = 4;

    /**
     * Get all status values
     */
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get status label
     */
    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            self::OVERDUE => 'Overdue',
        };
    }

    public function getTranslate(): string
    {
        return _('InvoiceStatus.' . $this->getLabel());
    }


    public function getColor(): string
    {
        return match($this) {
            self::DRAFT => '#6C757D',
            self::PENDING => '#FFC107',
            self::PAID => '#28A745',
            self::PARTIALLY_PAID => '#17A2B8',
            self::CANCELLED => '#DC3545',
            self::REFUNDED => '#FD7E14',
            self::OVERDUE => '#E74C3C',
        };
    }

    /**
     * Get status badge class for UI
     */
    public function getBadgeClass(): string
    {
        return match($this) {
            self::DRAFT => 'badge-secondary',
            self::PENDING => 'badge-warning',
            self::PAID => 'badge-success',
            self::PARTIALLY_PAID => 'badge-info',
            self::CANCELLED => 'badge-danger',
            self::REFUNDED => 'badge-orange',
            self::OVERDUE => 'badge-dark-red',
        };
    }

    /**
     * Check if invoice is payable
     */
    public function isPayable(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::PARTIALLY_PAID,
            self::OVERDUE,
        ]);
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Check if invoice is cancelled or refunded
     */
    public function isCancelled(): bool
    {
        return in_array($this, [
            self::CANCELLED,
            self::REFUNDED,
        ]);
    }

    /**
     * Check if invoice is editable
     */
    public function isEditable(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING,
        ]);
    }

    /**
     * Get all payable statuses
     */
    public static function getPayableStatuses(): array
    {
        return [
            self::PENDING->value,
            self::PARTIALLY_PAID->value,
            self::OVERDUE->value,
        ];
    }

    /**
     * Get all cancelled statuses
     */
    public static function getCancelledStatuses(): array
    {
        return [
            self::CANCELLED->value,
            self::REFUNDED->value,
        ];
    }

    /**
     * Get all completed statuses (paid or cancelled)
     */
    public static function getCompletedStatuses(): array
    {
        return [
            self::PAID->value,
            self::CANCELLED->value,
            self::REFUNDED->value,
        ];
    }

    /**
     * Generate comment for migration
     */
    public static function CommentStatus(): string
    {
        $comment = "";

        foreach(self::cases() as $status) {
            $comment .= $status->value . '=>' . $status->getLabel() . ', ';
        }

        return rtrim($comment, ', ');
    }

    /**
     * Get status from string
     */
    public static function fromString(string $status): ?self
    {
        return match(strtolower($status)) {
            'draft' => self::DRAFT,
            'pending' => self::PENDING,
            'paid' => self::PAID,
            'partially_paid' => self::PARTIALLY_PAID,
            'cancelled' => self::CANCELLED,
            'refunded' => self::REFUNDED,
            'overdue' => self::OVERDUE,
            default => null,
        };
    }

    /**
     * Get all statuses as array for select options
     */
    public static function toSelectArray(): array
    {
        $array = [];

        foreach(self::cases() as $status) {
            $array[$status->value] = $status->getLabel();
        }

        return $array;
    }
}
