<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header - Booking Overview
                Section::make('')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('number')
                                    ->label(__('resources.appointment.number'))
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->icon('heroicon-o-hashtag')
                                    ->copyable(),

                                TextEntry::make('status')
                                    ->label(__('resources.appointment.status'))
                                    ->badge()
                                    ->size('lg')
                                    ->formatStateUsing(fn ($state) => $state->getLabel())
                                    ->color(fn ($state) => match($state) {
                                        AppointmentStatus::COMPLETED => 'success',
                                        AppointmentStatus::USER_CANCELLED, AppointmentStatus::ADMIN_CANCELLED => 'danger',
                                        AppointmentStatus::PENDING => 'warning',
                                        default => 'gray',
                                    }),

                                TextEntry::make('payment_status')
                                    ->label(__('resources.appointment.payment_status'))
                                    ->badge()
                                    ->size('lg')
                                    ->formatStateUsing(fn ($state) => $state ? $state->label() : __('N/A'))
                                    ->color(fn ($state) => match($state) {
                                        PaymentStatus::PAID_ONLINE, PaymentStatus::PAID_ONSTIE_CASH, PaymentStatus::PAID_ONSTIE_CARD => 'success',
                                        PaymentStatus::PENDING => 'warning',
                                        PaymentStatus::FAILED => 'danger',
                                        default => 'gray',
                                    }),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('appointment_date')
                                    ->label(__('resources.appointment.date'))
                                    ->date('l, d M Y')
                                    ->icon('heroicon-o-calendar-days')
                                    ->color('warning'),

                                TextEntry::make('time_range')
                                    ->label(__('resources.appointment.time'))
                                    ->icon('heroicon-o-clock')
                                    ->color('info'),

                                TextEntry::make('duration_minutes')
                                    ->label(__('resources.appointment.duration'))
                                    ->formatStateUsing(fn ($state) => floor($state / 60) > 0
                                        ? floor($state / 60) . "h " . ($state % 60) . "m"
                                        : "{$state}m")
                                    ->badge()
                                    ->color('purple')
                                    ->icon('heroicon-o-clock'),
                            ]),
                    ])
                    ->compact(),

                // Two Columns Grid
                Grid::make(2)
                    ->schema([
                        // Customer Information
                        Section::make(__('resources.appointment.customer_info'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                TextEntry::make('customer.full_name')
                                    ->label(__('resources.user.name'))
                                    ->size('md')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->icon('heroicon-o-user-circle'),

                                TextEntry::make('customer.email')
                                    ->label(__('resources.user.email'))
                                    ->icon('heroicon-o-envelope')
                                    ->copyable(),

                                TextEntry::make('customer.phone')
                                    ->label(__('resources.user.phone'))
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),

                                TextEntry::make('customer.id')
                                    ->label(__('resources.user.customer_id'))
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($state) => "#{$state}"),

                                TextEntry::make('customer_stats')
                                    ->label(__('resources.appointment.customer_stats'))
                                    ->state(function ($record) {
                                        if (!$record->customer) {
                                            return new HtmlString('<span style="color: #94a3b8;">N/A</span>');
                                        }

                                        $totalAppointments = $record->customer->customerAppointments()->count();
                                        $completedAppointments = $record->customer->customerAppointments()
                                            ->where('status', AppointmentStatus::COMPLETED)->count();
                                        $cancelledAppointments = $record->customer->customerAppointments()
                                            ->where('status', AppointmentStatus::USER_CANCELLED)->count();

                                        return new HtmlString("
                                            <div style='display: flex; gap: 0.5rem; flex-wrap: wrap;'>
                                                <span style='padding: 0.25rem 0.75rem; background: #dbeafe; color: #1e40af; border-radius: 4px; font-size: 0.875rem;'>
                                                    Total: {$totalAppointments}
                                                </span>
                                                <span style='padding: 0.25rem 0.75rem; background: #dcfce7; color: #166534; border-radius: 4px; font-size: 0.875rem;'>
                                                    Completed: {$completedAppointments}
                                                </span>
                                                <span style='padding: 0.25rem 0.75rem; background: #fee2e2; color: #991b1b; border-radius: 4px; font-size: 0.875rem;'>
                                                    Cancelled: {$cancelledAppointments}
                                                </span>
                                            </div>
                                        ");
                                    }),
                            ])
                            ->compact()
                            ->columnSpan(1),

                        // Financial Details
                        Section::make(__('resources.appointment.financial_details'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('subtotal')
                                            ->label(__('resources.appointment.subtotal'))
                                            ->money('SAR')
                                            ->icon('heroicon-o-calculator'),

                                        TextEntry::make('tax_amount')
                                            ->label(__('resources.appointment.tax'))
                                            ->money('SAR')
                                            ->icon('heroicon-o-receipt-percent'),
                                    ]),

                                TextEntry::make('total_amount')
                                    ->label(__('resources.appointment.total'))
                                    ->money('SAR')
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->icon('heroicon-o-currency-dollar'),

                                TextEntry::make('payment_method')
                                    ->label(__('resources.appointment.payment_method'))
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn ($state) => $state ? ucfirst(str_replace('_', ' ', $state)) : __('N/A'))
                                    ->visible(fn ($record) => $record->payment_method),

                                TextEntry::make('payment_summary_text')
                                    ->label(__('resources.appointment.payment_summary'))
                                    ->state(function ($record) {
                                        if ($record->payments->isEmpty()) {
                                            return 'No payments recorded';
                                        }

                                        $totalPaid = $record->payments
                                            ->whereIn('status', [
                                                PaymentStatus::PAID_ONLINE,
                                                PaymentStatus::PAID_ONSTIE_CASH,
                                                PaymentStatus::PAID_ONSTIE_CARD
                                            ])
                                            ->sum('amount');

                                        return $record->payments->count() . ' payment(s) • SAR ' . number_format($totalPaid, 2) . ' paid';
                                    })
                                    ->icon('heroicon-o-credit-card'),
                            ])
                            ->compact()
                            ->columnSpan(1),
                    ]),

                // Services Section
                Section::make(__('resources.appointment.services'))
                    ->icon('heroicon-o-scissors')
                    ->schema([
                        TextEntry::make('services_count')
                            ->label(__('resources.appointment.total_services'))
                            ->state(fn ($record) => $record->services_record->count())
                            ->badge()
                            ->color('primary')
                            ->icon('heroicon-o-shopping-bag'),

                        TextEntry::make('services_list')
                            ->label('')
                            ->state(function ($record) {
                                if ($record->services_record->isEmpty()) {
                                    return new HtmlString('<p style="color: #94a3b8; font-style: italic;">No services</p>');
                                }

                                $servicesHtml = $record->services_record->sortBy('sequence_order')->map(function ($appointmentService, $index) {
                                    $serviceName = $appointmentService->service_name;
                                    $duration = $appointmentService->duration_minutes;
                                    $price = number_format($appointmentService->price, 2);

                                    $durationFormatted = floor($duration / 60) > 0
                                        ? floor($duration / 60) . "h " . ($duration % 60) . "m"
                                        : "{$duration}m";

                                    $sequenceNumber = $appointmentService->sequence_order ?? ($index + 1);

                                    return "
                                        <div style='display: flex; align-items: center; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 0.5rem;'>
                                            <div style='display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 50%; color: white; font-weight: 700; font-size: 0.875rem; margin-right: 0.75rem; flex-shrink: 0;'>
                                                {$sequenceNumber}
                                            </div>
                                            <div style='flex: 1;'>
                                                <div style='font-weight: 600; color: #1e293b; font-size: 0.95rem;'>
                                                    {$serviceName}
                                                </div>
                                                <div style='font-size: 0.8rem; color: #64748b; margin-top: 0.125rem;'>
                                                    {$durationFormatted}
                                                </div>
                                            </div>
                                            <div style='font-weight: 700; color: #22c55e; font-size: 1rem;'>
                                                SAR {$price}
                                            </div>
                                        </div>
                                    ";
                                })->join('');

                                return new HtmlString($servicesHtml);
                            })
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->collapsible()
                    ->collapsed(false),

                // Invoice Section
                Section::make(__('resources.appointment.invoice'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('invoice.invoice_number')
                                    ->label(__('resources.invoice.number'))
                                    ->icon('heroicon-o-hashtag')
                                    ->copyable(),

                                TextEntry::make('invoice.status')
                                    ->label(__('resources.invoice.status'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),

                                TextEntry::make('invoice.created_at')
                                    ->label(__('resources.invoice.created_at'))
                                    ->dateTime('d M Y, H:i')
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('invoice.total_amount')
                                    ->label(__('resources.invoice.total'))
                                    ->money('SAR')
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),
                            ]),
                    ])
                    ->compact()
                    ->visible(fn ($record) => $record->invoice !== null)
                    ->collapsible()
                    ->collapsed(true),

                // Additional Information
                Section::make(__('resources.appointment.additional_info'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        TextEntry::make('notes')
                            ->label(__('resources.appointment.notes'))
                            ->default(__('No notes'))
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.appointment.created_at'))
                                    ->dateTime('d M Y, H:i')
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('created_status')
                                    ->label(__('resources.appointment.created_via'))
                                    ->badge()
                                    ->color('info')
                                    ->visible(fn ($record) => $record->created_status !== null),
                            ]),
                    ])
                    ->compact()
                    ->collapsible()
                    ->collapsed(true),
            ]);
    }
}
