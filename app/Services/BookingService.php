<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\PushRequiredException;
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
    public function __construct(
        protected BookingValidationService $validationService,
        protected ?GapAnalysisService $gapAnalysis = null,
        protected ?PushBookingsService $pushService = null,
        protected ?AppointmentLinkingService $linkingService = null,
    ) {
        // Lazy-resolve optional services from the container if not injected.
        $this->gapAnalysis     = $this->gapAnalysis ?? app(GapAnalysisService::class);
        $this->pushService     = $this->pushService ?? app(PushBookingsService::class);
        $this->linkingService  = $this->linkingService ?? app(AppointmentLinkingService::class);
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
        $isConfirmed = $bookingData['is_confirmed'] ?? ($paymentMethod == 'cash');
        $markAsPaid = $bookingData['mark_as_paid'] ?? ($paymentMethod == 'cash');
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
        return DB::transaction(function () use ($customer, $date, $paymentMethod, $isConfirmed, $markAsPaid, $notes, $preparedServices, $totals, $customerName, $customerEmail, $customerPhone) {
            // Allow staff-created bookings to be confirmed without being marked as paid yet.
            $createdStatus = $isConfirmed ? 1 : 0;
            $paymentStatus = $markAsPaid
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
                'booking_source' => $bookingData['booking_source'] ?? 'in_person',
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

    // ================================================================
    // Add Service to an Existing Booking (PENDING + DRAFT invoice only)
    // ================================================================

    /**
     * Add a new service to an existing PENDING appointment.
     * Two modes:
     *   - Same provider as anchor → append as AppointmentService to the anchor.
     *   - Different provider     → create a CHILD appointment linked to the
     *                              parent (or to the anchor's parent if the
     *                              anchor is itself a child).
     *
     * @param Appointment $anchor The existing appointment the staff is acting on.
     * @param array $data {
     *     service_id:        int,
     *     provider_id:       int,            // may equal anchor.provider_id
     *     placement:         'before'|'after',
     *     duration_minutes:  ?int,           // null → service.duration_minutes
     *     start_time:        ?string ('H:i') // null → auto-compute
     *     apply_push:        bool,           // false = throw PushRequiredException if push needed
     * }
     *
     * @return array{
     *   mode: 'same_provider'|'child_created',
     *   appointment: Appointment,            // the anchor (same_provider) or new child
     *   pushed_appointments: int[],
     *   invoice: \App\Models\Invoice,        // the unified invoice on the parent/standalone
     * }
     *
     * @throws PushRequiredException     when apply_push=false and a push is needed
     * @throws InvalidArgumentException  on any business rule violation
     */
    public function addServiceToBooking(Appointment $anchor, array $data): array
    {
        // 0) Pre-checks
        if (! $anchor->canAcceptNewService()) {
            throw new InvalidArgumentException(
                'This booking does not accept new services (not pending or already paid).'
            );
        }

        if (! isset($data['service_id'], $data['provider_id'], $data['placement'])) {
            throw new InvalidArgumentException('Missing required fields: service_id, provider_id, placement.');
        }
        if (! in_array($data['placement'], ['before', 'after'], true)) {
            throw new InvalidArgumentException("Placement must be 'before' or 'after'.");
        }

        $service     = Service::findOrFail($data['service_id']);
        $newProvider = User::findOrFail($data['provider_id']);
        $duration    = (int) ($data['duration_minutes'] ?? $service->duration_minutes);
        if ($duration <= 0) {
            throw new InvalidArgumentException('Duration must be greater than zero.');
        }
        $placement   = $data['placement'];
        $applyPush   = (bool) ($data['apply_push'] ?? false);
        $requestedStart = $this->parseTimeOrNull($data['start_time'] ?? null, $anchor);

        // 1) Validate provider offers the service
        $this->validationService->validateProviderOffersService($newProvider, $service);

        $sameProvider = ((int) $data['provider_id']) === ((int) $anchor->provider_id);

        // 2) Analyze gap
        if ($sameProvider) {
            $analysis = $placement === 'before'
                ? $this->gapAnalysis->analyzeAddBefore($anchor, $service, $duration, $requestedStart)
                : $this->gapAnalysis->analyzeAddAfter($anchor, $service, $duration, $requestedStart);
        } else {
            // Child mode — gap measured against invoice owner (parent or self)
            $invoiceOwner = $this->linkingService->getInvoiceOwner($anchor);
            $analysis = $this->gapAnalysis->analyzeChildAdd(
                $invoiceOwner,
                $newProvider,
                $service,
                $duration,
                $placement,
                $requestedStart
            );
        }

        if (! ($analysis['is_possible'] ?? false)) {
            throw new InvalidArgumentException(
                $this->formatAnalysisError($analysis)
            );
        }

        // 3) Push gating — if push needed but user hasn't confirmed
        if (($analysis['requires_push'] ?? false) && ! $applyPush) {
            throw new PushRequiredException($analysis['push_plan'] ?? []);
        }

        // 4) Apply inside a single transaction
        return DB::transaction(function () use (
            $anchor, $service, $newProvider, $sameProvider, $duration,
            $placement, $analysis, $applyPush
        ) {
            // 4.1) Execute push if needed
            $pushedIds = [];
            if (($analysis['requires_push'] ?? false) && $applyPush) {
                $pushedIds = $this->pushService->executePushPlan(
                    $analysis['push_plan'],
                    $anchor->appointment_date->format('Y-m-d')
                );
            }

            // 4.2) Same provider → augment anchor in place
            if ($sameProvider) {
                $target = $this->addServiceSameProvider(
                    $anchor, $service, $newProvider, $duration, $analysis, $placement
                );
                $mode = 'same_provider';
            } else {
                $target = $this->addServiceDifferentProvider(
                    $anchor, $service, $newProvider, $duration, $analysis, $placement
                );
                $mode = 'child_created';
            }

            // 4.3) Rebuild the unified invoice on the parent/standalone
            $invoiceOwner = $this->linkingService->getInvoiceOwner($target);
            $invoiceService = app(InvoiceService::class);
            $invoice = $invoiceService->rebuildAggregatedInvoice($invoiceOwner);

            return [
                'mode' => $mode,
                'appointment' => $target,
                'pushed_appointments' => $pushedIds,
                'invoice' => $invoice,
            ];
        });
    }

    /**
     * Add the new service to the SAME anchor (same provider).
     * Adjusts anchor.start_time/end_time, sequence orders, and totals.
     */
    protected function addServiceSameProvider(
        Appointment $anchor,
        Service $service,
        User $provider,
        int $duration,
        array $analysis,
        string $placement
    ): Appointment {
        $price = $this->getEffectivePrice($provider, $service);
        $date  = $anchor->appointment_date->format('Y-m-d');

        // Insert AppointmentService row
        $maxSeq = (int) ($anchor->services_record()->max('sequence_order') ?? 0);
        $newSequence = $placement === 'before' ? 0 : $maxSeq + 1;

        AppointmentService::create([
            'appointment_id' => $anchor->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => $duration,
            'price' => $price,
            'sequence_order' => $newSequence,
        ]);

        // Resequence so sequence_order is contiguous 1..N
        if ($placement === 'before') {
            $this->resequenceServices($anchor->fresh('services_record'));
        }

        // Compute new anchor start/end_time
        $newStartStr = $analysis['suggested_start_time'];
        $newEndStr   = $analysis['suggested_end_time'];

        $newStart = $placement === 'before'
            ? Carbon::parse($date . ' ' . $newStartStr)   // new service start
            : $anchor->start_time;

        $newEnd = $placement === 'before'
            ? $anchor->end_time
            : Carbon::parse($date . ' ' . $newEndStr);     // new service end

        // Recalculate totals from ALL current services
        $totals = $this->recalculateAnchorTotals($anchor->fresh('services_record'));

        $anchor->update([
            'start_time' => $newStart,
            'end_time' => $newEnd,
            'duration_minutes' => $totals['total_duration'],
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total_amount' => $totals['total_amount'],
        ]);

        return $anchor->fresh(['services_record.service', 'provider', 'invoice', 'parent', 'children']);
    }

    /**
     * Create a CHILD appointment for the new service (different provider).
     * The child has its own start/end/duration/totals but no invoice — the
     * unified invoice is rebuilt on the parent in step 4.3.
     */
    protected function addServiceDifferentProvider(
        Appointment $anchor,
        Service $service,
        User $newProvider,
        int $duration,
        array $analysis,
        string $placement
    ): Appointment {
        $parent = $this->linkingService->getInvoiceOwner($anchor);

        // Validate before we write
        $this->linkingService->validateChildCandidate($parent, [
            'appointment_date' => $parent->appointment_date,
        ]);

        $price = $this->getEffectivePrice($newProvider, $service);
        $date  = $parent->appointment_date->format('Y-m-d');

        // Tax split
        $taxRate = (float) get_setting('tax_rate', 19);
        if ($taxRate > 0) {
            $net = round($price / (1 + ($taxRate / 100)), 2);
            $tax = round($price - $net, 2);
        } else {
            $net = round($price, 2);
            $tax = 0.0;
        }

        $newStart = Carbon::parse($date . ' ' . $analysis['suggested_start_time']);
        $newEnd   = Carbon::parse($date . ' ' . $analysis['suggested_end_time']);

        $child = Appointment::create([
            'number' => $this->generateAppointmentNumber(),
            'parent_appointment_id' => $parent->id,
            'customer_id' => $parent->customer_id,
            'customer_name' => $parent->getRawOriginal('customer_name'),
            'customer_email' => $parent->getRawOriginal('customer_email'),
            'customer_phone' => $parent->getRawOriginal('customer_phone'),
            'provider_id' => $newProvider->id,
            'appointment_date' => $parent->appointment_date,
            'start_time' => $newStart,
            'end_time' => $newEnd,
            'duration_minutes' => $duration,
            'subtotal' => $net,
            'tax_amount' => $tax,
            'total_amount' => $price,
            'status' => AppointmentStatus::PENDING,
            'payment_method' => $parent->payment_method ?? 'cash',
            'payment_status' => PaymentStatus::PENDING,
            'created_status' => 1, // Block time slot in timeline
            'booking_source' => $parent->booking_source?->value ?? 'in_person',
            'notes' => null,
        ]);

        AppointmentService::create([
            'appointment_id' => $child->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'duration_minutes' => $duration,
            'price' => $price,
            'sequence_order' => 1,
        ]);

        return $child->fresh(['services_record.service', 'provider', 'parent']);
    }

    // ----------- helpers used by addServiceToBooking ------------

    protected function parseTimeOrNull(?string $time, Appointment $anchor): ?Carbon
    {
        if (! $time) {
            return null;
        }
        $date = $anchor->appointment_date->format('Y-m-d');
        return Carbon::parse($date . ' ' . $time);
    }

    protected function resequenceServices(Appointment $appointment): void
    {
        $rows = $appointment->services_record()->orderBy('sequence_order')->get();
        foreach ($rows as $i => $row) {
            $row->update(['sequence_order' => $i + 1]);
        }
    }

    protected function recalculateAnchorTotals(Appointment $appointment): array
    {
        $services = $appointment->services_record->map(fn($s) => [
            'price' => (float) $s->price,
            'duration_minutes' => (int) $s->duration_minutes,
        ])->toArray();

        // Reuse the (private) calculateTotals via reflection to avoid duplicating logic.
        $ref = new \ReflectionMethod($this, 'calculateTotals');
        $ref->setAccessible(true);
        return $ref->invoke($this, $services);
    }

    /**
     * Convert a failed-analysis array into a single human-readable error string.
     */
    protected function formatAnalysisError(array $analysis): string
    {
        $reason = $analysis['reason'] ?? 'unknown';
        $max = $analysis['max_duration_available'] ?? null;
        $gap = $analysis['gap_minutes'] ?? null;
        $blocker = $analysis['blocking_appointment_number'] ?? null;

        return match ($reason) {
            'provider_not_working' => 'Provider does not work on the selected day.',
            'provider_full_day_off' => 'Provider has a full-day time off.',
            'provider_time_off_conflict' => 'Provider has a time-off in this slot.',
            'no_space_before' => 'No space available before the booking.',
            'insufficient_space' => "Insufficient space — maximum duration available is {$max} minutes.",
            'overlaps_previous' => 'New service overlaps the previous booking.',
            'overlaps_anchor' => 'New service overlaps the existing booking.',
            'overlaps_owner' => 'New service overlaps the parent booking window.',
            'gap_too_large' => "Gap of {$gap} minutes exceeds the allowed " . GapAnalysisService::MAX_GAP_MINUTES . " minutes.",
            'exceeds_work_hours' => 'New service goes past the provider\'s working hours.',
            'paid_booking_in_chain' => "Cannot push: booking #{$blocker} is already paid. Reduce duration to {$max} minutes or less.",
            'new_provider_busy' => 'The selected provider is busy at that time.',
            'in_past' => 'Cannot place a service in the past.',
            default => "Cannot add service ({$reason}).",
        };
    }
}
