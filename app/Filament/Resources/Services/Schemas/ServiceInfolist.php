<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

class ServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Section with Image and Main Info
                Section::make('')
                    ->schema([
                        ImageEntry::make('image_url')
                            ->label(__('resources.service.image'))
                            ->height(200)
                            ->defaultImageUrl(url('/images/placeholder-service.png'))
                            ->extraImgAttributes(['class' => 'rounded-lg object-cover']),

                        TextEntry::make('name')
                            ->label(__('resources.service.name'))
                            ->size('lg')
                            ->weight(FontWeight::Bold)
                            ->color('primary'),

                        TextEntry::make('category.name')
                            ->label(__('resources.service.category'))
                            ->badge()
                            ->color('info'),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('price')
                                    ->label(__('resources.service.price'))
                                    ->money('SAR')
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success'),

                                TextEntry::make('discount_price')
                                    ->label(__('resources.service.discount'))
                                    ->money('SAR')
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('danger')
                                    ->visible(fn ($record) => $record->discount_price !== null),
                            ]),

                        TextEntry::make('duration_minutes')
                            ->label(__('resources.service.duration'))
                            ->formatStateUsing(fn ($state) => floor($state / 60) > 0
                                ? floor($state / 60) . 'h ' . ($state % 60) . 'm'
                                : $state . 'm')
                            ->icon('heroicon-o-clock')
                            ->color('warning'),
                    ])
                    ->columns(1),

                // Service Details Section
                Section::make(__('resources.service.service_details'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label(__('resources.service.status'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                IconEntry::make('is_featured')
                                    ->label(__('resources.service.featured'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-star')
                                    ->falseIcon('heroicon-o-star')
                                    ->trueColor('warning')
                                    ->falseColor('gray'),

                                TextEntry::make('sort_order')
                                    ->label(__('resources.service.sort_order'))
                                    ->badge()
                                    ->color('gray'),
                            ]),

                        TextEntry::make('description')
                            ->label(__('resources.service.description'))
                            ->columnSpanFull()
                            ->markdown()
                            ->default(__('resources.user.not_provided')),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('color_code')
                                    ->label(__('resources.service.color'))
                                    ->formatStateUsing(fn ($state) => $state
                                        ? new HtmlString('<span style="display: inline-block; padding: 4px 12px; border-radius: 6px; background-color: ' . $state . '; color: #fff; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">' . $state . '</span>')
                                        : __('resources.user.not_provided')),

                                ImageEntry::make('icon.path')
                                    ->label(__('resources.service.icon'))
                                    ->disk('public')
                                    ->height(50)
                                    ->defaultImageUrl(url('/images/placeholder-icon.png')),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('resources.service.created_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('updated_at')
                                    ->label(__('resources.service.updated_at'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar')
                                    ->since()
                                    ->color('gray'),
                            ]),
                    ])
                    ->columns(3)
                    ->collapsible(),

                // Grid for Providers and Translations side by side
                Grid::make(2)
                    ->schema([
                        // Service Providers Section
                        Section::make(__('resources.service.providers'))
                            ->description(__('resources.service.assign_providers_desc'))
                            ->icon('heroicon-o-users')
                            ->schema([
                                TextEntry::make('providers_list')
                                    ->label('')
                                    ->state(function ($record) {
                                        if ($record->providers->isEmpty()) {

                                            return new HtmlString('
                                                <div style="text-align: center; padding: 2rem; color: #64748b;">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                                    </svg>
                                                    <p style="margin-top: 0.5rem; font-weight: 500;">' . __('resources.user.no_services_assigned_desc') . '</p>
                                                </div>
                                            ');
                                        }

                                        $providersHtml = $record->providers->map(function ($provider, $index) {
                                            $isActive = $provider->pivot->is_active;
                                            $statusClass = $isActive ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200';
                                            $statusDot = $isActive
                                                ? '<span style="display: inline-block; width: 8px; height: 8px; background: #22c55e; border-radius: 50%; margin-right: 6px;"></span>'
                                                : '<span style="display: inline-block; width: 8px; height: 8px; background: #94a3b8; border-radius: 50%; margin-right: 6px;"></span>';

                                            return '
                                                <div style="display: flex; align-items: center; padding: 0.75rem 1rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s;">
                                                    <div style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; color: white; font-weight: 700; font-size: 1.1rem; margin-right: 1rem; flex-shrink: 0;">
                                                        ' . strtoupper(substr($provider->full_name, 0, 1)) . '
                                                    </div>
                                                    <div style="flex: 1;">
                                                        <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;">
                                                            ' . $statusDot . htmlspecialchars($provider->full_name) . '
                                                        </div>
                                                    </div>
                                                </div>
                                            ';
                                        })->join('');

                                        return new HtmlString('
                                            <div style="max-height: 400px; overflow-y: auto; padding: 0.5rem;">
                                                ' . $providersHtml . '
                                            </div>
                                        ');
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(false)
                            ->columnSpan(1),

                        // Service Translations Section
                        Section::make(__('resources.service.translations'))
                            ->description(__('resources.service.translations_section_desc'))
                            ->icon('heroicon-o-language')
                            ->schema([
                                TextEntry::make('translations_count')
                                    ->label(__('resources.service.translations'))
                                    ->state(fn ($record) => $record->translations->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->icon('heroicon-o-globe-alt')
                                    ->visible(fn ($record) => $record->translations->isNotEmpty()),

                                TextEntry::make('translations_list')
                                    ->label('')
                                    ->state(function ($record) {
                                        if ($record->translations->isEmpty()) {
                                            return __('No translations available');
                                        }

                                        $translationsList = $record->translations->map(function ($translation) {
                                            $langName = $translation->language->name ?? $translation->language_code;
                                            $flag = match($translation->language_code) {
                                                'ar' => '🇸🇦',
                                                'en' => '🇬🇧',
                                                'de' => '🇩🇪',
                                                default => '🌐',
                                            };

                                            return "**{$flag} {$langName}**\n" .
                                                   "   📝 {$translation->name}\n" .
                                                   ($translation->description ? "   ℹ️ " . substr($translation->description, 0, 100) . "..." : "");
                                        })->join("\n\n");

                                        return new HtmlString('<div style="line-height: 1.8;">' . nl2br($translationsList) . '</div>');
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(false)
                            ->columnSpan(1),
                    ]),

                // Booking Statistics Section
                Section::make(__('resources.user.booking_stats'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_bookings_count')
                                    ->label(__('resources.service.total_bookings'))
                                    ->state(fn ($record) => $record->appointmentServices->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->icon('heroicon-o-shopping-bag'),

                                TextEntry::make('completed_bookings_count')
                                    ->label(__('resources.service.completed_bookings'))
                                    ->state(fn ($record) => $record->appointmentServices
                                        ->filter(fn ($as) => $as->appointment?->status === \App\Enum\AppointmentStatus::COMPLETED)
                                        ->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle'),

                                TextEntry::make('pending_bookings_count')
                                    ->label(__('resources.user.pending_bookings'))
                                    ->state(fn ($record) => $record->appointmentServices
                                        ->filter(fn ($as) => $as->appointment
                                            && $as->appointment->status !== \App\Enum\AppointmentStatus::COMPLETED
                                            && $as->appointment->status !== \App\Enum\AppointmentStatus::USER_CANCELLED)
                                        ->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('warning')
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('cancelled_bookings_count')
                                    ->label(__('resources.user.cancelled_appointments'))
                                    ->state(fn ($record) => $record->appointmentServices
                                        ->filter(fn ($as) => $as->appointment?->status === \App\Enum\AppointmentStatus::USER_CANCELLED)
                                        ->count())
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('danger')
                                    ->icon('heroicon-o-x-circle'),
                            ]),
                    ])
                    ->collapsible(),

                // Revenue Statistics Section
                Section::make(__('resources.service.total_revenue'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('service_total_revenue')
                                    ->label(__('resources.service.total_revenue'))
                                    ->state(fn ($record) => 'SAR ' . number_format(
                                        $record->appointmentServices->sum('price'), 2
                                    ))
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary')
                                    ->icon('heroicon-o-currency-dollar'),

                                TextEntry::make('service_completed_revenue')
                                    ->label(__('resources.service.completed_revenue'))
                                    ->state(fn ($record) => 'SAR ' . number_format(
                                        $record->appointmentServices
                                            ->filter(fn ($as) => $as->appointment?->status === \App\Enum\AppointmentStatus::COMPLETED)
                                            ->sum('price'), 2
                                    ))
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('success')
                                    ->icon('heroicon-o-banknotes')
                                    ->helperText(__('resources.service.from_completed_only')),

                                TextEntry::make('service_average_price')
                                    ->label(__('resources.service.average_price'))
                                    ->state(fn ($record) => 'SAR ' . number_format(
                                        $record->appointmentServices
                                            ->filter(fn ($as) => $as->appointment?->status === \App\Enum\AppointmentStatus::COMPLETED)
                                            ->avg('price') ?? 0, 2
                                    ))
                                    ->badge()
                                    ->size('lg')
                                    ->weight(FontWeight::Bold)
                                    ->color('info')
                                    ->icon('heroicon-o-calculator'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('service_last_booking')
                                    ->label(__('resources.service.last_booking_date'))
                                    ->state(function ($record) {
                                        $lastAppointment = $record->appointmentServices
                                            ->sortByDesc('created_at')
                                            ->first();
                                        return $lastAppointment && $lastAppointment->appointment
                                            ? $lastAppointment->appointment->appointment_date->format('Y-m-d H:i')
                                            : __('resources.service.never');
                                    })
                                    ->icon('heroicon-o-calendar-days')
                                    ->color('gray')
                                    ->size('md'),

                                TextEntry::make('service_first_booking')
                                    ->label(__('resources.user.first_booking'))
                                    ->state(function ($record) {
                                        $firstAppointment = $record->appointmentServices
                                            ->sortBy('created_at')
                                            ->first();
                                        return $firstAppointment && $firstAppointment->appointment
                                            ? $firstAppointment->appointment->appointment_date->format('Y-m-d H:i')
                                            : __('resources.service.never');
                                    })
                                    ->icon('heroicon-o-calendar')
                                    ->color('gray')
                                    ->size('md'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }
}
