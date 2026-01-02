<?php

namespace App\Filament\Resources\Providers\Schemas;

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

class ProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section with Profile Image and Main Info
                Section::make(__('resources.provider_resource.personal_information'))
                    ->description(__('resources.provider_resource.view_complete_profile'))
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('profile_image_url')
                                    ->label(__('resources.provider_resource.profile_image'))
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
                                            ->label(__('resources.provider_resource.full_name'))
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->color('primary')
                                            ->icon('heroicon-o-user')
                                            ->columnSpanFull(),

                                        TextEntry::make('email')
                                            ->label(__('resources.provider_resource.email'))
                                            ->icon('heroicon-o-envelope')
                                            ->copyable()
                                            ->copyMessage(__('resources.provider_resource.email') . ' copied!')
                                            ->copyMessageDuration(1500),

                                        TextEntry::make('phone')
                                            ->label(__('resources.provider_resource.phone'))
                                            ->icon('heroicon-o-phone')
                                            ->copyable()
                                            ->copyMessage(__('resources.provider_resource.phone_copied'))
                                            ->copyMessageDuration(1500),

                                        IconEntry::make('is_active')
                                            ->label(__('resources.provider_resource.status'))
                                            ->boolean()
                                            ->trueIcon('heroicon-o-check-circle')
                                            ->falseIcon('heroicon-o-x-circle')
                                            ->trueColor('success')
                                            ->falseColor('danger'),

                                        IconEntry::make('email_verified_at')
                                            ->label(__('resources.provider_resource.email_verified'))
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

                // Grid for Contact & Location Info
                Grid::make(2)
                    ->schema([
                        Section::make(__('resources.provider_resource.contact_information'))
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                TextEntry::make('address')
                                    ->label(__('resources.provider_resource.address'))
                                    ->icon('heroicon-o-home')
                                    ->placeholder(__('resources.provider_resource.not_provided'))
                                    ->columnSpanFull(),

                                TextEntry::make('city')
                                    ->label(__('resources.provider_resource.city'))
                                    ->icon('heroicon-o-building-office-2')
                                    ->placeholder(__('resources.provider_resource.not_provided')),

                                TextEntry::make('locale')
                                    ->label(__('resources.provider_resource.language'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'ar' => __('resources.provider_resource.arabic'),
                                        'en' => __('resources.provider_resource.english'),
                                        'de' => __('resources.provider_resource.german'),
                                        default => $state ?? __('resources.provider_resource.english'),
                                    })
                                    ->icon('heroicon-o-language')
                                    ->color('info'),

                                TextEntry::make('branch.name')
                                    ->label(__('resources.provider_resource.branch'))
                                    ->icon('heroicon-o-building-storefront')
                                    ->placeholder(__('resources.provider_resource.no_branch'))
                                    ->weight(FontWeight::SemiBold)
                                    ->color('primary')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->columnSpan(1),

                        Section::make(__('resources.provider_resource.account_settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.provider_resource.joined_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar')
                                    ->since()
                                    ->dateTimeTooltip(),

                                TextEntry::make('updated_at')
                                    ->label(__('resources.provider_resource.last_updated'))
                                    ->dateTime()
                                    ->icon('heroicon-o-clock')
                                    ->since()
                                    ->dateTimeTooltip(),

                                TextEntry::make('email_verified_at')
                                    ->label(__('resources.provider_resource.verified_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-check-badge')
                                    ->placeholder(__('resources.provider_resource.not_verified'))
                                    ->since()
                                    ->dateTimeTooltip()
                                    ->visible(fn ($record) => $record->email_verified_at !== null),
                            ])
                            ->collapsible()
                            ->columnSpan(1),
                    ]),

                // Provider Statistics
                Section::make(__('resources.provider_resource.provider_statistics'))
                    ->description(__('resources.provider_resource.provider_stats'))
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('services_count')
                                    ->label(__('resources.provider_resource.services_count'))
                                    ->icon('heroicon-o-wrench-screwdriver')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->getStateUsing(fn ($record) => $record->services()->count()),

                                TextEntry::make('completed_bookings')
                                    ->label(__('resources.provider_resource.completed_bookings'))
                                    ->icon('heroicon-o-check-circle')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->getStateUsing(fn ($record) => $record->appointmentsFinshedAsProvider()->count()),

                                TextEntry::make('average_rating')
                                    ->label(__('resources.provider_resource.average_rating'))
                                    ->icon('heroicon-o-star')
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('warning')
                                    ->getStateUsing(fn ($record) =>
                                        $record->serviceReviews()->avg('rating')
                                            ? number_format($record->serviceReviews()->avg('rating'), 1) . ' ★'
                                            : __('resources.provider_resource.no_reviews_yet')
                                    ),

                                TextEntry::make('total_earnings')
                                    ->label(__('resources.provider_resource.total_earnings'))
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

                                        return number_format($total, 2) . ' ' . __('resources.provider_resource.sar_currency');
                                    }),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('current_month_earnings')
                                    ->label(__('resources.provider_resource.current_month_earnings'))
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

                                        return number_format($total, 2) . ' ' . __('resources.provider_resource.sar_currency');
                                    }),

                                TextEntry::make('total_appointments')
                                    ->label(__('resources.provider_resource.total_appointments'))
                                    ->icon('heroicon-o-calendar-days')
                                    ->badge()
                                    ->color('info')
                                    ->getStateUsing(fn ($record) => $record->appointmentsAsProvider()->count()),

                                TextEntry::make('upcoming_appointments')
                                    ->label(__('resources.provider_resource.upcoming_appointments'))
                                    ->icon('heroicon-o-arrow-trending-up')
                                    ->badge()
                                    ->color('primary')
                                    ->getStateUsing(fn ($record) =>
                                        $record->appointmentsAsProvider()
                                            ->where('start_time', '>', now())
                                            ->count()
                                    ),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(false),

                // Additional Notes
                Section::make(__('resources.provider_resource.additional_information'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('notes')
                            ->label(__('resources.provider_resource.notes'))
                            ->placeholder(__('resources.provider_resource.no_notes_available'))
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->notes)),
            ]);
    }
}
