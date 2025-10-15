<?php

namespace App\Enum;

enum AppointmentStatus: int
{
    case PENDING = 0;
    case COMPLETED =1;
    case USER_CANCELLED = -1;
    case ADMIN_CANCELLED = -2;
    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::USER_CANCELLED => 'User Cancelled',
            self::ADMIN_CANCELLED => 'Admin Cancelled',
        };
    }

    public static function getCancelledStatuses(): array
    {
        return [
            self::USER_CANCELLED->value,
            self::ADMIN_CANCELLED->value
        ];
    }
    public static function CommentStatus(){

        $comment="";

        foreach(self::cases() as $status){
            $comment.= $status->value . '=>' . $status->getLabel() . ', ';
        }
        return $comment;
    }
}
