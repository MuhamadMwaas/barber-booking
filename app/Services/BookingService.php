<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    protected BookingValidationService $validationService;

    public function __construct(BookingValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Create a new booking with multiple services
     *
     * @param User $customer
     * @param array $bookingData
     * @return Appointment
     * @throws InvalidArgumentException
     */
    public function createBooking(?User $customer, array $bookingData): Appointment
    {

        $services = $bookingData['services'];
        $date = $bookingData['date'];
        $paymentMethod = $bookingData['payment_method'];
        $notes = $bookingData['notes'] ?? null;
        $customerName = $bookingData['customer_name'] ?? ($customer->name?? null);
        $customerEmail = $bookingData['customer_email'] ?? $customer->email?? null;
        $customerPhone = $bookingData['customer_phone'] ?? $customer->phone?? null;


        $this->validationService->validateBasicData($services, $date);


        $this->validationService->validateDailyBookingLimit($customer, $date);


        $services = $this->sortServicesByStartTime($services);


        $preparedServices = $this->validateAndPrepareServices($services, $date, $customer);

        // 5. Calculate totals
        $totals = $this->calculateTotals($preparedServices);

        // 6. Create booking in transaction
        return DB::transaction(function () use ($customer, $date, $paymentMethod, $notes, $preparedServices, $totals, $customerName, $customerEmail, $customerPhone) {
            // Determine created_status based on payment method
            $createdStatus = $paymentMethod === 'cash' ? 1 : 0;
            $paymentStatus = $paymentMethod === 'cash'
                ? PaymentStatus::PENDING
                : PaymentStatus::PENDING;

            // Get first service for main appointment data
            $firstService = $preparedServices[0];

            // Create main appointment
            $appointment = Appointment::create([
                'number' => $this->generateAppointmentNumber(),
                'customer_id' => $customer->id,
                'provider_id' => $firstService['provider_id'],
                'appointment_date' => $date,
                'start_time' => Carbon::parse($date . ' ' . $firstService['start_time']),
                'end_time' => Carbon::parse($date . ' ' . $preparedServices[count($preparedServices) - 1]['end_time']),
                'duration_minutes' => $totals['total_duration'],
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'status' => AppointmentStatus::PENDING,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'notes' => $notes,
                'created_status' => $createdStatus,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
            ]);

            // Create appointment services
            foreach ($preparedServices as $index => $serviceData) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $serviceData['service_id'],
                    'service_name' => $serviceData['service_name'],
                    'duration_minutes' => $serviceData['duration_minutes'],
                    'price' => $serviceData['price'],
                    'sequence_order' => $index + 1,
                ]);
            }

            return $appointment->load(['services', 'customer', 'provider', 'services_record']);
        });
    }

    /**
     * Sort services by start time
     */
    private function sortServicesByStartTime(array $services): array
    {
        usort($services, function ($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        return $services;
    }

    /**
     * Validate and prepare services data
     */
    private function validateAndPrepareServices(array $services, string $date, User $customer): array
    {
        $preparedServices = [];
        $previousEndTime = null;

        $serviceIds = array_column($services, 'service_id');
        $providerIds = array_column($services, 'provider_id');

        $servicesCollection = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $providersCollection = User::whereIn('id', $providerIds)->get()->keyBy('id');

        foreach ($services as $index => $serviceData) {

        $service = $servicesCollection->get($serviceData['service_id']);
        $provider = $providersCollection->get($serviceData['provider_id']);

            $this->validationService->validateProviderOffersService($provider, $service);


            $serviceDuration = $this->getEffectiveDuration($provider, $service);
            $servicePrice = $this->getEffectivePrice($provider, $service);

            // 3. Calculate start and end time
            $startTime = Carbon::parse($date . ' ' . $serviceData['start_time']);
            $endTime = $startTime->copy()->addMinutes($serviceDuration);

            // 4. Validate sequential timing (if not first service)
            if ($previousEndTime !== null) {
                $this->validationService->validateSequentialTiming(
                    $previousEndTime,
                    $startTime,
                    $index + 1
                );
            }

            // 5. Validate time slot availability
            $this->validationService->validateTimeSlotAvailability(
                $provider,
                $service,
                $startTime,
                $endTime
            );

            // 6. Validate no duplicate booking
            $this->validationService->validateNoDuplicateBooking(
                $customer,
                $startTime,
                array_column($services, 'service_id')
            );

            // Prepare service data
            $preparedServices[] = [
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'service_name' => $service->name,
                'duration_minutes' => $serviceDuration,
                'price' => $servicePrice,
                'start_time' => $startTime->format('H:i'),
                'end_time' => $endTime->format('H:i'),
            ];

            // Update previous end time for next iteration
            $previousEndTime = $endTime;
        }

        return $preparedServices;
    }

    /**
     * Calculate booking totals
     */
    private function calculateTotals(array $preparedServices): array
    {
        $subtotal = array_sum(array_column($preparedServices, 'price'));
        $totalDuration = array_sum(array_column($preparedServices, 'duration_minutes'));

        // Get tax rate from settings
        $taxRate = (float) get_setting('tax_rate', 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'total_duration' => $totalDuration,
        ];
    }

    /**
     * Get effective duration (custom or default)
     */
    private function getEffectiveDuration(User $provider, Service $service): int
    {
        return $service->duration_minutes ;
        $pivot = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->first();

        return $pivot->custom_duration ?? $service->duration_minutes;
    }

    /**
     * Get effective price (custom or default)
     */
    private function getEffectivePrice(User $provider, Service $service): float
    {
        $pivot = DB::table('provider_service')
            ->where('provider_id', $provider->id)
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->first();

        $effectivePrice = $pivot->custom_price ?? $service->price;

        // Check for discount price
        if ($service->discount_price && $service->discount_price < $effectivePrice) {
            return (float) $service->discount_price;
        }

        return (float) $effectivePrice;
    }

    /**
     * Generate unique appointment number
     */
    private function generateAppointmentNumber(): string
    {
        $prefix = 'APT';
        $date = Carbon::now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Cancel booking
     */
    public function cancelBooking(Appointment $appointment, ?string $reason = null): bool
    {
        if (!in_array($appointment->status, [AppointmentStatus::PENDING])) {
            throw new InvalidArgumentException('Only pending appointments can be cancelled');
        }

        return $appointment->cancel($reason);
    }

    /**
     * Get customer bookings
     */
    public function getCustomerBookings(User $customer, ?string $status = null)
    {
        $query = Appointment::where('customer_id', $customer->id)
            ->with(['services', 'provider', 'services_record'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('start_time', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Get booking details
     */
    public function getBookingDetails(int $appointmentId, User $customer): Appointment
    {
        $appointment = Appointment::with(['services', 'provider', 'customer', 'services_record'])
            ->findOrFail($appointmentId);

        // Verify customer owns this appointment
        if ($appointment->customer_id !== $customer->id) {
            throw new InvalidArgumentException('Unauthorized access to this appointment');
        }

        return $appointment;
    }
}
