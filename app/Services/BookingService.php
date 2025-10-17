<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Models\AppointmentService as AppointmentServiceModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * خدمة إدارة الحجوزات المتقدمة
 * تدير عملية الحجز الكاملة مع التحقق من التوفر والتسلسل الزمني
 */
class BookingService
{
    protected ServiceAvailabilityService $availabilityService;
    protected BookingValidationService $validationService;

    public function __construct(
        ServiceAvailabilityService $availabilityService,
        BookingValidationService $validationService
    ) {
        $this->availabilityService = $availabilityService;
        $this->validationService = $validationService;
    }

    /**
     * @param User $customer
     * @param array $bookingData
     * @return array
     */
    public function validateBooking(User $customer, array $bookingData): array
    {
        $services = $bookingData['services'];
        $date = $bookingData['date']; // Y-m-d

        $this->validationService->validateBasicData($services, $date);

        $validatedServices = [];
        $previousEndTime = null;

        foreach ($services as $index => $serviceData) {
            $service = Service::findOrFail($serviceData['service_id']);
            $provider = User::findOrFail($serviceData['provider_id']);
            $startTime = Carbon::parse($date . ' ' . $serviceData['start_time']);

            // التحقق من أن المقدم يقدم هذه الخدمة
            $this->validationService->validateProviderOffersService($provider, $service);

            // الحصول على مدة الخدمة الفعلية
            $duration = $this->getEffectiveDuration($provider, $service);
            $endTime = $startTime->copy()->addMinutes($duration);

            // التحقق من التسلسل الزمني
            if ($index > 0) {
                $this->validationService->validateSequentialTiming(
                    $previousEndTime,
                    $startTime,
                    $index
                );
            }

            // التحقق من توفر الوقت
            $this->validationService->validateTimeSlotAvailability(
                $provider,
                $service,
                $startTime,
                $endTime
            );

            $validatedServices[] = [
                'service' => $service,
                'provider' => $provider,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $duration,
                'price' => $this->getEffectivePrice($provider, $service),
            ];

            $previousEndTime = $endTime;
        }

        // 3. حساب الإجماليات
        $totals = $this->calculateTotals($validatedServices);

        return [
            'success' => true,
            'message' => 'Booking validation successful',
            'services' => array_map(function ($item) {
                return [
                    'service_id' => $item['service']->id,
                    'service_name' => $item['service']->name,
                    'provider_id' => $item['provider']->id,
                    'provider_name' => $item['provider']->full_name,
                    'start_time' => $item['start_time']->format('H:i'),
                    'end_time' => $item['end_time']->format('H:i'),
                    'duration_minutes' => $item['duration'],
                    'price' => $item['price'],
                ];
            }, $validatedServices),
            'totals' => $totals,
            'appointment_summary' => [
                'date' => $date,
                'start_time' => $validatedServices[0]['start_time']->format('H:i'),
                'end_time' => end($validatedServices)['end_time']->format('H:i'),
                'total_duration' => $totals['total_duration'],
                'total_services' => count($validatedServices),
            ],
        ];
    }

    /**
     *
     * @param User $customer
     * @param array $bookingData
     * @return Appointment
     */
    public function createBooking(User $customer, array $bookingData): Appointment
    {
        return DB::transaction(function () use ($customer, $bookingData) {

            $validation = $this->validateBooking($customer, $bookingData);

            $services = $bookingData['services'];
            $date = $bookingData['date'];
            $notes = $bookingData['notes'] ?? null;
            $paymentMethod = $bookingData['payment_method'] ?? null;

            // 2. جمع البيانات من التحقق
            $validatedServices = [];
            $firstStartTime = null;
            $lastEndTime = null;
            $totalDuration = 0;
            $subtotal = 0;

            foreach ($services as $serviceData) {
                $service = Service::findOrFail($serviceData['service_id']);
                $provider = User::findOrFail($serviceData['provider_id']);
                $startTime = Carbon::parse($date . ' ' . $serviceData['start_time']);

                $duration = $this->getEffectiveDuration($provider, $service);
                $endTime = $startTime->copy()->addMinutes($duration);
                $price = $this->getEffectivePrice($provider, $service);

                if ($firstStartTime === null) {
                    $firstStartTime = $startTime;
                }
                $lastEndTime = $endTime;
                $totalDuration += $duration;
                $subtotal += $price;

                $validatedServices[] = [
                    'service' => $service,
                    'provider' => $provider,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration' => $duration,
                    'price' => $price,
                ];
            }

            // 3. حساب الضرائب والإجمالي
            $taxRate = $this->getTaxRate();
            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            // 4. استخدام أول مقدم خدمة كمقدم رئيسي للحجز
            $primaryProvider = $validatedServices[0]['provider'];

            // 5. إنشاء الحجز
            $appointment = Appointment::create([
                'number' => $this->generateAppointmentNumber(),
                'customer_id' => $customer->id,
                'provider_id' => $primaryProvider->id,
                'appointment_date' => Carbon::parse($date),
                'start_time' => $firstStartTime,
                'end_time' => $lastEndTime,
                'duration_minutes' => $totalDuration,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => \App\Enum\AppointmentStatus::PENDING,
                'payment_status' => \App\Enum\PaymentStatus::PENDING,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);

            // 6. إضافة الخدمات للحجز
            foreach ($validatedServices as $index => $serviceData) {
                AppointmentServiceModel::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $serviceData['service']->id,
                    'service_name' => $serviceData['service']->name,
                    'duration_minutes' => $serviceData['duration'],
                    'price' => $serviceData['price'],
                    'sequence_order' => $index + 1,
                ]);
            }

            // 7. مسح الـ cache للتوفر
            foreach ($validatedServices as $serviceData) {
                $this->availabilityService->clearProviderCache($serviceData['provider']->id);
                $this->availabilityService->clearServiceCache($serviceData['service']->id);
            }

            // 8. إرجاع الحجز مع العلاقات
            return $appointment->load(['services_record', 'provider', 'customer']);
        });
    }

    /**
     * الحصول على الأوقات المتاحة لحجز متعدد الخدمات
     *
     * @param array $servicesData
     * @param string $date
     * @return array
     */
    public function getAvailableSlotsForMultipleServices(array $servicesData, string $date): array
    {
        $allSlots = [];
        $previousEndTime = null;

        foreach ($servicesData as $index => $serviceData) {
            $service = Service::findOrFail($serviceData['service_id']);
            $provider = User::findOrFail($serviceData['provider_id']);

            // الحصول على الأوقات المتاحة لهذا المقدم في هذا اليوم
            $availability = $this->availabilityService->getProviderAvailableSlotsByDate(
                $service->id,
                $provider->id,
                $date
            );

            $slots = $availability['available_slots'];

            // تصفية الأوقات حسب الخدمة السابقة
            if ($index > 0 && $previousEndTime) {
                $slots = array_filter($slots, function ($slot) use ($previousEndTime) {
                    $slotStart = Carbon::parse($slot['start_time']);
                    return $slotStart->gte($previousEndTime);
                });
            }

            if (empty($slots)) {
                return [
                    'success' => false,
                    'message' => "No available slots found for service at position " . ($index + 1),
                    'service_index' => $index,
                    'service_name' => $service->name,
                ];
            }

            $allSlots[] = [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'provider_id' => $provider->id,
                'provider_name' => $provider->full_name,
                'slots' => array_values($slots),
            ];

            // تحديث وقت النهاية للخدمة التالية
            if (!empty($slots)) {
                $firstSlot = reset($slots);
                $slotStart = Carbon::parse($date . ' ' . $firstSlot['start_time']);
                $duration = $this->getEffectiveDuration($provider, $service);
                $previousEndTime = $slotStart->copy()->addMinutes($duration);
            }
        }

        return [
            'success' => true,
            'date' => $date,
            'services_slots' => $allSlots,
        ];
    }

    /**
     * حساب الإجماليات
     */
    private function calculateTotals(array $services): array
    {
        $subtotal = array_sum(array_column($services, 'price'));
        $total = round($subtotal, 2);
        $subtotal =$total / (1 + $this->getTaxRate());
        $totalDuration = array_sum(array_column($services, 'duration'));
        $taxRate = $this->getTaxRate();
        $taxAmount = $total - $subtotal;
        $totalAmount = $subtotal + $taxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'total_duration' => $totalDuration,
            'currency' => 'EUR',
        ];
    }

    /**
     * الحصول على المدة الفعلية للخدمة
     */
    private function getEffectiveDuration(User $provider, Service $service): int
    {
        $pivot = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->first();

        return $pivot->custom_duration ?? $service->duration_minutes;
    }

    /**
     * الحصول على السعر الفعلي للخدمة
     */
    private function getEffectivePrice(User $provider, Service $service): float
    {
        $pivot = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->first();

        $price = $pivot->custom_price ?? $service->display_price;
        return (float) $price;
    }

    /**
     * الحصول على نسبة الضريبة
     */
    private function getTaxRate(): float
    {
        return 19; 
    }

    /**
     * توليد رقم حجز فريد
     */
    private function generateAppointmentNumber(): string
    {
        $prefix = 'APT';
        $date = Carbon::now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }
}
