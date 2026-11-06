<?php

namespace App\Filament\Resources\SalonSettings;

use App\Models\SalonSetting;
use Illuminate\Support\Str;

/**
 * What the admin screens know about each salon setting: its group, its label,
 * and the limits a value must respect.
 *
 * The groups live here and nowhere else. They used to be repeated in the form,
 * the table, the filter and the infolist, and `company` was missing from all of
 * them: the group showed blank and every company setting failed validation on
 * save. A group that is not listed here still renders, under its raw name.
 *
 * `in_use => false` marks a setting no code reads. The screens say so, so that
 * nobody changes one and expects something to happen. cancellation_hours in
 * particular is unread by decision, not by oversight.
 */
final class SalonSettingCatalog
{
    private const GROUPS = [
        'company' => ['icon' => 'heroicon-o-building-office-2', 'color' => 'primary'],
        'booking' => ['icon' => 'heroicon-o-calendar-days', 'color' => 'info'],
        'payment' => ['icon' => 'heroicon-o-banknotes', 'color' => 'success'],
        'notifications' => ['icon' => 'heroicon-o-bell', 'color' => 'warning'],
        'contact' => ['icon' => 'heroicon-o-envelope', 'color' => 'info'],
        'loyalty' => ['icon' => 'heroicon-o-gift', 'color' => 'danger'],
        'general' => ['icon' => 'heroicon-o-cog-6-tooth', 'color' => 'gray'],
    ];

    private const SETTINGS = [
        'company_name' => ['required' => true],
        'company_address' => ['multiline' => true],
        'company_phone' => ['format' => 'tel'],
        'company_email' => ['format' => 'email'],
        'company_tax_number' => [],

        'max_booking_days' => ['unit' => 'days'],
        'book_buffer' => ['unit' => 'minutes'],
        'max_daily_bookings' => [],
        'max_services_per_booking' => ['min' => 1],
        'cancellation_hours' => ['unit' => 'hours', 'in_use' => false],
        'auto_confirm_appointments' => ['in_use' => false],

        'tax_rate' => ['unit' => 'percent', 'max' => 100],
        'currency' => ['required' => true],
        'enable_online_payment' => ['in_use' => false],
        'payment_methods' => ['in_use' => false],

        'reminder_hours_before' => ['unit' => 'hours', 'in_use' => false],

        'branch_phone' => ['format' => 'tel', 'in_use' => false],
        'branch_email' => ['format' => 'email', 'in_use' => false],

        'loyalty_points_enabled' => ['in_use' => false],
        'points_per_aed' => ['in_use' => false],
    ];

    /** @return list<string> group keys in display order */
    public static function groups(): array
    {
        return array_keys(self::GROUPS);
    }

    public static function groupLabel(?string $group): string
    {
        if (blank($group)) {
            return '—';
        }

        return isset(self::GROUPS[$group])
            ? __("resources.salon_setting.group_{$group}")
            : Str::headline($group);
    }

    public static function groupIcon(?string $group): string
    {
        return self::GROUPS[$group]['icon'] ?? 'heroicon-o-folder';
    }

    public static function groupColor(?string $group): string
    {
        return self::GROUPS[$group]['color'] ?? 'gray';
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            SalonSetting::TYPE_STRING => __('resources.salon_setting.type_string'),
            SalonSetting::TYPE_INTEGER => __('resources.salon_setting.type_integer'),
            SalonSetting::TYPE_DECIMAL => __('resources.salon_setting.type_decimal'),
            SalonSetting::TYPE_BOOLEAN => __('resources.salon_setting.type_boolean'),
            SalonSetting::TYPE_JSON => __('resources.salon_setting.type_json'),
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::typeOptions()[$type] ?? (string) $type;
    }

    public static function label(string $key): string
    {
        return self::translated($key, 'label') ?? Str::headline($key);
    }

    /** Falls back to the stored description for a key the catalog does not know. */
    public static function description(SalonSetting $setting): ?string
    {
        return self::translated($setting->key, 'helper') ?? $setting->description;
    }

    public static function isInUse(string $key): bool
    {
        return self::SETTINGS[$key]['in_use'] ?? true;
    }

    public static function isRequired(string $key): bool
    {
        return self::SETTINGS[$key]['required'] ?? false;
    }

    public static function isMultiline(string $key): bool
    {
        return self::SETTINGS[$key]['multiline'] ?? false;
    }

    public static function format(string $key): ?string
    {
        return self::SETTINGS[$key]['format'] ?? null;
    }

    public static function minValue(string $key): int
    {
        return self::SETTINGS[$key]['min'] ?? 0;
    }

    public static function maxValue(string $key): ?int
    {
        return self::SETTINGS[$key]['max'] ?? null;
    }

    public static function unit(string $key): ?string
    {
        $unit = self::SETTINGS[$key]['unit'] ?? null;

        return $unit === null ? null : __("resources.salon_setting.unit_{$unit}");
    }

    /** @return list<string> keys whose translated label contains the search term */
    public static function keysMatching(string $search): array
    {
        return array_values(array_filter(
            array_keys(self::SETTINGS),
            fn (string $key): bool => Str::contains(self::label($key), $search, ignoreCase: true),
        ));
    }

    public static function isEnabled(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The seeder wrote payment_methods as a JSON string inside the JSON cast, so
     * a row seeded before the repair migration decodes to a string, not an array.
     */
    public static function jsonValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    public static function isEmpty(SalonSetting $setting): bool
    {
        return match ($setting->type) {
            SalonSetting::TYPE_BOOLEAN => false,
            SalonSetting::TYPE_JSON => self::jsonValue($setting->value) === [],
            default => blank($setting->value),
        };
    }

    /** The value as an admin should read it, with its unit. */
    public static function display(SalonSetting $setting): string
    {
        if (self::isEmpty($setting)) {
            return __('resources.salon_setting.not_set');
        }

        return match ($setting->type) {
            SalonSetting::TYPE_BOOLEAN => self::isEnabled($setting->value)
                ? __('resources.salon_setting.enabled')
                : __('resources.salon_setting.disabled'),
            SalonSetting::TYPE_JSON => self::jsonDisplay(self::jsonValue($setting->value)),
            default => trim($setting->value.' '.self::unit($setting->key)),
        };
    }

    public static function displayColor(SalonSetting $setting): string
    {
        if (self::isEmpty($setting)) {
            return 'warning';
        }

        return $setting->type === SalonSetting::TYPE_BOOLEAN && self::isEnabled($setting->value)
            ? 'success'
            : 'gray';
    }

    public static function displayIcon(SalonSetting $setting): ?string
    {
        if (self::isEmpty($setting)) {
            return 'heroicon-o-exclamation-triangle';
        }

        if ($setting->type !== SalonSetting::TYPE_BOOLEAN) {
            return null;
        }

        return self::isEnabled($setting->value) ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle';
    }

    private static function jsonDisplay(array $value): string
    {
        if (array_is_list($value)) {
            return implode(', ', array_map(strval(...), $value));
        }

        return collect($value)
            ->map(fn (mixed $item, string $key): string => "{$key}: {$item}")
            ->implode(', ');
    }

    private static function translated(string $key, string $field): ?string
    {
        $path = "resources.salon_setting.settings.{$key}.{$field}";
        $text = __($path);

        return $text === $path ? null : $text;
    }
}
