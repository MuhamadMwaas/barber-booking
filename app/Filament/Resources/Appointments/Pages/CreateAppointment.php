<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingValidationService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    /**
     * Perform validations before record creation
     * Access Repeater relationship data directly from the component
     */
    protected function beforeCreate(): void
    {
        try {
            // Get the raw data from Livewire component state
            $formData = $this->data;

            // Get services from Livewire state (موجودة في $this->data حتى مع ->relationship())
            $services = $this->getServicesFromRepeater();

            // Add services to formData for validation
            $formData['services_record'] = $services;

            // Log data for debugging (only in development)
            if (config('app.debug')) {
                Log::debug('CreateAppointment - beforeCreate data check', [
                    'has_services_in_data' => isset($this->data['services_record']),
                    'services_count' => count($services),
                    'services_data' => $services,
                ]);
            }

            // Validate form data
            $this->validateFormData($formData);

            // Perform professional validations
            $this->performProfessionalValidations($formData);

        } catch (\InvalidArgumentException $e) {
            // Show validation error notification
            Notification::make()
                ->danger()
                ->title(__('messages.appointment.validation_error'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            // Stop the creation process
            $this->halt();

        } catch (\Exception $e) {
            // Log unexpected errors
            Log::error('CreateAppointment - Unexpected error in beforeCreate', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Show error notification
            Notification::make()
                ->danger()
                ->title(__('messages.appointment.creation_error'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    /**
     * Mutate form data before create - only for non-relationship fields
     * Repeater relationship data is NOT available here in $data array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get services from the Repeater component
        $services = $this->getServicesFromRepeater();

        // Calculate totals from services
        $data = $this->calculateTotalsFromServices($data, $services);

        // Prepare datetime fields
        $data = $this->prepareDateTimeFieldsFromServices($data, $services);

        return $data;
    }

    /**
     * Handle record creation with transaction
     *
     * الحل الجذري: حفظ السجل الرئيسي والعلاقات يدوياً في transaction واحدة
     * لضمان Atomicity (كل شيء أو لا شيء)
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // 1. الحصول على بيانات الخدمات من $this->data (قبل أن يزيلها Filament)
            $servicesData = $this->getServicesFromRepeater();

            // 2. إنشاء سجل الموعد الرئيسي
            // ملاحظة: $data لا يحتوي على services_record لأن Filament أزالها
            $appointment = static::getModel()::create($data);

            // 3. حفظ الخدمات يدوياً (العلاقة HasMany)
            if (!empty($servicesData)) {
                foreach ($servicesData as $index => $serviceData) {
                    // تأكد من وجود sequence_order
                    if (empty($serviceData['sequence_order'])) {
                        $serviceData['sequence_order'] = $index + 1;
                    }

                    // إنشاء سجل AppointmentService
                    $appointment->services_record()->create($serviceData);
                }

                if (config('app.debug')) {
                    Log::debug('handleRecordCreation - Services created', [
                        'appointment_id' => $appointment->id,
                        'services_count' => count($servicesData),
                    ]);
                }
            }

            // 4. تحميل العلاقات للعرض
            $appointment->load(['customer', 'provider', 'services_record']);

            // 5. منع Filament من محاولة حفظ العلاقات مرة أخرى
            // بحذف services_record من البيانات
            unset($this->data['services_record']);

            return $appointment;
        });
    }

    /**
     * Redirect to view page after successful creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    /**
     * Show success notification
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('messages.appointment.created_successfully'))
            ->body(__('messages.appointment.booking_number') . ': ' . $this->getRecord()->number)
            ->duration(5000);
    }

    // ==================== Private Helper Methods ====================

    /**
     * Get services data from Livewire component state
     *
     * المفتاح: استخدام $this->data بدلاً من $data المعامل
     * السبب: Filament يزيل بيانات ->relationship() من $data لكنها تبقى في $this->data
     */
    private function getServicesFromRepeater(): array
    {
        // الوصول المباشر لبيانات Livewire Component قبل فلترة Filament
        // هنا تكون البيانات موجودة حتى لو كان الـ Repeater يستخدم ->relationship()
        $services = $this->data['services_record'] ?? [];

        // تصفية العناصر الفارغة
        $services = array_filter($services, function($item) {
            return !empty($item) && is_array($item) && isset($item['service_id']);
        });

        // إعادة ترتيب المفاتيح (reindex)
        $services = array_values($services);

        // Debug logging
        if (config('app.debug')) {
            Log::debug('getServicesFromRepeater - Accessed via $this->data', [
                'services_count' => count($services),
                'has_data' => !empty($services),
                'first_service' => $services[0] ?? null,
            ]);
        }

        return $services;
    }

    /**
     * Validate form data (called in beforeCreate where repeater data is available)
     */
    private function validateFormData(array $formData): void
    {
        // Ensure services_record exists and is not empty
        if (!isset($formData['services_record']) || !is_array($formData['services_record'])) {
            throw new \InvalidArgumentException(__('messages.appointment.at_least_one_service'));
        }

        // Filter out empty items from repeater
        $services = array_values(array_filter($formData['services_record'], function($item) {
            return !empty($item) && is_array($item) && isset($item['service_id']) && !empty($item['service_id']);
        }));

        // Check after filtering
        if (count($services) === 0) {
            throw new \InvalidArgumentException(__('messages.appointment.at_least_one_service'));
        }

        // Ensure required fields exist
        if (empty($formData['customer_id'])) {
            throw new \InvalidArgumentException(__('messages.appointment.select_customer'));
        }

        if (empty($formData['provider_id'])) {
            throw new \InvalidArgumentException(__('messages.appointment.select_provider'));
        }

        if (empty($formData['appointment_date'])) {
            throw new \InvalidArgumentException(__('messages.appointment.select_date'));
        }

        if (empty($formData['start_time'])) {
            throw new \InvalidArgumentException(__('messages.appointment.select_start_time'));
        }

        // Check duration
        $duration = $formData['duration_minutes'] ?? 0;
        if ($duration <= 0) {
            // Calculate from services if not provided
            $duration = collect($services)->sum('duration_minutes');
        }

        if ($duration <= 0) {
            throw new \InvalidArgumentException(__('messages.appointment.duration_greater_than_zero'));
        }
    }

    /**
     * Perform professional validations using BookingValidationService
     */
    private function performProfessionalValidations(array $formData): void
    {
        $validationService = app(BookingValidationService::class);
        $provider = User::findOrFail($formData['provider_id']);
        $customer = User::findOrFail($formData['customer_id']);
        $date = Carbon::parse($formData['appointment_date'])->format('Y-m-d');

        // Get services and filter empty ones
        $services = array_values(array_filter($formData['services_record'] ?? [], function($item) {
            return !empty($item) && is_array($item) && isset($item['service_id']) && !empty($item['service_id']);
        }));

        // Calculate duration
        $duration = $formData['duration_minutes'] ?? collect($services)->sum('duration_minutes');

        // Parse start and end time
        $startTime = Carbon::parse($date . ' ' . Carbon::parse($formData['start_time'])->format('H:i:s'));
        $endTime = $startTime->copy()->addMinutes($duration);

        // 1. Validate basic booking data
        $validationService->validateBasicData($services, $date);

        // 2. Validate daily booking limit for customer
        $validationService->validateDailyBookingLimit($customer, $date);

        // 3. Validate each service and provider relationship
        $serviceIds = array_column($services, 'service_id');
        $serviceModels = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        foreach ($services as $serviceData) {
            $service = $serviceModels->get($serviceData['service_id']);
            if (!$service) {
                throw new \InvalidArgumentException(__('messages.appointment.service_not_found'));
            }

            // Validate provider offers this service
            $validationService->validateProviderOffersService($provider, $service);
        }

        // 4. Validate time slot availability for the provider
        $firstService = $serviceModels->first();
        $validationService->validateTimeSlotAvailability(
            $provider,
            $firstService,
            $startTime,
            $endTime
        );

        // 5. Validate no duplicate booking for this customer
        $validationService->validateNoDuplicateBooking($customer, $startTime, $serviceIds);
    }

    /**
     * Calculate totals from services (subtotal, tax, total)
     */
    private function calculateTotalsFromServices(array $data, array $services): array
    {
        // Filter valid services
        $services = array_filter($services, function($item) {
            return !empty($item) && is_array($item) && isset($item['price']);
        });

        $servicesCollection = collect($services);

        // Calculate subtotal
        $subtotal = $servicesCollection->sum('price');
        $data['subtotal'] = round($subtotal, 2);

        // Calculate tax
        $taxRate = (float) get_setting('tax_rate', 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $data['tax_amount'] = round($taxAmount, 2);

        // Calculate total
        $data['total_amount'] = round($subtotal + $taxAmount, 2);

        return $data;
    }

    /**
     * Prepare datetime fields (start_time, end_time, appointment_date) from services
     */
    private function prepareDateTimeFieldsFromServices(array $data, array $services): array
    {
        // Filter valid services
        $services = array_filter($services, function($item) {
            return !empty($item) && is_array($item) && isset($item['duration_minutes']);
        });

        // Calculate duration
        $duration = $data['duration_minutes'] ?? collect($services)->sum('duration_minutes');
        if ($duration <= 0) {
            $duration = collect($services)->sum('duration_minutes');
        }
        $data['duration_minutes'] = $duration;

        // Prepare datetime
        $date = Carbon::parse($data['appointment_date'])->format('Y-m-d');
        $time = Carbon::parse($data['start_time'])->format('H:i:s');
        $startDateTime = Carbon::parse("{$date} {$time}");

        $data['start_time'] = $startDateTime;
        $data['appointment_date'] = $date;
        $data['end_time'] = $startDateTime->copy()->addMinutes($duration);

        return $data;
    }
}
