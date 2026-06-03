<?php

namespace App\Filament\Resources\ProviderAttendances\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Admin form for correcting attendance records (providers punch from the
 * dashboard; admins only adjust mistakes here).
 */
class ProviderAttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources.provider_attendance.section_info'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('user_id')
                            ->label(__('resources.provider_attendance.provider'))
                            ->options(fn () => User::query()
                                ->whereHas('roles', fn ($q) => $q->where('name', 'provider'))
                                ->orderBy('first_name')
                                ->get()
                                ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name]))
                            ->searchable()
                            ->preload()
                            ->required(),

                        DatePicker::make('work_date')
                            ->label(__('resources.provider_attendance.work_date'))
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->required(),
                    ]),

                    Grid::make(2)->schema([
                        DateTimePicker::make('check_in_at')
                            ->label(__('resources.provider_attendance.check_in_at'))
                            ->seconds(false)
                            ->required(),

                        DateTimePicker::make('check_out_at')
                            ->label(__('resources.provider_attendance.check_out_at'))
                            ->seconds(false)
                            ->after('check_in_at')
                            ->nullable()
                            ->helperText(__('resources.provider_attendance.check_out_hint')),
                    ]),

                    Textarea::make('notes')
                        ->label(__('resources.provider_attendance.notes'))
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
