<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\ProviderTimeOff;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\DB;

class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    // Step 1: Customer Selection (الخطوة الأولى: اختيار الزبون)
                    Wizard\Step::make('customer')
                        ->label(__('resources.appointment.wizard_customer_label'))
                        ->icon('heroicon-o-user')
                        ->description(__('resources.appointment.wizard_customer_desc'))
                        ->schema([
                            Section::make(__('resources.appointment.customer_info'))
                                ->schema([
                                    Select::make('customer_id')
                                        ->label(__('resources.appointment.customer_label'))
                                        ->relationship('customer', 'first_name')
                                        ->searchable(['first_name', 'last_name', 'phone', 'email'])
                                        ->preload()
                                        ->getOptionLabelFromRecordUsing(fn (User $record) =>
                                            "{$record->full_name} - {$record->phone}"
                                        )
                                        ->createOptionForm([
                                            Grid::make(2)
                                                ->schema([
                                                    TextInput::make('first_name')
                                                        ->label(__('resources.user.first_name'))
                                                        ->required()
                                                        ->maxLength(255),
                                                    TextInput::make('last_name')
                                                        ->label(__('resources.user.last_name'))
                                                        ->required()
                                                        ->maxLength(255),
                                                    TextInput::make('phone')
                                                        ->label(__('resources.user.phone'))
                                                        ->tel()
                                                        ->required()
                                                        ->unique('users', 'phone')
                                                        ->maxLength(20),
                                                    TextInput::make('email')
                                                        ->label(__('resources.user.email'))
                                                        ->email()
                                                        ->unique('users', 'email')
                                                        ->maxLength(255),
                                                    TextInput::make('address')
                                                        ->label(__('resources.user.address'))
                                                        ->maxLength(500)
                                                        ->columnSpanFull(),
                                                    TextInput::make('city')
                                                        ->label(__('resources.user.city'))
                                                        ->maxLength(100),
                                                    Select::make('locale')
                                                        ->label(__('resources.user.locale'))
                                                        ->options(function(){
                                                            $languages = \App\Models\Language::where('is_active', true)
                                                                ->orderBy('order')
                                                                ->get();
                                                            $options = [];
                                                            foreach ($languages as $language) {
                                                                $options[$language->code] = "{$language->name} ({$language->native_name})";
                                                            }
                                                            return $options;
                                                        })
                                                        ->default('ar'),
                                                ])
                                        ])
                                        ->createOptionUsing(function (array $data): int {
                                            $customer = User::create([
                                                'first_name' => $data['first_name'],
                                                'last_name' => $data['last_name'],
                                                'phone' => $data['phone'],
                                                'email' => $data['email'] ?? null,
                                                'address' => $data['address'] ?? null,
                                                'city' => $data['city'] ?? null,
                                                'locale' => $data['locale'] ?? 'ar',
                                                'user_type' => 'customer',
                                                'password' => bcrypt($data['phone']),
                                                'is_active' => true,
                                            ]);
                                            $customer->assignRole('customer');
                                            return $customer->id;
                                        })
                                        ->createOptionAction(fn (Action $action) =>
                                            $action
                                                ->modalHeading(__('resources.appointment.add_new_customer'))
                                                ->modalButton(__('resources.appointment.add_customer'))
                                                ->modalWidth('2xl')
                                        )
                                        ->required()
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    // Step 2: Service + Schedule & Timeline (الخطوة الثانية: الخدمة + التاريخ والجدول الزمني)
                    Wizard\Step::make('service_schedule')
                        ->label(__('resources.appointment.wizard_services_label'))
                        ->icon('heroicon-o-scissors')
                        ->description(__('resources.appointment.select_service_then_time'))
                        ->schema([
                            // Service Selection Section
                            Section::make(__('resources.appointment.services_section'))
                                ->description(__('resources.appointment.services_section_desc'))
                                ->schema([
                                    Repeater::make('services_record')
                                        ->label(__('resources.appointment.services'))
                                        ->relationship()
                                        ->schema([
                                            Grid::make(3)
                                                ->schema([
                                                    Select::make('service_id')
                                                        ->label(__('resources.appointment.service_label'))
                                                        ->options(Service::active()->pluck('name', 'id'))
                                                        ->searchable()
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                            if ($state) {
                                                                $service = Service::find($state);
                                                                if ($service) {
                                                                    $set('service_name', $service->name);
                                                                    $set('duration_minutes', $service->duration_minutes);
                                                                    $set('price', $service->display_price);
                                                                }
                                                            }
                                                            // Reset provider and time when service changes
                                                            $set('../../provider_id', null);
                                                            $set('../../start_time', null);
                                                        })
                                                        ->columnSpan(2),

                                                    TextInput::make('duration_minutes')
                                                        ->label(__('resources.appointment.duration_label'))
                                                        ->numeric()
                                                        ->required()
                                                        ->suffix(__('resources.appointment.duration_suffix'))
                                                        ->live()
                                                        ->helperText(__('resources.appointment.duration_helper'))
                                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                                            // Reset time when duration changes
                                                            $set('../../provider_id', null);
                                                            $set('../../start_time', null);
                                                        })
                                                        ->columnSpan(1),

                                                    TextInput::make('price')
                                                        ->label(__('resources.appointment.price_label'))
                                                        ->numeric()
                                                        ->required()
                                                        ->prefix(get_setting('currency_symbol', '$'))
                                                        ->live()
                                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                                            self::calculateTotals($get, $set);
                                                        })
                                                        ->columnSpan(3),

                                                    TextInput::make('service_name')
                                                        ->hidden()
                                                        ->dehydrated(),

                                                    TextInput::make('sequence_order')
                                                        ->hidden()
                                                        ->default(fn ($get) => count($get('../../services_record') ?? []) + 1)
                                                        ->dehydrated(),
                                                ]),
                                        ])
                                        ->defaultItems(1)
                                        ->addActionLabel(__('resources.appointment.add_service'))
                                        ->reorderable()
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string =>
                                            Service::find($state['service_id'])?->name ?? __('resources.appointment.new_service')
                                        )
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            self::calculateTotals($get, $set);
                                            // Reset provider and time when services change
                                            $set('provider_id', null);
                                            $set('start_time', null);
                                        })
                                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                            // تأكد من وجود service_name
                                            if (empty($data['service_name']) && !empty($data['service_id'])) {
                                                $service = Service::find($data['service_id']);
                                                if ($service) {
                                                    $data['service_name'] = $service->name;
                                                }
                                            }

                                            // تأكد من وجود sequence_order
                                            if (empty($data['sequence_order'])) {
                                                $data['sequence_order'] = 1;
                                            }

                                            return $data;
                                        })
                                        ->columnSpanFull()
                                        ->minItems(1),
                                ])
                                ->collapsible(),

                            // Date and Timeline Section
                            Section::make(__('resources.appointment.date_selection'))
                                ->description(__('resources.appointment.select_date_to_view_timeline'))
                                ->schema([
                                    DatePicker::make('appointment_date')
                                        ->label(__('resources.appointment.appointment_date_label'))
                                        ->native(false)
                                        ->displayFormat('Y-m-d')
                                        ->minDate(now()->format('Y-m-d'))
                                        ->default(fn () => Carbon::now())
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            // Reset selections when date changes
                                            $set('provider_id', null);
                                            $set('start_time', null);
                                        })
                                        ->columnSpanFull(),
                                ]),

                            // Timeline View Component
                            ViewField::make('timeline_view')
                                ->label(__('resources.appointment.providers_availability'))
                                ->view('filament.forms.components.appointment-timeline')
                                ->viewData(fn (Get $get) => [
                                    'date' => $get('appointment_date'),
                                    'services' => $get('services_record') ?? [],
                                    'selectedProvider' => $get('provider_id'),
                                    'selectedTime' => $get('start_time'),
                                    'serviceDuration' => collect($get('services_record') ?? [])->sum('duration_minutes') ?: 30,
                                ])
                                ->visible(fn (Get $get) => !empty($get('appointment_date')) && !empty($get('services_record')))
                                ->columnSpanFull()
                                ->live()
                                ->dehydrated(false),

                            Section::make(__('resources.appointment.selected_slot'))
                                ->description(__('resources.appointment.select_appointment_details'))
                                ->schema([
                                    Grid::make(4)
                                        ->schema([
                                            Select::make('provider_id')
                                                ->label(__('resources.appointment.main_provider'))
                                                ->options(function (Get $get) {
                                                    $services = $get('services_record') ?? [];
                                                    if (empty($services)) {
                                                        return [];
                                                    }

                                                    // Get service IDs
                                                    $serviceIds = collect($services)
                                                        ->pluck('service_id')
                                                        ->filter()
                                                        ->unique()
                                                        ->toArray();

                                                    if (empty($serviceIds)) {
                                                        return [];
                                                    }

                                                    // Get providers who offer ALL selected services
                                                    return User::role('provider')
                                                        ->where('is_active', true)
                                                        ->whereHas('services', function($query) use ($serviceIds) {
                                                            $query->whereIn('services.id', $serviceIds);
                                                        })
                                                        ->get()
                                                        ->pluck('full_name', 'id')
                                                        ->toArray();
                                                })
                                                ->searchable()
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                                    // Recalculate end time when provider changes
                                                    if ($state && $get('start_time')) {
                                                        self::updateEndTime($get, $set);
                                                    }
                                                })
                                                ->columnSpan(1),

                                            TimePicker::make('start_time')
                                                ->label(__('resources.appointment.start_time_label'))
                                                ->seconds(false)
                                                ->minutesStep(10)
                                                ->required()
                                                ->live()
                                                ->afterStateUpdated(function (Get $get, Set $set) {
                                                    self::updateEndTime($get, $set);
                                                })
                                                ->columnSpan(1),

                                            TextInput::make('duration_minutes')
                                                ->label(__('resources.appointment.duration_label'))
                                                ->numeric()
                                                ->required()
                                                ->suffix(__('resources.appointment.duration_suffix'))
                                                ->live()
                                                ->afterStateUpdated(function (Get $get, Set $set) {
                                                    // Reset time selection when duration changes
                                                    $set('start_time', null);
                                                    // Recalculate end time if start time exists
                                                    if ($get('start_time') && $get('appointment_date')) {
                                                        self::updateEndTime($get, $set);
                                                    }
                                                })
                                                ->helperText(__('resources.appointment.edit_total_duration_helper'))
                                                ->columnSpan(1),

                                            ViewField::make('calculated_duration_display')
                                                ->label(__('resources.appointment.auto_calculated'))
                                                ->view('filament.forms.components.duration-display')
                                                ->viewData(fn (Get $get) => [
                                                    'duration' => collect($get('services_record') ?? [])->sum('duration_minutes') ?: 0,
                                                    'suffix' => __('resources.appointment.duration_suffix'),
                                                ])
                                                ->dehydrated(false)
                                                ->columnSpan(1),
                                        ]),

                                    // Hidden fields
                                    DateTimePicker::make('end_time')
                                        ->hidden()
                                        ->dehydrated(),
                                ])
                                ->visible(fn (Get $get) => !empty($get('appointment_date'))),

                            // Hidden fields for totals
                            TextInput::make('subtotal')
                                ->numeric()
                                ->default(0)
                                ->hidden()
                                ->dehydrated(),

                            TextInput::make('tax_amount')
                                ->numeric()
                                ->default(0)
                                ->hidden()
                                ->dehydrated(),

                            TextInput::make('total_amount')
                                ->numeric()
                                ->default(0)
                                ->hidden()
                                ->dehydrated(),
                        ]),

                    // Step 3: Payment & Notes (الخطوة الثالثة: الدفع والملاحظات)
                    Wizard\Step::make('payment')
                        ->label(__('resources.appointment.wizard_payment_label'))
                        ->icon('heroicon-o-credit-card')
                        ->description(__('resources.appointment.wizard_payment_desc'))
                        ->schema([
                            Section::make(__('resources.appointment.cost_summary'))
                                ->schema([
                                    Grid::make(3)
                                        ->schema([


    ViewField::make('subtotal_display')
    ->label(__('resources.appointment.subtotal_label') . ' (' . get_setting('tax_rate', 0) . '%)')

    ->view('filament.fields.cost-value',function(Get $get){
        return [
        'label'=>__('resources.appointment.subtotal_label') . ' (' . get_setting('tax_rate', 0) . '%)',
        'value' => $get('subtotal') ?? 0,
        'currency' => get_setting('currency_symbol', '$'),
        'size' => '1.2em',
        'color' => null,
    ];}),
    ViewField::make('tax_display')
    ->label(__('resources.appointment.tax_label') . ' (' . get_setting('tax_rate', 0) . '%)')
    ->view('filament.fields.cost-value',fn(Get $get) => [
        'label'=>__('resources.appointment.tax_label') . ' (' . get_setting('tax_rate', 0) . '%)',
        'value' => $get('tax_amount') ?? 0,
        'currency' => get_setting('currency_symbol', '$'),
        'size' => '1.2em',
        'color' => null,
    ]),

ViewField::make('total_display')
    ->label(__('resources.appointment.total_label'))
    ->view('filament.fields.cost-value',fn(Get $get) => [
        'label'=>__('resources.appointment.total_label'),
        'value' => $get('total_amount') ?? 0,
        'currency' => get_setting('currency_symbol', '$'),
        'size' => '1.5em',
        'color' => '#10b981',
    ]),

                                        ]),
                                ]),

                            Section::make(__('resources.appointment.payment_details'))
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            Select::make('payment_method')
                                                ->label(__('resources.appointment.payment_method_label'))
                                                ->options([
                                                    'cash' => __('resources.appointment.payment_method_cash'),
                                                    'card' => __('resources.appointment.payment_method_card'),
                                                    'online' => __('resources.appointment.payment_method_online'),
                                                ])
                                                ->default('cash')
                                                ->required(),

                                            Select::make('payment_status')
                                                ->label(__('resources.appointment.payment_status_label'))
                                                ->options([
                                                    PaymentStatus::PENDING->value => PaymentStatus::PENDING->label(),
                                                    PaymentStatus::PAID_ONLINE->value => PaymentStatus::PAID_ONLINE->label(),
                                                    PaymentStatus::PAID_ONSTIE_CASH->value => PaymentStatus::PAID_ONSTIE_CASH->label(),
                                                    PaymentStatus::PAID_ONSTIE_CARD->value => PaymentStatus::PAID_ONSTIE_CARD->label(),
                                                ])
                                                ->default(PaymentStatus::PENDING->value)
                                                ->required(),

                                            Select::make('status')
                                                ->label(__('resources.appointment.appointment_status_label'))
                                                ->options([
                                                    AppointmentStatus::PENDING->value => AppointmentStatus::PENDING->getLabel(),
                                                    AppointmentStatus::COMPLETED->value => AppointmentStatus::COMPLETED->getLabel(),
                                                ])
                                                ->default(AppointmentStatus::PENDING->value)
                                                ->required(),

                                            Toggle::make('created_status')
                                                ->label(__('resources.appointment.confirmed'))
                                                ->default(true)
                                                ->inline(false),
                                        ]),

                                    Textarea::make('notes')
                                        ->label(__('resources.appointment.notes_label'))
                                        ->rows(3)
                                        ->placeholder(__('resources.appointment.notes_placeholder'))
                                        ->columnSpanFull(),

                                    // Hidden appointment number
                                    TextInput::make('number')
                                        ->default(fn () => self::generateAppointmentNumber())
                                        ->hidden()
                                        ->dehydrated(),
                                ]),
                        ]),
                ])
                ->columnSpanFull()
                ->persistStepInQueryString()
                ->submitAction(new HtmlString('<button type="submit" class="filament-button filament-button-size-md inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">
                    <span>' . __('resources.appointment.create_appointment') . '</span>
                </button>')),
            ]);
    }

    /**
     * Update end time based on start time and total duration
     */
    protected static function updateEndTime(Get $get, Set $set): void
    {
        $startTime = $get('start_time');
        $appointmentDate = $get('appointment_date');
        $services = $get('services_record') ?? [];

        if (!$startTime || !$appointmentDate || empty($services)) {
            return;
        }

        // Calculate total duration from all services
        $totalDuration = collect($services)->sum('duration_minutes') ?? 0;

        // Set the duration_minutes field
        $set('duration_minutes', $totalDuration);

        // Parse start time and add duration
        try {
            $startDateTime = Carbon::parse($appointmentDate . ' ' . $startTime);
            $endDateTime = $startDateTime->copy()->addMinutes($totalDuration);

            // Set end_time field
            $set('end_time', $endDateTime->format('Y-m-d H:i:s'));

            // Update calculated duration display
            $set('calculated_duration', $totalDuration);
        } catch (\Exception $e) {
            // Handle parsing errors silently
        }
    }

    protected static function calculateTotals(Get $get, Set $set): void
    {
        $services = collect($get('services_record') ?? []);

        $subtotal = $services->sum('price');
        $taxRate = (float) get_setting('tax_rate', 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;

        $set('subtotal', round($subtotal, 2));
        $set('tax_amount', round($taxAmount, 2));
        $set('total_amount', round($totalAmount, 2));

        // Calculate total duration
        $totalDuration = $services->sum('duration_minutes') ?: 0;
        $set('calculated_duration', $totalDuration);
        $set('duration_minutes', $totalDuration);

        // Update end time if start time exists
        if ($get('start_time') && $get('appointment_date')) {
            self::updateEndTime($get, $set);
        }
    }

    protected static function generateAppointmentNumber(): string
    {
        $prefix = 'APT';
        $date = Carbon::now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get timeline data for all providers on a specific date
     */
    public static function getProvidersTimeline(string $date, array $services): array
    {
        if (empty($date) || empty($services)) {
            return [];
        }

        // Ensure we only have the date part
        $bookingDate = Carbon::parse($date);
        $dateOnly = $bookingDate->format('Y-m-d');
        $dayOfWeek = $bookingDate->dayOfWeek;

        // Get total duration from services
        $totalDuration = collect($services)->sum('duration_minutes') ?? 30;

        // Extract service IDs from the services array
        $serviceIds = collect($services)
            ->pluck('service_id')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($serviceIds)) {
            return [];
        }

        // Get only providers who offer ALL selected services
        $providers = User::role('provider')
            ->where('is_active', true)
            ->whereHas('services', function($query) use ($serviceIds) {
                $query->whereIn('services.id', $serviceIds);
            })
            ->get();

        $providersData = [];

        foreach ($providers as $provider) {
            // Check salon schedule for provider's branch
            $salonSchedule = null;
            if ($provider->branch_id) {
                $salonSchedule = DB::table('salon_schedules')
                    ->where('branch_id', $provider->branch_id)
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_open', true)
                    ->first();

                if (!$salonSchedule) {
                    continue; // Skip if salon is closed this day
                }
            }

            // Check if provider works on this day
            $schedule = DB::table('provider_scheduled_works')
                ->where('user_id', $provider->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_work_day', true)
                ->where('is_active', true)
                ->first();

            if (!$schedule) {
                continue; // Skip providers who don't work on this day
            }

            // Check for full day time off
            $hasFullDayOff = ProviderTimeOff::where('user_id', $provider->id)
                ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
                ->where('start_date', '<=', $dateOnly)
                ->where('end_date', '>=', $dateOnly)
                ->exists();

            if ($hasFullDayOff) {
                continue; // Skip providers with day off
            }

            // Get working hours - intersect provider schedule with salon schedule
            $providerStart = Carbon::parse($dateOnly . ' ' . $schedule->start_time);
            $providerEnd = Carbon::parse($dateOnly . ' ' . $schedule->end_time);

            // If salon schedule exists, intersect with provider schedule
            if ($salonSchedule) {
                $salonStart = Carbon::parse($dateOnly . ' ' . $salonSchedule->open_time);
                $salonEnd = Carbon::parse($dateOnly . ' ' . $salonSchedule->close_time);

                // Working hours are the intersection of salon and provider hours
                $workStart = $providerStart->greaterThan($salonStart) ? $providerStart : $salonStart;
                $workEnd = $providerEnd->lessThan($salonEnd) ? $providerEnd : $salonEnd;

                // Skip if no overlap
                if ($workStart->greaterThanOrEqualTo($workEnd)) {
                    continue;
                }
            } else {
                $workStart = $providerStart;
                $workEnd = $providerEnd;
            }

            // Get hourly time offs
            $hourlyTimeOffs = ProviderTimeOff::where('user_id', $provider->id)
                ->where('type', ProviderTimeOff::TYPE_HOURLY)
                ->whereDate('start_date', $dateOnly)
                ->get();

            // Get existing appointments
            $existingAppointments = Appointment::where('provider_id', $provider->id)
                ->whereDate('appointment_date', $dateOnly)
                ->where('created_status', 1)
                ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::COMPLETED->value])
                ->get();

            $providersData[] = [
                'id' => $provider->id,
                'name' => $provider->full_name,
                'workStart' => $workStart->format('H:i'),
                'workEnd' => $workEnd->format('H:i'),
                'timeOffs' => $hourlyTimeOffs->map(fn($timeOff) => [
                    'start' => Carbon::parse($timeOff->start_time)->format('H:i'),
                    'end' => Carbon::parse($timeOff->end_time)->format('H:i'),
                ])->toArray(),
                'appointments' => $existingAppointments->map(fn($apt) => [
                    'start' => Carbon::parse($apt->start_time)->format('H:i'),
                    'end' => Carbon::parse($apt->end_time)->format('H:i'),
                    'customer' => $apt->customer->full_name ?? 'N/A',
                ])->toArray(),
            ];
        }

        return $providersData;
    }
}
