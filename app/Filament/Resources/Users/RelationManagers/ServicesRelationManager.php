<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enum\AppointmentStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'services';

    protected static ?string $relatedResource = UserResource::class;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        // if ($ownerRecord->hasRole('customer')) {
        //     return __('resources.user.customer_services_statistics');
        // }
        return __('resources.user.provider_services');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {

        return $ownerRecord->hasRole('provider');
    }

    public function table(Table $table): Table
    {
        // تحديد نوع المستخدم (عميل أو مزود خدمة)
        $isCustomer = $this->ownerRecord->hasRole('customer');

        if ($isCustomer) {
            return $this->buildCustomerServicesTable($table);
        }

        return $this->buildProviderServicesTable($table);
    }

    /**
     * جدول الخدمات للعملاء مع الإحصائيات
     */
    protected function buildCustomerServicesTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.user.service_name'))
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn ($record) => $record->category?->name)
                    ->icon('heroicon-o-scissors')
                    ->color('primary'),

                TextColumn::make('total_bookings')
                    ->label(__('resources.user.total_bookings'))
                    ->badge()
                    ->color('info')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->count();
                    })
                    ->icon('heroicon-o-calendar-days')
                    ->sortable(),

                TextColumn::make('completed_bookings')
                    ->label(__('resources.user.completed_bookings'))
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->where('appointments.status', AppointmentStatus::COMPLETED)
                            ->count();
                    })
                    ->icon('heroicon-o-check-circle')
                    ->sortable(),

                TextColumn::make('pending_bookings')
                    ->label(__('resources.user.pending_bookings'))
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->where('appointments.status', AppointmentStatus::PENDING)
                            ->count();
                    })
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_spent')
                    ->label(__('resources.user.total_spent_on_service'))
                    ->badge()
                    ->color('success')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(function ($record) {
                        $total = DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->sum('appointment_services.price');

                        return number_format($total, 2) . ' ' . __('resources.user.sar_currency');
                    })
                    ->icon('heroicon-o-banknotes')
                    ->sortable(),

                TextColumn::make('revenue_from_customer')
                    ->label(__('resources.user.revenue_from_customer'))
                    ->badge()
                    ->color('success')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(function ($record) {
                        // حساب الدخل من الحجوزات المكتملة فقط
                        $revenue = DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->where('appointments.status', AppointmentStatus::COMPLETED)
                            ->sum('appointment_services.price');

                        return number_format($revenue, 2) . ' ' . __('resources.user.sar_currency');
                    })
                    ->icon('heroicon-o-currency-dollar')
                    ->description(__('resources.user.from_completed_only'))
                    ->sortable(),

                TextColumn::make('average_price')
                    ->label(__('resources.user.average_service_price'))
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(function ($record) {
                        $avg = DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->avg('appointment_services.price');

                        return $avg ? number_format($avg, 2) . ' ' . __('resources.user.sar_currency') : '0.00';
                    })
                    ->icon('heroicon-o-calculator')
                    ->toggleable(),

                TextColumn::make('last_booking')
                    ->label(__('resources.user.last_booking_date'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function ($record) {
                        $lastBooking = DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.customer_id', $this->ownerRecord->id)
                            ->orderBy('appointments.created_at', 'desc')
                            ->first();

                        return $lastBooking ? \Carbon\Carbon::parse($lastBooking->created_at)->diffForHumans() : __('resources.user.never');
                    })
                    ->icon('heroicon-o-calendar')
                    ->toggleable(),
            ])
            ->filters([])
            ->defaultSort('services.created_at', 'desc')
            ->emptyStateHeading(__('resources.user.no_services_booked'))
            ->emptyStateDescription(__('resources.user.no_services_booked_desc'))
            ->emptyStateIcon('heroicon-o-scissors');
    }

    /**
     * جدول الخدمات لمزودي الخدمة
     */
    protected function buildProviderServicesTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.user.service_name'))
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn ($record) => $record->category?->name)
                    ->icon('heroicon-o-scissors')
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('price')
                    ->label(__('resources.user.price'))
                    ->money('SAR')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('duration_minutes')
                    ->label(__('resources.user.duration'))
                    ->formatStateUsing(fn ($state) => $state . ' ' . __('resources.user.minutes'))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label(__('resources.user.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? __('resources.user.active') : __('resources.user.inactive'))
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('bookings_count')
                    ->label(__('resources.user.total_bookings'))
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.provider_id', $this->ownerRecord->id)
                            ->count();
                    })
                    ->icon('heroicon-o-calendar-days')
                    ->toggleable(),

                TextColumn::make('completed_bookings_count')
                    ->label(__('resources.user.completed_bookings'))
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        return DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.provider_id', $this->ownerRecord->id)
                            ->where('appointments.status', AppointmentStatus::COMPLETED)
                            ->count();
                    })
                    ->icon('heroicon-o-check-circle')
                    ->toggleable(),

                TextColumn::make('revenue_from_service')
                    ->label(__('resources.user.revenue_from_service'))
                    ->badge()
                    ->color('success')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(function ($record) {
                        // حساب الدخل من الحجوزات المكتملة لهذه الخدمة
                        $revenue = DB::table('appointment_services')
                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                            ->where('appointment_services.service_id', $record->id)
                            ->where('appointments.provider_id', $this->ownerRecord->id)
                            ->where('appointments.status', AppointmentStatus::COMPLETED)
                            ->sum('appointment_services.price');

                        return number_format($revenue, 2) . ' ' . __('resources.user.sar_currency');
                    })
                    ->icon('heroicon-o-currency-dollar')
                    ->description(__('resources.user.from_completed_only'))
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('services.created_at', 'desc')
            ->emptyStateHeading(__('resources.user.no_services'))
            ->emptyStateDescription(__('resources.user.no_services_assigned_desc'))
            ->emptyStateIcon('heroicon-o-wrench-screwdriver');
    }
}
