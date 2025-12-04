<?php

namespace App\Filament\Resources\Users\RelationManagers;
use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Model;
class CustomerAppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerAppointments';

    protected static ?string $relatedResource = UserResource::class;

        public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
            return __('resources.user.customer_services_statistics');

    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {

        return $ownerRecord->hasRole('customer');
    }
    public function table(Table $table): Table
    {
        return  $this->buildCustomerServicesTable($table);
    }

        protected function buildCustomerServicesTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('resources.appointment.number'))
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-hashtag')
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('appointment_date')
                    ->label(__('resources.appointment.date'))
                    ->date('Y-m-d')
                    ->icon('heroicon-o-calendar')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label(__('resources.appointment.time'))
                    ->time('H:i')
                    ->description(fn ($record) => $record->end_time->format('H:i'))
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                TextColumn::make('services')
                    ->label(__('resources.appointment.services'))
                    ->badge()
                    ->getStateUsing(function ($record) {
                        return $record->services->pluck('name')->toArray();
                    })
                    ->separator(',')
                    ->icon('heroicon-o-scissors')
                    ->color('info')
                    ->wrap(),

                TextColumn::make('provider.full_name')
                    ->label(__('resources.appointment.provider'))
                    ->icon('heroicon-o-user')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label(__('resources.appointment.total'))
                    ->badge()
                    ->color('success')
                    ->weight(FontWeight::Bold)
                    ->money('SAR')
                    ->icon('heroicon-o-banknotes')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('resources.appointment.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->getLabel())
                    ->color(fn ($state) => match ($state) {
                        AppointmentStatus::PENDING => 'warning',
                        AppointmentStatus::COMPLETED => 'success',
                        AppointmentStatus::USER_CANCELLED, AppointmentStatus::ADMIN_CANCELLED => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        AppointmentStatus::PENDING => 'heroicon-o-clock',
                        AppointmentStatus::COMPLETED => 'heroicon-o-check-circle',
                        AppointmentStatus::USER_CANCELLED, AppointmentStatus::ADMIN_CANCELLED => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label(__('resources.appointment.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => match ($state) {
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::PAID_ONLINE, PaymentStatus::PAID_ONSTIE_CASH, PaymentStatus::PAID_ONSTIE_CARD => 'success',
                        PaymentStatus::FAILED => 'danger',
                        PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('duration_minutes')
                    ->label(__('resources.appointment.duration'))
                    ->suffix(' ' . __('resources.appointment.minutes'))
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('resources.appointment.booked_at'))
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->icon('heroicon-o-calendar-days')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([])
            ->defaultSort('appointment_date', 'desc')
            ->emptyStateHeading(__('resources.user.no_appointments'))
            ->emptyStateDescription(__('resources.user.no_appointments_desc'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
