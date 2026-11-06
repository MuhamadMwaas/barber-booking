<?php

namespace App\Filament\Resources\SalonSettings\Schemas;

use App\Filament\Resources\SalonSettings\SalonSettingCatalog;
use App\Models\SalonSetting;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class SalonSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Section::make(__('resources.salon_setting.setting_value'))
                    ->description(__('resources.salon_setting.setting_value_desc'))
                    ->icon('heroicon-o-pencil-square')
                    ->schema(fn (SalonSetting $record): array => [
                        Callout::make(__('resources.salon_setting.not_in_use_title'))
                            ->description(__('resources.salon_setting.not_in_use_desc'))
                            ->warning()
                            ->visible(! SalonSettingCatalog::isInUse($record->key)),

                        self::valueInput($record),
                    ])
                    ->columnSpan(['default' => 1, 'lg' => 2]),

                // Read-only on purpose: entries are not fields, so `value` is the
                // only thing this page can write.
                Section::make(__('resources.salon_setting.setting_information'))
                    ->icon('heroicon-o-information-circle')
                    ->schema(self::detailEntries())
                    ->columnSpan(1),
            ]);
    }

    /** @return array<TextEntry> */
    public static function detailEntries(): array
    {
        return [
            TextEntry::make('setting_group')
                ->label(__('resources.salon_setting.setting_group'))
                ->badge()
                ->formatStateUsing(fn (?string $state): string => SalonSettingCatalog::groupLabel($state))
                ->color(fn (?string $state): string => SalonSettingCatalog::groupColor($state))
                ->icon(fn (?string $state): string => SalonSettingCatalog::groupIcon($state)),

            TextEntry::make('key')
                ->label(__('resources.salon_setting.key'))
                ->fontFamily(FontFamily::Mono)
                ->copyable()
                ->copyMessage(__('resources.salon_setting.value_copied')),

            TextEntry::make('type')
                ->label(__('resources.salon_setting.type'))
                ->formatStateUsing(fn (?string $state): string => SalonSettingCatalog::typeLabel($state)),

            TextEntry::make('branch.name')
                ->label(__('resources.salon_setting.branch'))
                ->placeholder(__('resources.salon_setting.global_setting')),
        ];
    }

    public static function valueInput(SalonSetting $setting): Field
    {
        $field = match ($setting->type) {
            SalonSetting::TYPE_INTEGER => self::integerInput($setting->key),
            SalonSetting::TYPE_DECIMAL => self::decimalInput($setting->key),
            SalonSetting::TYPE_BOOLEAN => self::booleanInput(),
            SalonSetting::TYPE_JSON => self::jsonInput($setting),
            default => self::textInput($setting->key),
        };

        return $field
            ->label(SalonSettingCatalog::label($setting->key))
            ->helperText(SalonSettingCatalog::description($setting));
    }

    private static function integerInput(string $key): TextInput
    {
        return TextInput::make('value')
            ->integer()
            ->rule('integer')
            ->required()
            ->minValue(SalonSettingCatalog::minValue($key))
            ->maxValue(SalonSettingCatalog::maxValue($key))
            ->suffix(SalonSettingCatalog::unit($key))
            ->dehydrateStateUsing(fn ($state): int => (int) $state);
    }

    private static function decimalInput(string $key): TextInput
    {
        return TextInput::make('value')
            ->numeric()
            ->required()
            ->step(0.01)
            ->minValue(SalonSettingCatalog::minValue($key))
            ->maxValue(SalonSettingCatalog::maxValue($key))
            ->suffix(SalonSettingCatalog::unit($key))
            ->dehydrateStateUsing(fn ($state): string => (string) $state);
    }

    private static function booleanInput(): Toggle
    {
        return Toggle::make('value')
            ->onColor('success')
            ->onIcon('heroicon-m-check')
            ->offIcon('heroicon-m-x-mark')
            ->inline(false)
            ->formatStateUsing(fn ($state): bool => SalonSettingCatalog::isEnabled($state))
            ->dehydrateStateUsing(fn ($state): string => $state ? 'true' : 'false');
    }

    private static function jsonInput(SalonSetting $setting): Field
    {
        $value = SalonSettingCatalog::jsonValue($setting->value);

        if (! array_is_list($value)) {
            return KeyValue::make('value');
        }

        return TagsInput::make('value')
            ->formatStateUsing(fn (): array => $value)
            ->placeholder(__('resources.salon_setting.value_placeholder'));
    }

    private static function textInput(string $key): Field
    {
        $format = SalonSettingCatalog::format($key);

        $field = SalonSettingCatalog::isMultiline($key)
            ? Textarea::make('value')->rows(3)->maxLength(500)
            : TextInput::make('value')
                ->maxLength(255)
                // The browser would otherwise fill an empty email field with the
                // admin's own address, and Save would publish it on invoices.
                ->autocomplete(false)
                ->email($format === 'email')
                ->tel($format === 'tel');

        // `value` is NOT NULL, and a cleared input arrives here as null.
        return $field
            ->required(SalonSettingCatalog::isRequired($key))
            ->dehydrateStateUsing(fn (?string $state): string => $state ?? '');
    }
}
