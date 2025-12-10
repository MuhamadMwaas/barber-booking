<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Actions\ActionGroup;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // رقم الحجز
                TextColumn::make('number')
                    ->label(__('resources.appointment.booking_number'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->copyMessage(__('resources.appointment.number_copied'))
                    ->color('primary'),

                // العميل
                // TextColumn::make('customer.full_name')
                //     ->label(__('resources.appointment.customer_name'))
                //     ->searchable(['first_name', 'last_name'])
                //     ->sortable(['first_name', 'last_name'])
                //     ->weight(FontWeight::SemiBold)
                //     ->description(fn($record) => $record->customer->phone ?? __('resources.user.not_provided')),

TextColumn::make('customer_display') // اسم افتراضي للعمود
    ->label(__('resources.appointment.customer_name'))
    ->state(fn ($record) => $record->customer_display_name) // من الـ accessor
    ->description(function ($record) {
        return $record->customer_phone
            ?? $record->customer_email
            ?? __('resources.user.not_provided');
    })

    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search) {
        $query->where(function ($q) use ($search) {
            $q->where('customer_name', 'like', "%{$search}%")
              ->orWhere('customer_email', 'like', "%{$search}%")
              ->orWhere('customer_phone', 'like', "%{$search}%")
              ->orWhereHas('customer', function ($qq) use ($search) {
                  $qq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%")
                     ->orWhereRaw("CONCAT(first_name,' ',last_name) like ?", ["%{$search}%"])
                     ->orWhere('email', 'like', "%{$search}%")
                     ->orWhere('phone', 'like', "%{$search}%");
              });
        });
    })

    ->sortable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $direction) {
        $usersFullName = <<<SQL
            (SELECT CONCAT(u.first_name, ' ', u.last_name)
             FROM users u
             WHERE u.id = appointments.customer_id)
        SQL;

        $query->orderByRaw(
            "COALESCE(customer_name, {$usersFullName}) " . ($direction === 'asc' ? 'asc' : 'desc')
        );
    })
    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                // مزود الخدمة
                ImageColumn::make('provider.profile_image_url')
                    ->label(__('resources.appointment.provider'))
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->provider->full_name) . '&color=10b981&background=d1fae5')
                    ->size(40),

                TextColumn::make('provider.full_name')
                    ->label(__('resources.appointment.provider_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight(FontWeight::SemiBold)
                    ->description(fn($record) => $record->provider->branch->name ?? __('resources.no_branch')),

                // الخدمات
                TextColumn::make('services_list')
                    ->label(__('resources.appointment.services'))
                    ->state(function ($record) {
                        if ($record->services->isEmpty()) {
                            return __('resources.appointment.no_services');
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

                // التاريخ والوقت
                TextColumn::make('appointment_date')
                    ->label(__('resources.appointment.date'))
                    ->date('M d, Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->description(fn($record) => $record->start_time->format('h:i A') . ' - ' . $record->end_time->format('h:i A')),

                // المدة
                TextColumn::make('duration_minutes')
                    ->label(__('resources.appointment.duration'))
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

                // السعر الإجمالي
                TextColumn::make('total_amount')
                    ->label(__('resources.appointment.total_price'))
                    ->money('SAR')
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->description(function ($record) {
                        if ($record->tax_amount > 0) {
                            return __('resources.appointment.includes_tax') . ': SAR ' . number_format($record->tax_amount, 2);
                        }
                        return null;
                    }),

                // حالة الحجز
                TextColumn::make('status')
                    ->label(__('resources.appointment.status'))
                    ->badge()
                    ->formatStateUsing(fn(AppointmentStatus $state) => match ($state) {
                        AppointmentStatus::PENDING => __('resources.appointment.pending'),
                        AppointmentStatus::COMPLETED => __('resources.appointment.completed'),
                        AppointmentStatus::USER_CANCELLED => __('resources.appointment.user_cancelled'),
                        AppointmentStatus::ADMIN_CANCELLED => __('resources.appointment.admin_cancelled'),
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
                    ->label(__('resources.appointment.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn(PaymentStatus $state) => match ($state) {
                        PaymentStatus::PENDING => __('resources.appointment.payment_pending'),
                        PaymentStatus::PAID_ONLINE => __('resources.appointment.paid_online'),
                        PaymentStatus::PAID_ONSTIE_CASH => __('resources.appointment.paid_cash'),
                        PaymentStatus::PAID_ONSTIE_CARD => __('resources.appointment.paid_card'),
                        PaymentStatus::FAILED => __('resources.appointment.payment_failed'),
                        PaymentStatus::REFUNDED => __('resources.appointment.refunded'),
                        PaymentStatus::PARTIALLY_REFUNDED => __('resources.appointment.partially_refunded'),
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

                // وقت الإنشاء
                TextColumn::make('created_at')
                    ->label(__('resources.appointment.created_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),

                // آخر تحديث
                TextColumn::make('updated_at')
                    ->label(__('resources.appointment.updated_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // فلتر حسب حالة الحجز
                SelectFilter::make('status')
                    ->label(__('resources.appointment.filter_status'))
                    ->options([
                        AppointmentStatus::PENDING->value => __('resources.appointment.pending'),
                        AppointmentStatus::COMPLETED->value => __('resources.appointment.completed'),
                        AppointmentStatus::USER_CANCELLED->value => __('resources.appointment.user_cancelled'),
                        AppointmentStatus::ADMIN_CANCELLED->value => __('resources.appointment.admin_cancelled'),
                    ])
                    ->multiple(),

                // فلتر حسب حالة الدفع
                SelectFilter::make('payment_status')
                    ->label(__('resources.appointment.filter_payment'))
                    ->options([
                        PaymentStatus::PENDING->value => __('resources.appointment.payment_pending'),
                        PaymentStatus::PAID_ONLINE->value => __('resources.appointment.paid_online'),
                        PaymentStatus::PAID_ONSTIE_CASH->value => __('resources.appointment.paid_cash'),
                        PaymentStatus::PAID_ONSTIE_CARD->value => __('resources.appointment.paid_card'),
                        PaymentStatus::FAILED->value => __('resources.appointment.payment_failed'),
                    ])
                    ->multiple(),

                // فلتر حسب المزود
                SelectFilter::make('provider_id')
                    ->label(__('resources.appointment.filter_provider'))
                    ->relationship('provider', 'first_name')
                    ->searchable()
                    ->preload(),

                // فلتر حسب الخدمة
                SelectFilter::make('service')
                    ->label(__('resources.appointment.filter_service'))
                    ->relationship('services', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                // فلتر الحجوزات القادمة / السابقة
                TernaryFilter::make('upcoming')
                    ->label(__('resources.appointment.filter_time'))
                    ->placeholder(__('resources.all'))
                    ->trueLabel(__('resources.appointment.upcoming'))
                    ->falseLabel(__('resources.appointment.past'))
                    ->queries(
                        true: fn($query) => $query->where('start_time', '>', now()),
                        false: fn($query) => $query->where('start_time', '<', now()),
                    ),

                // فلتر حجوزات اليوم
                TernaryFilter::make('today')
                    ->label(__('resources.appointment.filter_today'))
                    ->placeholder(__('resources.all'))
                    ->trueLabel(__('resources.appointment.today_only'))
                    ->falseLabel(__('resources.appointment.not_today'))
                    ->queries(
                        true: fn($query) => $query->whereDate('appointment_date', today()),
                        false: fn($query) => $query->whereDate('appointment_date', '!=', today()),
                    ),

                // فلتر حسب نطاق التاريخ والوقت
                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from_date')
                            ->label(__('resources.appointment.from_date')),
                        \Filament\Forms\Components\DatePicker::make('to_date')
                            ->label(__('resources.appointment.to_date')),
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
                            $indicators[] = __('resources.appointment.from_date') . ': ' . \Carbon\Carbon::parse($data['from_date'])->format('M d, Y');
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = __('resources.appointment.to_date') . ': ' . \Carbon\Carbon::parse($data['to_date'])->format('M d, Y');
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                // زر الدفع
                Action::make('pay')
                    ->label(__('resources.appointment.mark_as_paid'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(fn($record) => $record->payment_status === PaymentStatus::PENDING && $record->status !== AppointmentStatus::ADMIN_CANCELLED && $record->status !== AppointmentStatus::USER_CANCELLED)
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
                            ->label(__('resources.appointment.payment_method'))
                            ->options([
                                PaymentStatus::PAID_ONSTIE_CASH->value => __('resources.appointment.paid_cash'),
                                PaymentStatus::PAID_ONSTIE_CARD->value => __('resources.appointment.paid_card'),
                                PaymentStatus::PAID_ONLINE->value => __('resources.appointment.paid_online'),
                            ])
                            ->default(PaymentStatus::PAID_ONSTIE_CASH->value)
                            ->required()
                            ->columnSpanFull(),

                        Hidden::make('start_time_value'),

                        TextInput::make('start_time_display')
                            ->label(__('resources.appointment.start_time'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),

                        TimePicker::make('adjusted_end_time')
                            ->label(__('resources.appointment.end_time'))
                            ->seconds(false)
                            ->required()
                            ->helperText(__('resources.appointment.end_time_helper'))
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
                            ->label(__('resources.appointment.duration'))
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('min')
                            ->columnSpan(1),

                        Hidden::make('adjusted_duration'),

                        TextInput::make('amount_paid')
                            ->label(__('resources.appointment.amount_paid'))
                            ->numeric()
                            ->prefix('SAR')
                            ->suffix(__('resources.appointment.includes_tax_suffix'))
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
                                ? __('resources.appointment.breakdown') . ': ' .
                                __('resources.appointment.subtotal') . ' SAR ' . number_format($get('calculated_subtotal'), 2) . ' + ' .
                                __('resources.appointment.tax') . ' (19%) SAR ' . number_format($get('calculated_tax'), 2)
                                : __('resources.appointment.amount_paid_helper')
                            ),

                        Textarea::make('notes')
                            ->label(__('resources.appointment.payment_notes'))
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // استخدام InvoiceService لإنشاء الفاتورة بشكل احترافي
                            $invoiceService = app(InvoiceService::class);

                            // التحقق من صحة العملية قبل الإنشاء
                            $invoiceService->validateInvoiceCreation($record);

                            if(!is_null($data['adjusted_duration']) && $data['adjusted_duration'] > 0 ){
                            $adjustedDuration = $data['adjusted_duration'] ?? $record->duration_minutes;

                            }else{
                            $adjustedDuration = $record->duration_minutes;
                            }

                            $amountPaid = $data['amount_paid'];

                            // حساب الضريبة العكسية لتحديث الحجز
                            $taxCalculation = $invoiceService->calculateReverseTax($amountPaid, 19);

                            // تحديث السعر والضريبة في الحجز
                            $record->update([
                                'total_amount' => $amountPaid,
                                'tax_amount' => $taxCalculation['tax_amount'],
                                'duration_minutes' =>  $adjustedDuration ,
                                'end_time' =>  $record->end_time->setTimeFromTimeString($data['adjusted_end_time']),
                                'status' => AppointmentStatus::COMPLETED->value,

                            ]);

                            // إنشاء الفاتورة مع جميع البنود (سيقوم بتحديث المدة تلقائياً)
                            $invoice = $invoiceService->createInvoiceFromAppointment(
                                appointment: $record->fresh(),
                                paymentType: $data['payment_type'],
                                amountPaid: $amountPaid,
                                notes: $data['notes'] ?? null,
                                adjustedDuration: $adjustedDuration,
                                amountIncludesTax: true // المبلغ يتضمن الضريبة
                            );

                            Notification::make()
                                ->title(__('resources.appointment.payment_success'))
                                ->body(__('resources.appointment.invoice_created') . ': ' . $invoice->invoice_number)
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('resources.appointment.payment_error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalWidth('2xl'),

                Action::make('ajuste_duration')
                    ->label(__('resources.appointment.ajuste_duration'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    // ->visible(function($record){
                    //     return $record->end_time > now();
                    // })
                    ->fillForm(fn($record) => [
                        'adjusted_duration' => $record->duration_minutes,
                        'start_time_display' => $record->start_time->format('h:i A'),
                        'start_time_value' => $record->start_time->format('Y-m-d H:i:s'),
                        'adjusted_end_time' => $record->end_time->format('H:i'),
                        'duration_display' => $record->duration_minutes,
                    ])->schema([
                            Hidden::make('start_time_value'),

                            TextInput::make('start_time_display')
                                ->label(__('resources.appointment.start_time'))
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(1),

                            TimePicker::make('adjusted_end_time')
                                ->label(__('resources.appointment.end_time'))
                                ->seconds(false)
                                ->required()
                                ->helperText(__('resources.appointment.end_time_helper'))
                                ->live(debounce: 500)
                                ->afterStateUpdated(function ($state, $set, $get, $record) {
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
                                ->label(__('resources.appointment.duration'))
                                ->disabled()
                                ->dehydrated(false)
                                ->suffix('min')
                                ->columnSpan(1),

                            Hidden::make('adjusted_duration'),
                        ])->action(function ($record, array $data) {
                            try {

                                $adjustedDuration = $data['adjusted_duration'] ?? $record->duration_minutes;

                                $record->update([
                                    'duration_minutes' => $adjustedDuration,
                                    'end_time' => $record->end_time->setTimeFromTimeString($data['adjusted_end_time']),

                                ]);

                                Notification::make()
                                    ->title(__('resources.appointment.duration_updated'))
                                    ->body(__('resources.appointment.duration_updated_message'))
                                    ->success()
                                    ->send();


                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title(__('resources.appointment.payment_error'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                    ->modalWidth('2xl'),



                ActionGroup::make([
                    Action::make('view')
                        ->label(__('resources.view'))
                        ->icon('heroicon-o-eye')
                        ->url(fn($record) => route('filament.admin.resources.appointments.view', ['record' => $record])),

                    Action::make('edit')
                        ->label(__('resources.edit'))
                        ->icon('heroicon-o-pencil')
                        ->url(fn($record) => route('filament.admin.resources.appointments.edit', ['record' => $record])),

                    Action::make('cancel')
                        ->label(__('main.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn($record) => $record->status === AppointmentStatus::PENDING)
                        ->requiresConfirmation()
                        ->modalHeading(__('resources.appointment.cancel_confirmation'))
                        ->modalDescription(__('resources.appointment.cancel_description'))
                        ->form([
                            Textarea::make('cancellation_reason')
                                ->label(__('resources.appointment.cancellation_reason'))
                                ->required()
                                ->rows(3)
                                ->maxLength(500),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update([
                                'status' => AppointmentStatus::ADMIN_CANCELLED,
                                'cancellation_reason' => $data['cancellation_reason'],
                                'cancelled_at' => now(),
                            ]);

                            Notification::make()
                                ->title(__('resources.appointment.cancelled_successfully'))
                                ->success()
                                ->send();
                        }),
                ]),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Action::make('delete')
                        ->label(__('resources.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each->delete()),
                ]),
            ])
            ->poll('30s') // تحديث تلقائي كل 30 ثانية لمراقبة الحجوزات الجديدة
            ->emptyStateHeading(__('resources.appointment.no_appointments'))
            ->emptyStateDescription(__('resources.appointment.no_appointments_desc'))
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
