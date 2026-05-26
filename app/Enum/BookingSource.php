<?php

namespace App\Enum;

enum BookingSource: string
{
    case ONLINE    = 'online';
    case IN_PERSON = 'in_person';

    /**
     * Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::ONLINE    => 'Online',
            self::IN_PERSON => 'In-Person',
        };
    }

    /**
     * Filament badge color
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::ONLINE    => 'primary',  // أزرق
            self::IN_PERSON => 'gray',     // رمادي
        };
    }

    /**
     * Heroicon name (للاستخدام في Filament)
     */
    public function heroicon(): string
    {
        return match ($this) {
            self::ONLINE    => 'heroicon-o-globe-alt',
            self::IN_PERSON => 'heroicon-o-building-storefront',
        };
    }

    /**
     * HTML emoji icon (للاستخدام في Dashboard timeline)
     */
    public function htmlIcon(): string
    {
        return match ($this) {
            self::ONLINE    => '🌐',
            self::IN_PERSON => '🏪',
        };
    }
}
