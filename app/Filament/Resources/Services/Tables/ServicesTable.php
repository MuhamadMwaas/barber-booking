<?php

namespace App\Filament\Resources\Services\Tables;

use App\Enum\AppointmentStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Service Image with Color Indicator
                ImageColumn::make('image_url')
                    ->label(__('resources.service.image'))
                    ->circular()
                    ->size(60)
                    ->defaultImageUrl(fn ($record) =>
                        'https://ui-avatars.com/api/?name=' . urlencode($record->name) .
                        '&color=fff&background=' . str_replace('#', '', $record->color_code ?? '3B82F6')
                    ),

                // Service Name with Category
                TextColumn::make('name')
                    ->label(__('resources.service.name'))
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn ($record) => $record->category?->name)
                    ->icon('heroicon-o-scissors')
                    ->color(fn ($record) => $record->color_code ? 'primary' : null)
                    ->sortable(),

                // Color Code Visual Indicator
                ColorColumn::make('color_code')
                    ->label(__('resources.service.color'))
                    ->sortable()
                    ->toggleable(),

                // Service Providers (Max 4)
                // TextColumn::make('providers')
                //     ->label(__('resources.service.providers'))
                //     ->badge()
                //     ->getStateUsing(function ($record) {
                //         $providers = $record->activeProviders()
                //             ->limit(4)
                //             ->get();

                //         $providerNames = $providers->pluck('full_name')->toArray();

                //         $totalCount = $record->activeProviders()->count();
                //         if ($totalCount > 4) {
                //             $providerNames[] = '+' . ($totalCount - 4);
                //         }

                //         return $providerNames;
                //     })
                //     ->separator(',')
                //     ->color('info')
                //     ->icon('heroicon-o-user-group')
                //     ->wrap()
                //     ->toggleable(),

                // Price with Discount
                TextColumn::make('price')
                    ->label(__('resources.service.price'))
                    ->money('SAR')
                    ->weight(FontWeight::Bold)
                    ->description(function ($record) {
                        if ($record->discount_price) {
                            return __('resources.service.discount') . ': ' . number_format($record->discount_price, 2) . ' ' . __('resources.service.sar');
                        }
                        return null;
                    })
                    ->icon('heroicon-o-banknotes')
                    ->color(fn ($record) => $record->discount_price ? 'success' : 'gray')
                    ->sortable(),

                // Duration
                TextColumn::make('duration_minutes')
                    ->label(__('resources.service.duration'))
                    ->formatStateUsing(fn ($state) => floor($state / 60) . 'h ' . ($state % 60) . 'm')
                    ->icon('heroicon-o-clock')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                // Total Bookings
                TextColumn::make('total_bookings')
                    ->label(__('resources.service.total_bookings'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->where('service_id', $record->id)
                            ->count();
                    })
                    ->icon('heroicon-o-calendar-days')
                    ->sortable()
                    ->alignCenter(),

                // Completed Bookings
                TextColumn::make('completed_bookings')
                    ->label(__('resources.service.completed_bookings'))
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.status', AppointmentStatus::COMPLETED)
                            ->count();
                    })
                    ->icon('heroicon-o-check-circle')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                // Total Revenue
                TextColumn::make('total_revenue')
                    ->label(__('resources.service.total_revenue'))
                    ->badge()
                    ->color('success')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(function ($record) {
                        $total = DB::table('appointment_services')
                            ->where('service_id', $record->id)
                            ->sum('price');

                        return number_format($total, 2) . ' ' . __('resources.service.sar');
                    })
                    ->icon('heroicon-o-currency-dollar')
                    ->sortable()
                    ->toggleable(),

                // Completed Revenue Only
                TextColumn::make('completed_revenue')
                    ->label(__('resources.service.completed_revenue'))
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        $revenue = DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.status', AppointmentStatus::COMPLETED)
                            ->sum('appointment_services.price');

                        return number_format($revenue, 2) . ' ' . __('resources.service.sar');
                    })
                    ->description(__('resources.service.from_completed_only'))
                    ->icon('heroicon-o-banknotes')
                    ->sortable()
                    ->toggleable(),

                // Average Booking Price
                TextColumn::make('average_price')
                    ->label(__('resources.service.average_price'))
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(function ($record) {
                        $avg = DB::table('appointment_services')
                            ->where('service_id', $record->id)
                            ->avg('price');

                        return $avg ? number_format($avg, 2) . ' ' . __('resources.service.sar') : '0.00';
                    })
                    ->icon('heroicon-o-calculator')
                    ->toggleable(),

                // Status Indicators
                IconColumn::make('is_featured')
                    ->label(__('resources.service.featured'))
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label(__('resources.service.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                // Timestamps
                TextColumn::make('created_at')
                    ->label(__('resources.service.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('resources.service.updated_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultSort('sort_order', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
