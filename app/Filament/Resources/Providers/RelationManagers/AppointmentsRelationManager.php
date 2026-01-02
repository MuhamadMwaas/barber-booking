<?php

namespace App\Filament\Resources\Providers\RelationManagers;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Services\InvoiceService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointmentsAsProvider';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('resources.provider_resource.appointments_management');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                // رقم الحجز
                TextColumn::make('number')
                    ->label(__('resources.provider_resource.booking_number'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->copyMessage(__('resources.provider_resource.number_copied'))
                    ->color('primary'),

                // اسم العميل
                TextColumn::make('customer_display')
                    ->label(__('resources.provider_resource.customer_name'))
                    ->state(fn ($record) => $record->customer_name)
                    ->description(function ($record) {
                        return $record->customer_phone
                            ?? $record->customer_email
                            ?? __('resources.user.not_provided');
                    })
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%")
                              ->orWhere('customer_email', 'like', "%{$search}%")
                              ->orWhere('customer_phone', 'like', "%{$search}%")
                              ->orWhereHas('customer', function ($qq) use ($search) {
                                  $qq->where('first_name', 'like', "%{$search}%")
                                     ->orWhere('last_name', 'like', "%{$search}%")
                                     ->orWhereRaw("CONCAT(first_name,' ',last_name) like ?", ["%{$search}%"]);
                              });
                        });
                    })
                    ->weight(FontWeight::SemiBold),

                // التاريخ والوقت
                TextColumn::make('appointment_date')
                    ->label(__('resources.provider_resource.appointment_date'))
                    ->date('M d, Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->description(fn($record) => $record->start_time->format('h:i A') . ' - ' . $record->end_time->format('h:i A')),

                // المدة
                TextColumn::make('duration_minutes')
                    ->label(__('resources.provider_resource.duration'))
                    ->formatStateUsing(function ($state) {
                        $hours = floor($state / 60);
                        $minutes = $state % 60;
                        return $hours > 0
                            ? "{$hours}h {$minutes}m"
                            : "{$minutes}m";
                    })
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-clock'),

                // الخدمات
                TextColumn::make('services_list')
                    ->label(__('resources.provider_resource.services'))
                    ->state(function ($record) {
                        if ($record->services->isEmpty()) {
                            return __('resources.provider_resource.no_services');
                        }

                        return new HtmlString(
                            $record->services->map(function ($service) {
                                return '<span style="display: inline-block; padding: 2px 8px; margin: 2px; background: #f3f4f6; border-radius: 4px; font-size: 0.75rem;">'
                                    . htmlspecialchars($service->pivot->service_name)
                                    . '</span>';
                            })->join('')
                        );
                    })
                    ->limit(50)
                    ->wrap(),

                // السعر الإجمالي
                TextColumn::make('total_amount')
                    ->label(__('resources.provider_resource.total_price'))
                    ->money('SAR')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->description(function ($record) {
                        if ($record->tax_amount > 0) {
                            return __('resources.provider_resource.includes_tax') . ': SAR ' . number_format($record->tax_amount, 2);
                        }
                        return null;
                    }),

                // حالة الحجز
                TextColumn::make('status')
                    ->label(__('resources.provider_resource.appointment_status'))
                    ->badge()
                    ->formatStateUsing(fn(AppointmentStatus $state) => match ($state) {
                        AppointmentStatus::PENDING => __('resources.provider_resource.status_pending'),
                        AppointmentStatus::COMPLETED => __('resources.provider_resource.status_completed'),
                        AppointmentStatus::USER_CANCELLED => __('resources.provider_resource.status_user_cancelled'),
                        AppointmentStatus::ADMIN_CANCELLED => __('resources.provider_resource.status_admin_cancelled'),
                    })
                    ->color(fn(AppointmentStatus $state) => match ($state) {
                        AppointmentStatus::PENDING => 'warning',
                        AppointmentStatus::COMPLETED => 'success',
                        AppointmentStatus::USER_CANCELLED => 'danger',
                        AppointmentStatus::ADMIN_CANCELLED => 'gray',
                    })
                    ->icon(fn(AppointmentStatus $state) => match ($state) {
                        AppointmentStatus::PENDING => 'heroicon-o-clock',
                        AppointmentStatus::COMPLETED => 'heroicon-o-check-circle',
                        AppointmentStatus::USER_CANCELLED => 'heroicon-o-x-circle',
                        AppointmentStatus::ADMIN_CANCELLED => 'heroicon-o-no-symbol',
                    })
                    ->sortable(),

                // حالة الدفع
                TextColumn::make('payment_status')
                    ->label(__('resources.provider_resource.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn(PaymentStatus $state) => match ($state) {
                        PaymentStatus::PENDING => __('resources.provider_resource.payment_pending'),
                        PaymentStatus::PAID_ONLINE => __('resources.provider_resource.paid_online'),
                        PaymentStatus::PAID_ONSTIE_CASH => __('resources.provider_resource.paid_cash'),
                        PaymentStatus::PAID_ONSTIE_CARD => __('resources.provider_resource.paid_card'),
                        PaymentStatus::FAILED => __('resources.provider_resource.payment_failed'),
                        PaymentStatus::REFUNDED => __('resources.provider_resource.refunded'),
                        PaymentStatus::PARTIALLY_REFUNDED => __('resources.provider_resource.partially_refunded'),
                    })
                    ->color(fn(PaymentStatus $state) => match ($state) {
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::PAID_ONLINE, PaymentStatus::PAID_ONSTIE_CASH, PaymentStatus::PAID_ONSTIE_CARD => 'success',
                        PaymentStatus::FAILED => 'danger',
                        PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => 'gray',
                    })
                    ->icon(fn(PaymentStatus $state) => match ($state) {
                        PaymentStatus::PENDING => 'heroicon-o-clock',
                        PaymentStatus::PAID_ONLINE, PaymentStatus::PAID_ONSTIE_CASH, PaymentStatus::PAID_ONSTIE_CARD => 'heroicon-o-check-badge',
                        PaymentStatus::FAILED => 'heroicon-o-x-circle',
                        PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED => 'heroicon-o-arrow-uturn-left',
                    })
                    ->sortable(),
            ])
            ->defaultSort('appointment_date', 'desc')
            ->filters([
                // فلتر حسب حالة الحجز
                SelectFilter::make('status')
                    ->label(__('resources.provider_resource.filter_status'))
                    ->options([
                        AppointmentStatus::PENDING->value => __('resources.provider_resource.status_pending'),
                        AppointmentStatus::COMPLETED->value => __('resources.provider_resource.status_completed'),
                        AppointmentStatus::USER_CANCELLED->value => __('resources.provider_resource.status_user_cancelled'),
                        AppointmentStatus::ADMIN_CANCELLED->value => __('resources.provider_resource.status_admin_cancelled'),
                    ])
                    ->multiple(),

                // فلتر حسب حالة الدفع
                SelectFilter::make('payment_status')
                    ->label(__('resources.provider_resource.filter_payment'))
                    ->options([
                        PaymentStatus::PENDING->value => __('resources.provider_resource.payment_pending'),
                        PaymentStatus::PAID_ONLINE->value => __('resources.provider_resource.paid_online'),
                        PaymentStatus::PAID_ONSTIE_CASH->value => __('resources.provider_resource.paid_cash'),
                        PaymentStatus::PAID_ONSTIE_CARD->value => __('resources.provider_resource.paid_card'),
                        PaymentStatus::FAILED->value => __('resources.provider_resource.payment_failed'),
                    ])
                    ->multiple(),

                // فلتر الحجوزات القادمة / السابقة
                TernaryFilter::make('upcoming')
                    ->label(__('resources.provider_resource.filter_time'))
                    ->placeholder(__('resources.all'))
                    ->trueLabel(__('resources.provider_resource.upcoming_appointments'))
                    ->falseLabel(__('resources.provider_resource.past_appointments'))
                    ->queries(
                        true: fn($query) => $query->where('start_time', '>', now()),
                        false: fn($query) => $query->where('start_time', '<', now()),
                    ),

                // فلتر حجوزات اليوم
                TernaryFilter::make('today')
                    ->label(__('resources.provider_resource.filter_today'))
                    ->placeholder(__('resources.all'))
                    ->trueLabel(__('resources.provider_resource.today_only'))
                    ->falseLabel(__('resources.provider_resource.not_today'))
                    ->queries(
                        true: fn($query) => $query->whereDate('appointment_date', today()),
                        false: fn($query) => $query->whereDate('appointment_date', '!=', today()),
                    ),

                // فلتر حسب نطاق التاريخ
                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from_date')
                            ->label(__('resources.provider_resource.from_date')),
                        \Filament\Forms\Components\DatePicker::make('to_date')
                            ->label(__('resources.provider_resource.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('start_time', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn(Builder $query, $date): Builder => $query->whereDate('end_time', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = __('resources.provider_resource.from_date') . ': ' . \Carbon\Carbon::parse($data['from_date'])->format('M d, Y');
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = __('resources.provider_resource.to_date') . ': ' . \Carbon\Carbon::parse($data['to_date'])->format('M d, Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                // زر عرض التفاصيل
                Action::make('view')
                    ->label(__('resources.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('filament.admin.resources.appointments.view', ['record' => $record])),

                // زر الدفع
                Action::make('pay')
                    ->label(__('resources.provider_resource.mark_as_paid'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(fn($record) => $record->payment_status === PaymentStatus::PENDING
                        && $record->status !== AppointmentStatus::ADMIN_CANCELLED
                        && $record->status !== AppointmentStatus::USER_CANCELLED)
                    ->fillForm(fn($record) => [
                        'payment_type' => PaymentStatus::PAID_ONSTIE_CASH->value,
                        'adjusted_duration' => $record->duration_minutes,
                        'amount_paid' => $record->total_amount,
                        'start_time_display' => $record->start_time->format('h:i A'),
                        'start_time_value' => $record->start_time->format('Y-m-d H:i:s'),
                        'adjusted_end_time' => $record->end_time->format('H:i'),
                        'duration_display' => $record->duration_minutes,
                    ])
                    ->schema([
                        Select::make('payment_type')
                            ->label(__('resources.provider_resource.payment_method'))
                            ->options([
                                PaymentStatus::PAID_ONSTIE_CASH->value => __('resources.provider_resource.paid_cash'),
                                PaymentStatus::PAID_ONSTIE_CARD->value => __('resources.provider_resource.paid_card'),
                                PaymentStatus::PAID_ONLINE->value => __('resources.provider_resource.paid_online'),
                            ])
                            ->default(PaymentStatus::PAID_ONSTIE_CASH->value)
                            ->required()
                            ->columnSpanFull(),

                        Hidden::make('start_time_value'),

                        TextInput::make('start_time_display')
                            ->label(__('resources.provider_resource.start_time'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),

                        TimePicker::make('adjusted_end_time')
                            ->label(__('resources.provider_resource.end_time'))
                            ->seconds(false)
                            ->required()
                            ->helperText(__('resources.provider_resource.end_time_helper'))
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && $get('start_time_value')) {
                                    $startTime = \Carbon\Carbon::parse($get('start_time_value'));
                                    $endTimeParts = explode(':', $state);

                                    $newEndTime = $startTime->copy()
                                        ->setTime((int) $endTimeParts[0], (int) $endTimeParts[1], 0);

                                    $durationMinutes = $startTime->diffInMinutes($newEndTime);

                                    $set('adjusted_duration', $durationMinutes);
                                    $set('duration_display', $durationMinutes);
                                }
                            })
                            ->columnSpan(1),

                        TextInput::make('duration_display')
                            ->label(__('resources.provider_resource.duration'))
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('min')
                            ->columnSpan(1),

                        Hidden::make('adjusted_duration'),

                        TextInput::make('amount_paid')
                            ->label(__('resources.provider_resource.amount_paid'))
                            ->numeric()
                            ->prefix('SAR')
                            ->suffix(__('resources.provider_resource.includes_tax_suffix'))
                            ->default(fn($record) => $record->total_amount)
                            ->required()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    // حساب الضريبة العكسية (19%)
                                    $taxRate = 19;
                                    $subtotal = $state / (1 + ($taxRate / 100));
                                    $taxAmount = $state - $subtotal;

                                    $set('calculated_subtotal', round($subtotal, 2));
                                    $set('calculated_tax', round($taxAmount, 2));
                                }
                            })
                            ->helperText(
                                fn($get) =>
                                $get('calculated_subtotal')
                                ? __('resources.provider_resource.breakdown') . ': ' .
                                __('resources.provider_resource.subtotal') . ' SAR ' . number_format($get('calculated_subtotal'), 2) . ' + ' .
                                __('resources.provider_resource.tax') . ' (19%) SAR ' . number_format($get('calculated_tax'), 2)
                                : __('resources.provider_resource.amount_paid_helper')
                            ),

                        Textarea::make('notes')
                            ->label(__('resources.provider_resource.payment_notes'))
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $invoiceService = app(InvoiceService::class);
                            $invoiceService->validateInvoiceCreation($record);

                            if(!is_null($data['adjusted_duration']) && $data['adjusted_duration'] > 0) {
                                $adjustedDuration = $data['adjusted_duration'];
                            } else {
                                $adjustedDuration = $record->duration_minutes;
                            }

                            $amountPaid = $data['amount_paid'];
                            $taxCalculation = $invoiceService->calculateReverseTax($amountPaid, 19);

                            $record->update([
                                'total_amount' => $amountPaid,
                                'tax_amount' => $taxCalculation['tax_amount'],
                                'duration_minutes' => $adjustedDuration,
                                'end_time' => $record->end_time->setTimeFromTimeString($data['adjusted_end_time']),
                                'status' => AppointmentStatus::COMPLETED->value,
                            ]);

                            $invoice = $invoiceService->createInvoiceFromAppointment(
                                appointment: $record->fresh(),
                                paymentType: $data['payment_type'],
                                amountPaid: $amountPaid,
                                notes: $data['notes'] ?? null,
                                adjustedDuration: $adjustedDuration,
                                amountIncludesTax: true
                            );

                            Notification::make()
                                ->title(__('resources.provider_resource.payment_success'))
                                ->body(__('resources.provider_resource.invoice_created') . ': ' . $invoice->invoice_number)
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('resources.provider_resource.payment_error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalWidth('2xl'),
            ])
            ->emptyStateHeading(__('resources.provider_resource.no_appointments_yet'))
            ->emptyStateDescription(__('resources.provider_resource.no_appointments_desc'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}