<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Support\Enums\FontWeight;
use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use Illuminate\Support\Facades\DB;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section with Profile Image and Main Info
                Section::make(__('resources.user.personal_information'))
                    ->description('View complete user profile information')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('profile_image_url')
                                    ->label(__('resources.user.profile_image'))
                                    ->circular()
                                    ->defaultImageUrl(fn ($record) =>
                                        'https://ui-avatars.com/api/?name=' . urlencode($record->full_name) . '&color=7F9CF5&background=EBF4FF&size=200'
                                    )
                                    ->openUrlInNewTab()
                                        ->url(fn ($record) => $record->profile_image_url ?? null)

                                    ->alignCenter()
                                    ->columnSpan(1),

                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('full_name')
                                            ->label(__('resources.user.full_name'))
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->color('primary')
                                            ->icon('heroicon-o-user')
                                            ->columnSpanFull(),

                                        TextEntry::make('roles.name')
                                            ->label(__('resources.user.role'))
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => __('resources.user.' . $state))
                                            ->color(fn (string $state): string => match ($state) {
                                                'admin' => 'danger',
                                                'manager' => 'warning',
                                                'provider' => 'success',
                                                'customer' => 'info',
                                                default => 'gray',
                                            })
                                            ->icon(fn (string $state): string => match ($state) {
                                                'admin' => 'heroicon-o-shield-check',
                                                'manager' => 'heroicon-o-user-group',
                                                'provider' => 'heroicon-o-scissors',
                                                'customer' => 'heroicon-o-user',
                                                default => 'heroicon-o-user-circle',
                                            }),

                                        TextEntry::make('email')
                                            ->label(__('resources.user.email'))
                                            ->icon('heroicon-o-envelope')
                                            ->copyable()
                                            ->copyMessage(__('resources.user.email') . ' copied!')
                                            ->copyMessageDuration(1500),

                                        TextEntry::make('phone')
                                            ->label(__('resources.user.phone'))
                                            ->icon('heroicon-o-phone')
                                            ->copyable()
                                            ->copyMessage(__('resources.user.phone_copied'))
                                            ->copyMessageDuration(1500),

                                        IconEntry::make('is_active')
                                            ->label(__('resources.user.status'))
                                            ->boolean()
                                            ->trueIcon('heroicon-o-check-circle')
                                            ->falseIcon('heroicon-o-x-circle')
                                            ->trueColor('success')
                                            ->falseColor('danger'),

                                        IconEntry::make('email_verified_at')
                                            ->label(__('resources.user.email_verified'))
                                            ->boolean()
                                            ->trueIcon('heroicon-o-check-badge')
                                            ->falseIcon('heroicon-o-exclamation-triangle')
                                            ->trueColor('success')
                                            ->falseColor('warning')
                                            ->getStateUsing(fn ($record) => $record->email_verified_at !== null),
                                    ])
                                    ->columnSpan(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                // Grid لتنظيم المعلومات جنبا إلى جنب
                Grid::make(2)
                    ->schema([
                        // Contact & Location Information
                        Section::make(__('resources.user.contact_information'))
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                TextEntry::make('address')
                                    ->label(__('resources.user.address'))
                                    ->icon('heroicon-o-home')
                                    ->placeholder(__('resources.user.not_provided'))
                                    ->columnSpanFull(),

                                TextEntry::make('city')
                                    ->label(__('resources.user.city'))
                                    ->icon('heroicon-o-building-office-2')
                                    ->placeholder(__('resources.user.not_provided')),

                                TextEntry::make('locale')
                                    ->label(__('resources.user.language'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'ar' => __('resources.user.arabic'),
                                        'en' => __('resources.user.english'),
                                        'de' => __('resources.user.german'),
                                        default => $state ?? __('resources.user.english'),
                                    })
                                    ->icon('heroicon-o-language')
                                    ->color('info'),

                                TextEntry::make('branch.name')
                                    ->label(__('resources.user.branch'))
                                    ->icon('heroicon-o-building-storefront')
                                    ->placeholder(__('resources.user.no_branch'))
                                    ->weight(FontWeight::SemiBold)
                                    ->color('primary')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->columnSpan(1),

                        // Account Information
                        Section::make(__('resources.user.account_settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.user.joined_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar')
                                    ->since()
                                    ->dateTimeTooltip(),

                                TextEntry::make('updated_at')
                                    ->label(__('resources.user.last_updated'))
                                    ->dateTime()
                                    ->icon('heroicon-o-clock')
                                    ->since()
                                    ->dateTimeTooltip(),

                                TextEntry::make('email_verified_at')
                                    ->label(__('resources.user.verified_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-check-badge')
                                    ->placeholder(__('resources.user.not_verified'))
                                    ->since()
                                    ->dateTimeTooltip()
                                    ->visible(fn ($record) => $record->email_verified_at !== null),

                                TextEntry::make('google_id')
                                    ->label('Google ID')
                                    ->icon('heroicon-o-globe-alt')
                                    ->placeholder(__('resources.user.not_connected'))
                                    ->visible(fn ($record) => $record->google_id !== null)
                                    ->badge()
                                    ->color('success'),
                            ])
                            ->collapsible()
                            ->columnSpan(1),
                    ]),

                // Provider Specific Information (for providers only)
                Section::make(__('resources.user.provider_information'))
                    ->description(__('resources.user.provider_stats'))
                    ->icon('heroicon-o-scissors')
                    ->schema([
                        // Main Provider Statistics
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('services_count')
                                    ->label(__('resources.user.services_count'))
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->getStateUsing(fn ($record) => $record->services()->count()),

                                TextEntry::make('completed_bookings')
                                    ->label(__('resources.user.completed_bookings'))
                                    ->icon('heroicon-o-check-circle')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->getStateUsing(fn ($record) => $record->appointmentsFinshedAsProvider()->count()),

                                TextEntry::make('average_rating')
                                    ->label(__('resources.user.average_rating'))
                                    ->icon('heroicon-o-star')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('warning')
                                    ->getStateUsing(fn ($record) =>
                                        $record->serviceReviews()->avg('rating')
                                            ? number_format($record->serviceReviews()->avg('rating'), 1) . ' ★'
                                            : __('resources.user.no_reviews_yet')
                                    ),

                                TextEntry::make('total_earnings')
                                    ->label(__('resources.user.total_earnings'))
                                    ->icon('heroicon-o-banknotes')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->getStateUsing(function ($record) {

                                        $total = DB::table('payments')
                                            ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
                                            ->where('payments.paymentable_type', 'App\\Models\\Appointment')
                                            ->where('appointments.provider_id', $record->id)
                                            ->whereIn('payments.status', [
                                                PaymentStatus::PAID_ONLINE,
                                                PaymentStatus::PAID_ONSTIE_CASH,
                                                PaymentStatus::PAID_ONSTIE_CARD,
                                            ])
                                            ->sum('payments.amount');

                                        return number_format($total, 2) . ' ' . __('resources.user.sar_currency');
                                    }),
                            ]),

                        // Earnings & Reviews في Grid منفصل
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('current_month_earnings')
                                    ->label(__('resources.user.current_month_earnings'))
                                    ->icon('heroicon-o-calendar')
                                    ->badge()
                                    ->color('success')
                                    ->getStateUsing(function ($record) {
                                        $total = DB::table('payments')
                                            ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
                                            ->where('payments.paymentable_type', 'App\\Models\\Appointment')
                                            ->where('appointments.provider_id', $record->id)
                                            ->whereIn('payments.status', [
                                                PaymentStatus::PAID_ONLINE,
                                                PaymentStatus::PAID_ONSTIE_CASH,
                                                PaymentStatus::PAID_ONSTIE_CARD,
                                            ])
                                            ->whereMonth('payments.created_at', now()->month)
                                            ->whereYear('payments.created_at', now()->year)
                                            ->sum('payments.amount');

                                        return number_format($total, 2) . ' ' . __('resources.user.sar_currency');
                                    }),

                                TextEntry::make('last_month_earnings')
                                    ->label(__('resources.user.last_month_earnings'))
                                    ->icon('heroicon-o-calendar-days')
                                    ->badge()
                                    ->color('info')
                                    ->getStateUsing(function ($record) {
                                        $lastMonth = now()->subMonth();
                                        $total = DB::table('payments')
                                            ->join('appointments', 'payments.paymentable_id', '=', 'appointments.id')
                                            ->where('payments.paymentable_type', 'App\\Models\\Appointment')
                                            ->where('appointments.provider_id', $record->id)
                                            ->whereIn('payments.status', [
                                                PaymentStatus::PAID_ONLINE,
                                                PaymentStatus::PAID_ONSTIE_CASH,
                                                PaymentStatus::PAID_ONSTIE_CARD,
                                            ])
                                            ->whereMonth('payments.created_at', $lastMonth->month)
                                            ->whereYear('payments.created_at', $lastMonth->year)
                                            ->sum('payments.amount');

                                        return number_format($total, 2) . ' ' . __('resources.user.sar_currency');
                                    }),

                                TextEntry::make('total_reviews')
                                    ->label(__('resources.user.total_reviews'))
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->badge()
                                    ->color('warning')
                                    ->getStateUsing(fn ($record) => $record->serviceReviews()->count()),
                            ]),

                        // Appointments Performance Metrics
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('total_appointments')
                                    ->label(__('resources.user.total_appointments'))
                                    ->icon('heroicon-o-calendar-days')
                                    ->badge()
                                    ->color('info')
                                    ->getStateUsing(fn ($record) => $record->appointmentsAsProvider()->count()),

                                TextEntry::make('pending_appointments')
                                    ->label(__('resources.user.pending_appointments'))
                                    ->icon('heroicon-o-clock')
                                    ->badge()
                                    ->color('warning')
                                    ->getStateUsing(fn ($record) =>
                                        $record->appointmentsAsProvider()
                                            ->where('status', AppointmentStatus::PENDING)
                                            ->count()
                                    ),

                                TextEntry::make('upcoming_appointments')
                                    ->label(__('resources.user.upcoming_appointments'))
                                    ->icon('heroicon-o-arrow-trending-up')
                                    ->badge()
                                    ->color('primary')
                                    ->getStateUsing(fn ($record) =>
                                        $record->appointmentsAsProvider()
                                            ->where('start_time', '>', now())
                                            ->count()
                                    ),
                            ]),

                        // Services & Schedule في Grid 2 columns
                        Grid::make(2)
                            ->schema([
                                // Services List
                                TextEntry::make('services_list')
                                    ->label(__('resources.user.services_list'))
                                    ->icon('heroicon-o-list-bullet')
                                    ->badge()
                                    ->separator(',')
                                    ->getStateUsing(function ($record) {
                                        $services = $record->services()
                                            ->pluck('services.name')
                                            ->toArray();

                                        return $services ?: [__('resources.user.no_services')];
                                    })
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->columnSpan(1),

                                // Work Schedule
                                TextEntry::make('work_schedule')
                                    ->label(__('resources.user.work_schedule'))
                                    ->icon('heroicon-o-clock')
                                    ->getStateUsing(function ($record) {
                                        $schedule = $record->scheduledWorks()
                                            ->where('is_active', true)
                                            ->orderBy('day_of_week')
                                            ->get();

                                        if ($schedule->isEmpty()) {
                                            return __('resources.user.no_schedule');
                                        }

                                        $days = [
                                            0 => __('resources.user.sunday'),
                                            1 => __('resources.user.monday'),
                                            2 => __('resources.user.tuesday'),
                                            3 => __('resources.user.wednesday'),
                                            4 => __('resources.user.thursday'),
                                            5 => __('resources.user.friday'),
                                            6 => __('resources.user.saturday'),
                                        ];

                                        $scheduleText = [];
                                        foreach ($schedule as $work) {
                                            $day = $days[$work->day_of_week] ?? $work->day_of_week;
                                            if ($work->is_work_day) {
                                                $breakInfo = $work->break_minutes > 0
                                                    ? ' (' . __('resources.user.break_time') . ': ' . $work->break_minutes . ' ' . __('resources.user.minutes') . ')'
                                                    : '';
                                                $scheduleText[] = "**{$day}**: {$work->start_time} - {$work->end_time}{$breakInfo}";
                                            } else {
                                                $scheduleText[] = "**{$day}**: " . __('resources.user.day_off');
                                            }
                                        }

                                        return implode("\n", $scheduleText);
                                    })
                                    ->markdown()
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->hasRole('provider'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(false),

                // Customer Specific Information (for customers only)
                Section::make(__('resources.user.customer_information'))
                    ->description(__('resources.user.customer_stats'))
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        // Main Statistics Cards
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_appointments')
                                    ->label(__('resources.user.total_appointments'))
                                    ->icon('heroicon-o-calendar-days')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->getStateUsing(fn ($record) => $record->customerAppointments()->count()),

                                TextEntry::make('completed_appointments')
                                    ->label(__('resources.user.completed_appointments'))
                                    ->icon('heroicon-o-check-circle')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->getStateUsing(fn ($record) =>
                                        $record->customerAppointments()
                                            ->where('status', AppointmentStatus::COMPLETED)
                                            ->count()
                                    ),

                                TextEntry::make('services_requested')
                                    ->label(__('resources.user.services_requested'))
                                    ->icon('heroicon-o-scissors')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('warning')
                                    ->getStateUsing(fn ($record) =>
                                        DB::table('appointment_services')
                                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                                            ->where('appointments.customer_id', $record->id)
                                            ->count()
                                    ),

                                TextEntry::make('total_paid')
                                    ->label(__('resources.user.total_paid'))
                                    ->icon('heroicon-o-banknotes')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->getStateUsing(fn ($record) =>
                                        number_format($record->invoices()->sum('total_amount'), 2) . ' ' . __('resources.user.sar_currency')
                                    ),
                            ]),

                        // Booking Statistics
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('pending_appointments')
                                    ->label(__('resources.user.pending_appointments'))
                                    ->icon('heroicon-o-clock')
                                    ->badge()
                                    ->color('warning')
                                    ->getStateUsing(fn ($record) =>
                                        $record->customerAppointments()
                                            ->where('status', AppointmentStatus::PENDING)
                                            ->count()
                                    ),

                                TextEntry::make('upcoming_appointments')
                                    ->label(__('resources.user.upcoming_appointments'))
                                    ->icon('heroicon-o-arrow-trending-up')
                                    ->badge()
                                    ->color('info')
                                    ->getStateUsing(fn ($record) =>
                                        $record->customerAppointments()
                                            ->where('start_time', '>', now())
                                            ->count()
                                    ),

                                TextEntry::make('cancelled_appointments')
                                    ->label(__('resources.user.cancelled_appointments'))
                                    ->icon('heroicon-o-x-circle')
                                    ->badge()
                                    ->color('danger')
                                    ->getStateUsing(fn ($record) =>
                                        $record->customerAppointments()
                                            ->whereIn('status', [
                                                AppointmentStatus::USER_CANCELLED,
                                                AppointmentStatus::ADMIN_CANCELLED
                                            ])
                                            ->count()
                                    ),

                                TextEntry::make('average_booking_value')
                                    ->label(__('resources.user.average_booking_value'))
                                    ->icon('heroicon-o-calculator')
                                    ->badge()
                                    ->color('primary')
                                    ->getStateUsing(function ($record) {
                                        $avg = $record->invoices()->avg('total_amount');
                                        return $avg ? number_format($avg, 2) . ' ' . __('resources.user.sar_currency') : '0.00';
                                    }),
                            ]),

                        // Favorite Services & Booking History في Grid 2 columns
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('favorite_services')
                                    ->label(__('resources.user.favorite_services'))
                                    ->icon('heroicon-o-star')
                                    ->badge()
                                    ->separator(',')
                                    ->getStateUsing(function ($record) {
                                        $services = DB::table('appointment_services')
                                            ->join('appointments', 'appointment_services.appointment_id', '=', 'appointments.id')
                                            ->join('services', 'appointment_services.service_id', '=', 'services.id')
                                            ->where('appointments.customer_id', $record->id)
                                            ->select('services.name', DB::raw('COUNT(*) as count'))
                                            ->groupBy('services.id', 'services.name')
                                            ->orderBy('count', 'desc')
                                            ->limit(5)
                                            ->pluck('name')
                                            ->toArray();

                                        return $services ?: [__('resources.user.no_services')];
                                    })
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->columnSpan(1),

                                // Booking History
                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('first_booking')
                                            ->label(__('resources.user.first_booking'))
                                            ->icon('heroicon-o-calendar')
                                            ->dateTime()
                                            ->since()
                                            ->dateTimeTooltip()
                                            ->getStateUsing(fn ($record) =>
                                                $record->customerAppointments()->oldest('created_at')->first()?->created_at
                                            ),

                                        TextEntry::make('last_booking')
                                            ->label(__('resources.user.last_booking'))
                                            ->icon('heroicon-o-calendar-days')
                                            ->dateTime()
                                            ->since()
                                            ->dateTimeTooltip()
                                            ->getStateUsing(fn ($record) =>
                                                $record->customerAppointments()->latest('created_at')->first()?->created_at
                                            ),

                                        TextEntry::make('total_invoices')
                                            ->label(__('resources.user.total_invoices'))
                                            ->icon('heroicon-o-document-text')
                                            ->badge()
                                            ->color('primary')
                                            ->getStateUsing(fn ($record) => $record->invoices()->count()),
                                    ])
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->hasRole('customer'))
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(false),

                // Additional Notes
                Section::make(__('resources.user.additional_information'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('notes')
                            ->label(__('resources.user.notes'))
                            ->placeholder(__('resources.user.no_notes_available'))
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->notes)),
            ]);
    }
}
