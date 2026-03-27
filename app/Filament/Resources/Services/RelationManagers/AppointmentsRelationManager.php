<?php

namespace App\Filament\Resources\Services\RelationManagers;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources.service.appointments_management');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'customer',
                'provider',
                'services' => fn ($servicesQuery) => $servicesQuery->orderBy('appointment_services.sequence_order'),
            ]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('resources.service.booking_number'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->copyMessage(__('resources.service.number_copied'))
                    ->color('primary'),

                TextColumn::make('customer_display')
                    ->label(__('resources.service.customer_name'))
                    ->state(fn ($record) => $record->customer_name)
                    ->description(fn ($record) => $record->customer_phone ?: ($record->customer_email ?: '—'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search) {
                            $query
                                ->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_email', 'like', "%{$search}%")
                                ->orWhere('customer_phone', 'like', "%{$search}%")
                                ->orWhereHas('customer', function (Builder $customerQuery) use ($search) {
                                    $customerQuery
                                        ->where('first_name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"]);
                                });
                        });
                    })
                    ->weight(FontWeight::SemiBold),

                TextColumn::make('provider.full_name')
                    ->label(__('resources.service.provider_name'))
                    ->description(fn ($record) => $record->provider?->email ?: '—')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('appointment_date')
                    ->label(__('resources.service.appointment_date'))
                    ->date('M d, Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->description(fn ($record) => $record->start_time->format('h:i A') . ' - ' . $record->end_time->format('h:i A')),

                TextColumn::make('services_list')
                    ->label(__('resources.service.services'))
                    ->state(function ($record) {
                        if ($record->services->isEmpty()) {
                            return __('resources.service.no_other_services');
                        }

                        return new HtmlString(
                            $record->services->map(function ($service) {
                                $isCurrentService = (int) $service->id === (int) $this->getOwnerRecord()->id;
                                $background = $isCurrentService ? '#dbeafe' : '#f3f4f6';
                                $color = $isCurrentService ? '#1d4ed8' : '#374151';
                                $fontWeight = $isCurrentService ? '700' : '500';
                                $serviceName = $service->pivot->service_name ?: $service->name;

                                return '<span style="display:inline-block;padding:2px 8px;margin:2px;background:' . $background . ';color:' . $color . ';border-radius:4px;font-size:0.75rem;font-weight:' . $fontWeight . ';">'
                                    . e($serviceName)
                                    . '</span>';
                            })->join('')
                        );
                    })
                    ->wrap(),

                TextColumn::make('pivot.price')
                    ->label(__('resources.service.service_price'))
                    ->money('EUR')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('pivot.duration_minutes')
                    ->label(__('resources.service.service_duration'))
                    ->formatStateUsing(fn ($state) => $this->formatDuration((int) $state))
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                TextColumn::make('pivot.sequence_order')
                    ->label(__('resources.service.sequence_order'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_amount')
                    ->label(__('resources.service.total_price'))
                    ->money('EUR')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->description(function ($record) {
                        if ($record->tax_amount > 0) {
                            return __('resources.service.includes_tax') . ': EUR ' . number_format((float) $record->tax_amount, 2);
                        }

                        return null;
                    }),

                TextColumn::make('status')
                    ->label(__('resources.service.appointment_status'))
                    ->badge()
                    ->formatStateUsing(fn (AppointmentStatus $state) => $this->getAppointmentStatusLabel($state))
                    ->color(fn (AppointmentStatus $state) => $this->getAppointmentStatusColor($state))
                    ->icon(fn (AppointmentStatus $state) => $this->getAppointmentStatusIcon($state))
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label(__('resources.service.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $this->getPaymentStatusLabel($state))
                    ->color(fn (PaymentStatus $state) => $this->getPaymentStatusColor($state))
                    ->icon(fn (PaymentStatus $state) => $this->getPaymentStatusIcon($state))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('resources.service.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('appointment_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('resources.service.filter_status'))
                    ->options([
                        AppointmentStatus::PENDING->value => __('resources.service.status_pending'),
                        AppointmentStatus::COMPLETED->value => __('resources.service.status_completed'),
                        AppointmentStatus::USER_CANCELLED->value => __('resources.service.status_user_cancelled'),
                        AppointmentStatus::ADMIN_CANCELLED->value => __('resources.service.status_admin_cancelled'),
                        AppointmentStatus::NO_SHOW->value => __('resources.service.status_no_show'),
                    ])
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label(__('resources.service.filter_payment'))
                    ->options([
                        PaymentStatus::PENDING->value => __('resources.service.payment_pending'),
                        PaymentStatus::PAID_ONLINE->value => __('resources.service.paid_online'),
                        PaymentStatus::PAID_ONSTIE_CASH->value => __('resources.service.paid_cash'),
                        PaymentStatus::PAID_ONSTIE_CARD->value => __('resources.service.paid_card'),
                        PaymentStatus::FAILED->value => __('resources.service.payment_failed'),
                        PaymentStatus::REFUNDED->value => __('resources.service.refunded'),
                        PaymentStatus::PARTIALLY_REFUNDED->value => __('resources.service.partially_refunded'),
                    ])
                    ->multiple(),

                TernaryFilter::make('today')
                    ->label(__('resources.service.filter_today'))
                    ->placeholder(__('resources.service.all'))
                    ->trueLabel(__('resources.service.today_only'))
                    ->falseLabel(__('resources.service.not_today'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereDate('appointment_date', today()),
                        false: fn (Builder $query) => $query->whereDate('appointment_date', '!=', today()),
                    ),

                TernaryFilter::make('upcoming')
                    ->label(__('resources.service.filter_time'))
                    ->placeholder(__('resources.service.all'))
                    ->trueLabel(__('resources.service.upcoming_appointments'))
                    ->falseLabel(__('resources.service.past_appointments'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('start_time', '>', now()),
                        false: fn (Builder $query) => $query->where('start_time', '<', now()),
                    ),

                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from_date')
                            ->label(__('resources.service.from_date')),
                        \Filament\Forms\Components\DatePicker::make('to_date')
                            ->label(__('resources.service.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('appointment_date', '>=', $date),
                            )
                            ->when(
                                $data['to_date'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('appointment_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from_date'] ?? null) {
                            $indicators[] = __('resources.service.from_date') . ': ' . \Carbon\Carbon::parse($data['from_date'])->format('M d, Y');
                        }

                        if ($data['to_date'] ?? null) {
                            $indicators[] = __('resources.service.to_date') . ': ' . \Carbon\Carbon::parse($data['to_date'])->format('M d, Y');
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('resources.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => AppointmentResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('resources.service.no_appointments'))
            ->emptyStateDescription(__('resources.service.no_appointments_desc'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    protected function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$remainingMinutes}m";
    }

    protected function getAppointmentStatusLabel(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PENDING => __('resources.service.status_pending'),
            AppointmentStatus::COMPLETED => __('resources.service.status_completed'),
            AppointmentStatus::USER_CANCELLED => __('resources.service.status_user_cancelled'),
            AppointmentStatus::ADMIN_CANCELLED => __('resources.service.status_admin_cancelled'),
            AppointmentStatus::NO_SHOW => __('resources.service.status_no_show'),
        };
    }

    protected function getAppointmentStatusColor(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PENDING => 'warning',
            AppointmentStatus::COMPLETED => 'success',
            AppointmentStatus::USER_CANCELLED => 'danger',
            AppointmentStatus::ADMIN_CANCELLED => 'gray',
            AppointmentStatus::NO_SHOW => 'danger',
        };
    }

    protected function getAppointmentStatusIcon(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PENDING => 'heroicon-o-clock',
            AppointmentStatus::COMPLETED => 'heroicon-o-check-circle',
            AppointmentStatus::USER_CANCELLED => 'heroicon-o-x-circle',
            AppointmentStatus::ADMIN_CANCELLED => 'heroicon-o-no-symbol',
            AppointmentStatus::NO_SHOW => 'heroicon-o-exclamation-triangle',
        };
    }

    protected function getPaymentStatusLabel(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::PENDING => __('resources.service.payment_pending'),
            PaymentStatus::PAID_ONLINE => __('resources.service.paid_online'),
            PaymentStatus::PAID_ONSTIE_CASH => __('resources.service.paid_cash'),
            PaymentStatus::PAID_ONSTIE_CARD => __('resources.service.paid_card'),
            PaymentStatus::FAILED => __('resources.service.payment_failed'),
            PaymentStatus::REFUNDED => __('resources.service.refunded'),
            PaymentStatus::PARTIALLY_REFUNDED => __('resources.service.partially_refunded'),
        };
    }

    protected function getPaymentStatusColor(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::PENDING => 'warning',
            PaymentStatus::PAID_ONLINE, PaymentStatus::PAID_ONSTIE_CASH, PaymentStatus::PAID_ONSTIE_CARD => 'success',
            PaymentStatus::FAILED => 'danger',
            PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => 'gray',
        };
    }

    protected function getPaymentStatusIcon(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::PENDING => 'heroicon-o-clock',
            PaymentStatus::PAID_ONLINE, PaymentStatus::PAID_ONSTIE_CASH, PaymentStatus::PAID_ONSTIE_CARD => 'heroicon-o-check-badge',
            PaymentStatus::FAILED => 'heroicon-o-x-circle',
            PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => 'heroicon-o-arrow-uturn-left',
        };
    }
}
