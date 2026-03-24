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
use Illuminate\Support\Facades\Auth;
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
        $customerName = $bookingData['customer_name'] ?? ($customer->full_name ?? null);
        $customerEmail = $bookingData['customer_email'] ?? $customer->email ?? null;
        $customerPhone = $bookingData['customer_phone'] ?? $customer->phone ?? null;


        $this->validationService->validateBasicData($services, $date);

        if ($customer) {
            $this->validationService->validateDailyBookingLimit($customer, $date);
        }


        $services = $this->sortServicesByStartTime($services);


        $preparedServices = $this->validateAndPrepareServices($services, $date, $customer, $customerPhone);

        // 5. Calculate totals
        $totals = $this->calculateTotals($preparedServices);

        // 6. Create booking in transaction
        return DB::transaction(function () use ($customer, $date, $paymentMethod, $notes, $preparedServices, $totals, $customerName, $customerEmail, $customerPhone) {
            // Determine created_status based on payment method
            $createdStatus = $paymentMethod == 'cash' ? 1 : 0;
            $paymentStatus = $paymentMethod == 'cash'
                ? PaymentStatus::PAID_ONSTIE_CASH
                : PaymentStatus::PENDING;

            // Get first service for main appointment data
            $firstService = $preparedServices[0];

            // Create main appointment
            $appointment = Appointment::create([
                'number' => $this->generateAppointmentNumber(),
                'customer_id' => $customer?->id ?? null,
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
            $InvoiceService = app(InvoiceService::class);

            $InvoiceService->createDtaftInvoiceFromAppointment(
                $appointment,
                'cash',
                0
            );
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
    private function validateAndPrepareServices(
        array $services,
        string $date,
        ?User $customer,
        ?string $customerPhone = null,
    ): array {
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
            if ($customer) {
                $this->validationService->validateNoDuplicateBooking(
                    $customer,
                    $startTime,
                    array_column($services, 'service_id')
                );
            } else {
                $this->validationService->validateNoDuplicateBookingByPhone(
                    $customerPhone,
                    $startTime,
                    array_column($services, 'service_id')
                );

            }

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
        $totalDuration = array_sum(array_column($preparedServices, 'duration_minutes'));

        // High internal precision
        $internalScale = 6;

        // tax rate (e.g., "19")
        $taxRate = (string) get_setting('tax_rate', '0');

        // factor = 1 + taxRate/100
        $factor = '1';
        if (bccomp($taxRate, '0', 6) === 1) {
            $factor = bcadd('1', bcdiv($taxRate, '100', $internalScale), $internalScale);
        }

        // Totals (as strings)
        $grossTotal = '0';
        $netTotal = '0';
        $taxTotal = '0';

        foreach ($preparedServices as $service) {
            // Always treat price as string to avoid float conversion
            // Ensure it looks like "12.34"
            $gross = isset($service['price'])
                ? (string) $service['price']
                : '0';

            // normalize to internal scale
            // (bc* doesn't need normalization, but it's good practice)
            $gross = bcadd($gross, '0', $internalScale);

            $grossTotal = bcadd($grossTotal, $gross, $internalScale);

            if (bccomp($taxRate, '0', 6) !== 1) {
                // taxRate <= 0
                $net = $gross;
                $tax = '0';
            } else {
                // net = gross / factor
                $net = bcdiv($gross, $factor, $internalScale);

                // tax = gross - net
                $tax = bcsub($gross, $net, $internalScale);
            }

            // Round per line item to 2 decimals (invoice practice)
            $net = $this->bcRound($net, 2);
            $tax = $this->bcRound($tax, 2);

            // Add rounded line amounts to totals (still as strings)
            $netTotal = bcadd($netTotal, $net, 2);
            $taxTotal = bcadd($taxTotal, $tax, 2);
        }

        // Final gross rounded to cents (money)
        $grossTotal = $this->bcRound($grossTotal, 2);

        // At this point: netTotal+taxTotal might differ by 0.01 due to per-line rounding.
        // We'll reconcile using bcmath.
        $sumNetTax = bcadd($netTotal, $taxTotal, 2);
        $diff = bcsub($grossTotal, $sumNetTax, 2); // "-0.01", "0.00", "0.01"

        if (bccomp($diff, '0.00', 2) !== 0) {
            // Adjust taxTotal by the diff to force: gross = net + tax
            $taxTotal = bcadd($taxTotal, $diff, 2);

            // Optional safety: re-round
            $taxTotal = $this->bcRound($taxTotal, 2);
        }

        return [
            'subtotal' => $netTotal,
            'tax_amount' => $taxTotal,
            'total_amount' => $grossTotal,
            'total_duration' => $totalDuration,
        ];
    }

    private function bcRound(string $number, int $precision = 2): string
    {
        if ($precision < 0) {
            throw new InvalidArgumentException('Precision must be >= 0');
        }

        $sign = '';
        if (str_starts_with($number, '-')) {
            $sign = '-';
            $number = substr($number, 1);
        }

        // shift = 10^precision
        $shift = '1' . str_repeat('0', $precision);

        // number * shift
        $shifted = bcmul($number, $shift, $precision + 6);

        // add 0.5 then floor via bcdiv(..., 0)
        $shiftedPlus = bcadd($shifted, '0.5', $precision + 6);
        $floored = bcdiv($shiftedPlus, '1', 0);

        // back to original scale
        $result = bcdiv($floored, $shift, $precision);

        return $sign === '-' ? '-' . $result : $result;
    }

    private function calculateTotalsInverse(array $preparedServices): array
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
        return $service->duration_minutes;
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
    public function generateAppointmentNumber(): string
    {
        $prefix = 'APT';
        $date = Carbon::now()->format('Ymd');
        do {
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $number = "{$prefix}-{$date}-{$random}";
        } while (Appointment::where('number', $number)->exists());


        return $number;
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
            ->find($appointmentId);
        if (!$appointment) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                "Appointment #{$appointmentId} not found"
            );
        }
        // Verify customer owns this appointment
        if ($appointment->customer_id !== $customer->id) {
            throw new InvalidArgumentException('Unauthorized access to this appointment');
        }

        return $appointment;
    }
}
