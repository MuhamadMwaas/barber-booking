<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithDashboardPermissions;
use App\Livewire\Concerns\ProvidesDashboardChrome;
use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\PushRequiredException;
use App\Models\Appointment;
use App\Models\AppointmentColor;
use App\Models\AppointmentService as AppointmentServiceModel;
use App\Models\DashboardMessage;
use App\Models\Language;
use App\Models\ProviderTimeOff;
use App\Models\Service;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BookingService;
use App\Services\BookingValidationService;
use App\Services\DashboardMessageService;
use App\Services\DashboardService;
use App\Services\GapAnalysisService;
use App\Services\InvoiceFinalizationService;
use App\Services\InvoiceService;
use App\Services\ServiceAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StaffDashboard extends Component {
    use ProvidesDashboardChrome;
    use InteractsWithDashboardPermissions;

    public string $selectedDate;
    public array $selectedProviderIds = [];
    public int $calendarYear;
    public int $calendarMonth;

    /** Provider-only view filter: when true the timeline shows only my column. */
    public bool $onlyMine = false;

    /** Today's attendance snapshot (computed at page load only — see mount()). */
    public array $attendanceState = [];

    // -------- Attendance confirmation / history modals --------
    public bool $showCheckInModal = false;
    public bool $showCheckOutModal = false;
    public bool $showAttendanceHistoryModal = false;
    public array $checkInPreview = [];
    public array $checkOutPreview = [];
    public array $attendanceHistory = [];

    public bool $showBookingModal = false;
    public bool $showAppointmentModal = false;
    public bool $showPaymentModal = false;
    public bool $showTimeOffModal = false;

    public string $customerType = 'existing';
    public ?int $selectedCustomerId = null;
    public string $guestName = '';
    public string $guestPhone = '';
    public string $guestEmail = '';
    public string $customerSearch = '';

    public array $bookingServices = [];
    public string $bookingNotes = '';

    public ?int $selectedAppointmentId = null;
    public string $editStartTime = '';
    public string $editEndTime = '';
    public int $editDuration = 0;
    public string $editNotes = '';
    public string $editProviderNotes = '';  // ← provider professional notes

    public float $paymentAmount = 0;
    /** The amount shown when the payment modal opened — used to detect a real discount. */
    public float $paymentBaseline = 0;
    public string $paymentType = '2';

    public ?int $timeOffProviderId = null;
    public string $timeOffType = '1';
    public string $timeOffStartDate = '';
    public string $timeOffEndDate = '';
    public string $timeOffStartTime = '';
    public string $timeOffEndTime = '';
    public ?int $timeOffReasonId = null;

    // -------- Add Service to Existing Booking ----------
    public bool $showAddServiceModal = false;
    public ?int $addServiceToAppointmentId = null;
    public array $addServiceForm = [
        'category_id' => null,
        'service_id' => null,
        'provider_id' => null,
        'placement' => 'after',     // 'before' | 'after'
        'duration_minutes' => 0,
        'start_time' => null,
    ];
    public array $addServiceAnalysis = [];   // Last result from analyzeAddServiceGap()

    // -------- Push Preview ----------
    public bool $showPushPreviewModal = false;
    public array $pushPreviewPlan = [];      // Plan array (from PushRequiredException)

    // -------- Bulletin Board (messages) ----------
    public string $newMessageBody = '';
    public string $newMessageExpiry = 'never';   // 'never' | 'end_of_day' | 'in_24h'

    protected DashboardService $dashboardService;
    protected DashboardMessageService $messageService;

    public function boot(DashboardService $dashboardService, DashboardMessageService $messageService) {
        $this->dashboardService = $dashboardService;
        $this->messageService = $messageService;
    }

    #[Computed]
    public function allProviders()
    {
        return $this->dashboardService->getProvidersWithStatus($this->selectedDate);
    }

    #[Computed]
    public function selectedAppointment(): ?Appointment
    {
        if (!$this->selectedAppointmentId) {
            return null;
        }
        return $this->dashboardService->getAppointmentDetails($this->selectedAppointmentId);
    }

    public function mount() {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->calendarYear = (int) Carbon::today()->format('Y');
        $this->calendarMonth = (int) Carbon::today()->format('n');

        $this->syncSelectedProvidersForSelectedDate();

        $this->addEmptyBookingService();

        // Computed once at page load (not on every poll) so the "not checked in"
        // banner is evaluated on load only, as required.
        $this->refreshAttendanceState();
    }

    public function selectDate(string $date) {
        $this->selectedDate = $date;
        $this->syncSelectedProvidersForSelectedDate();
        $this->dispatch('dateChanged', date: $date);
    }

    public function goToToday() {
        $today = Carbon::today();
        $this->selectedDate = $today->format('Y-m-d');
        $this->calendarYear = (int) $today->format('Y');
        $this->calendarMonth = (int) $today->format('n');
        $this->syncSelectedProvidersForSelectedDate();
    }

    public function previousMonth() {
        $date = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->subMonth();
        $this->calendarYear = (int) $date->format('Y');
        $this->calendarMonth = (int) $date->format('n');
    }

    public function nextMonth() {
        $date = Carbon::create($this->calendarYear, $this->calendarMonth, 1)->addMonth();
        $this->calendarYear = (int) $date->format('Y');
        $this->calendarMonth = (int) $date->format('n');
    }

    public function toggleProvider(int $providerId) {
        $selectableProviderIds = $this->getSelectableProviderIdsFromProviders(
            $this->allProviders
        );

        if (!in_array($providerId, $selectableProviderIds, true)) {
            return;
        }

        if (in_array($providerId, $this->selectedProviderIds, true)) {
            $this->selectedProviderIds = array_values(array_diff($this->selectedProviderIds, [$providerId]));
        } else {
            $this->selectedProviderIds[] = $providerId;
        }
    }

    public function openBookingModal(?int $providerId = null, ?string $startTime = null) {
        $this->resetBookingForm();
        if ($startTime) {
            $this->bookingServices[0]['start_time'] = $startTime;
        }
        if ($providerId) {
            $this->bookingServices[0]['provider_id'] = $providerId;
        }
        $this->showBookingModal = true;
    }

    public function closeBookingModal() {
        $this->showBookingModal = false;
        $this->resetBookingForm();
    }

    public function openAppointmentModal(int $appointmentId) {
        $this->selectedAppointmentId = $appointmentId;
        $appointment = $this->selectedAppointment;
        if ($appointment) {
            $this->editStartTime      = $appointment->start_time->format('H:i');
            $this->editEndTime        = $appointment->end_time->format('H:i');
            $this->editDuration       = $appointment->duration_minutes;
            $this->editNotes          = $appointment->notes ?? '';
            $this->editProviderNotes  = $appointment->provider_notes ?? '';
        }
        $this->showAppointmentModal = true;
    }

    public function updatedEditStartTime(): void {
        $this->syncEditEndTimeFromDuration();
    }

    public function updatedEditDuration(): void {
        $this->syncEditEndTimeFromDuration();
    }

    public function updatedEditEndTime(): void {
        $this->syncEditDurationFromEndTime();
    }

    public function updatedAddServiceForm($value, string $key): void {
        if ($key === 'category_id') {
            $this->addServiceForm['service_id'] = null;
            $this->addServiceForm['duration_minutes'] = 0;
            $this->addServiceAnalysis = [];
            return;
        }

        if ($key !== 'service_id') {
            return;
        }

        if (empty($value)) {
            $this->addServiceForm['duration_minutes'] = 0;
            $this->addServiceAnalysis = [];
            return;
        }

        $service = Service::find($value);
        if (! $service) {
            $this->addServiceForm['duration_minutes'] = 0;
            $this->addServiceAnalysis = [];
            return;
        }

        $this->addServiceForm['duration_minutes'] = (int) $service->duration_minutes;
        $this->analyzeAddServiceGap();
    }

    public function closeAppointmentModal() {
        $this->showAppointmentModal = false;
        $this->selectedAppointmentId = null;
    }

    public function openPaymentModal(int $appointmentId) {
        $this->selectedAppointmentId = $appointmentId;
        $appointment = $this->selectedAppointment;
        if ($appointment) {
            $this->paymentAmount = (float) $appointment->total_amount;
            // Remember the suggested amount so processPayment() can tell whether
            // the staff actually lowered it (a discount) or left it untouched.
            $this->paymentBaseline = (float) $appointment->total_amount;
            $this->paymentType = '2';
        }
        $this->showAppointmentModal = false;
        $this->showPaymentModal = true;
    }

    public function closePaymentModal() {
        $this->showPaymentModal = false;
        $this->selectedAppointmentId = null;
    }

    public function openTimeOffModal() {
        $this->resetTimeOffForm();
        $this->timeOffStartDate = $this->selectedDate;
        $this->timeOffEndDate = $this->selectedDate;
        $this->showTimeOffModal = true;
    }

    public function openTimeOffModalFromTimeline(int $providerId, string $startTime, string $endTime) {
        $this->resetTimeOffForm();
        $this->timeOffProviderId = $providerId;
        $this->timeOffType = '0'; // Hourly
        $this->timeOffStartDate = $this->selectedDate;
        $this->timeOffEndDate = $this->selectedDate;
        $this->timeOffStartTime = $startTime;
        $this->timeOffEndTime = $endTime;
        $this->showTimeOffModal = true;
    }

    public function closeTimeOffModal() {
        $this->showTimeOffModal = false;
        $this->resetTimeOffForm();
    }

    public function updatedBookingServices($value, $key) {
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $index = (int) $parts[0];
            $field = $parts[1];

            if ($field === 'category_id') {
                $this->bookingServices[$index]['service_id'] = null;
                $this->bookingServices[$index]['provider_id'] = null;
                $this->bookingServices[$index]['start_time'] = '';
                $this->bookingServices[$index]['duration'] = 0;
            }

            if ($field === 'service_id' && $value) {
                $service = Service::find($value);
                if ($service) {
                    $this->bookingServices[$index]['duration'] = $service->duration_minutes;
                    $this->bookingServices[$index]['price'] = (float) ($service->discount_price ?? $service->price);
                }
                $this->bookingServices[$index]['provider_id'] = null;
                $this->bookingServices[$index]['start_time'] = '';
            }

            if ($field === 'start_time') {
                $this->bookingServices[$index]['provider_id'] = null;
            }
        }
    }

    public function addEmptyBookingService() {
        $this->bookingServices[] = [
            'category_id' => null,
            'service_id' => null,
            'provider_id' => null,
            'start_time' => '',
            'duration' => 0,
            'price' => 0,
        ];
    }

    public function removeBookingService(int $index) {
        if (count($this->bookingServices) > 1) {
            unset($this->bookingServices[$index]);
            $this->bookingServices = array_values($this->bookingServices);
        }
    }

    public function getAvailableSlotsForBookingService(int $index): array {
        $bs = $this->bookingServices[$index] ?? null;
        if (!$bs || !$bs['service_id'] || !$bs['provider_id']) {
            return [];
        }

        $rawSlots = $this->dashboardService->getAvailableSlotsForProvider(
            (int) $bs['service_id'],
            (int) $bs['provider_id'],
            $this->selectedDate
        );

        return array_map(function ($slot) {
            return is_array($slot) ? ($slot['start_time'] ?? '') : (string) $slot;
        }, $rawSlots);
    }

    public function getAvailableProvidersAtTime(int $index): array {
        $bs = $this->bookingServices[$index] ?? null;
        if (!$bs || !$bs['service_id'] || !$bs['start_time']) {
            return [];
        }

        return $this->dashboardService->getAvailableProvidersForServiceAtTime(
            (int) $bs['service_id'],
            $this->selectedDate,
            $bs['start_time'],
            (int) ($bs['duration'] ?: 30)
        );
    }

    public function getAvailableProvidersForBooking(int $serviceId, string $startTime, int $duration, bool $bypassAvailability = false): array {
        // Only honour the force flag for users who actually hold the permission,
        // so a forged request can never surface on-leave providers.
        $bypassAvailability = $bypassAvailability && $this->dashCan('force_booking');

        return $this->dashboardService->getAvailableProvidersForServiceAtTime(
            $serviceId,
            $this->selectedDate,
            $startTime,
            $duration ?: 30,
            $bypassAvailability
        );
    }

    public function saveBookingFromAlpine(array $data) {
        if ($this->dashDeny('create_booking')) {
            $this->dispatch('booking-error');
            return;
        }

        // Force booking: the client toggle is NEVER trusted on its own. Only
        // honour it after a server-side force_booking permission check; if the
        // toggle is on but the user lacks the ability, hard-stop here so a
        // forged request can never bypass the availability window.
        $bypassAvailability = (bool) ($data['bypassAvailability'] ?? false);
        if ($bypassAvailability && $this->dashDeny('force_booking')) {
            $this->dispatch('booking-error');
            return;
        }

        $validServices = array_filter($data['services'] ?? [], function ($bs) {
            return !empty($bs['service_id']) && !empty($bs['provider_id']) && !empty($bs['start_time']);
        });

        if (empty($validServices)) {
            $this->dispatch('notify', type: 'error', message: 'Please fill in all service details');
            $this->dispatch('booking-error');
            return;
        }

        try {
            $customer = null;
            $bookingData = [
                'date' => $this->selectedDate,
                'payment_method' => 'cash',
                'is_confirmed' => true,
                'mark_as_paid' => false,
                // Staff may record a walk-in that already started earlier today.
                'allow_same_day_past' => true,
                // Authorised force booking (validated above). Bypasses ONLY the
                // provider availability window; conflict + offers-service stay on.
                'bypass_availability' => $bypassAvailability,
                'override_reason' => $bypassAvailability ? trim((string) ($data['overrideReason'] ?? '')) ?: null : null,
                'notes' => $data['notes'] ?? '',
                'services' => [],
            ];

            if (($data['customerType'] ?? 'existing') === 'existing' && !empty($data['selectedCustomerId'])) {
                $customer = User::find($data['selectedCustomerId']);
                if (!$customer) {
                    $this->dispatch('notify', type: 'error', message: 'Selected customer not found');
                    $this->dispatch('booking-error');
                    return;
                }
                $bookingData['customer_name'] = $customer->full_name;
                $bookingData['customer_email'] = $customer->email;
                $bookingData['customer_phone'] = $customer->phone;
            } else {
                $bookingData['customer_name'] = $data['guestName'] ?? '';
                $bookingData['customer_phone'] = $data['guestPhone'] ?? '';
                $bookingData['customer_email'] = $data['guestEmail'] ?? '';
            }

            foreach ($validServices as $bs) {
                $bookingData['services'][] = [
                    'service_id' => (int) $bs['service_id'],
                    'provider_id' => (int) $bs['provider_id'],
                    'start_time' => $bs['start_time'],
                ];
            }

            $bookingService = app(BookingService::class);
            $appointment = $bookingService->createBooking($customer, $bookingData);

            $this->dispatch('booking-saved');
            $this->dispatch('notify', type: 'success', message: __('dashboard.booking_modal.save') . ' #' . $appointment->number);
        } catch (\Exception $e) {
            Log::error('Dashboard booking error: ' . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
            $this->dispatch('booking-error');
        }
    }

    public function saveBooking() {
        if ($this->dashDeny('create_booking')) {
            return;
        }

        $validServices = array_filter($this->bookingServices, function ($bs) {
            return $bs['service_id'] && $bs['provider_id'] && $bs['start_time'];
        });

        if (empty($validServices)) {
            $this->dispatch('notify', type: 'error', message: 'Please fill in all service details');
            return;
        }

        try {
            $customer = null;
            $bookingData = [
                'date' => $this->selectedDate,
                'payment_method' => 'cash',
                'is_confirmed' => true,
                'mark_as_paid' => false,
                'notes' => $this->bookingNotes,
                'services' => [],
            ];

            if ($this->customerType === 'existing' && $this->selectedCustomerId) {
                $customer = User::find($this->selectedCustomerId);
                $bookingData['customer_name'] = $customer->full_name;
                $bookingData['customer_email'] = $customer->email;
                $bookingData['customer_phone'] = $customer->phone;
            } else {
                $bookingData['customer_name'] = $this->guestName;
                $bookingData['customer_phone'] = $this->guestPhone;
                $bookingData['customer_email'] = $this->guestEmail;
            }

            foreach ($validServices as $bs) {
                $bookingData['services'][] = [
                    'service_id' => (int) $bs['service_id'],
                    'provider_id' => (int) $bs['provider_id'],
                    'start_time' => $bs['start_time'],
                ];
            }

            $bookingService = app(BookingService::class);
            $appointment = $bookingService->createBooking($customer, $bookingData);

            $this->closeBookingModal();
            $this->dispatch('notify', type: 'success', message: __('dashboard.booking_modal.save') . ' #' . $appointment->number);
            $this->dispatch('refreshTimeline');
        } catch (\Exception $e) {
            Log::error('Dashboard booking error: ' . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function updateAppointment() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::find($this->selectedAppointmentId);
        if (!$appointment) return;

        if ($this->dashDenyOnAppointment('edit_appointment', $appointment)) return;

        try {
            $newStart = Carbon::parse($this->selectedDate . ' ' . $this->editStartTime);
            $newEnd = $this->editEndTime
                ? Carbon::parse($this->selectedDate . ' ' . $this->editEndTime)
                : $newStart->copy()->addMinutes($this->editDuration);

            if ($newEnd->lte($newStart)) {
                throw new \InvalidArgumentException(__('dashboard.appointment_modal.invalid_end_time'));
            }

            $this->editDuration = (int) $newStart->diffInMinutes($newEnd);

            $appointment->update([
                'start_time' => $newStart,
                'end_time' => $newEnd,
                'duration_minutes' => $this->editDuration,
            ]);

            if ($appointment->services_record->count() > 0) {
                $firstService = $appointment->services_record->first();
                $firstService->update(['duration_minutes' => $this->editDuration]);
            }

            $this->closeAppointmentModal();
            $this->dispatch('notify', type: 'success', message: 'Appointment updated');
            $this->dispatch('refreshTimeline');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    private function syncEditEndTimeFromDuration(): void {
        if (empty($this->editStartTime) || $this->editDuration <= 0) {
            return;
        }

        try {
            $this->editEndTime = Carbon::parse($this->selectedDate . ' ' . $this->editStartTime)
                ->addMinutes($this->editDuration)
                ->format('H:i');
        } catch (\Throwable) {
            // Ignore partial input while the user is typing.
        }
    }

    private function syncEditDurationFromEndTime(): void {
        if (empty($this->editStartTime) || empty($this->editEndTime)) {
            return;
        }

        try {
            $start = Carbon::parse($this->selectedDate . ' ' . $this->editStartTime);
            $end = Carbon::parse($this->selectedDate . ' ' . $this->editEndTime);

            if ($end->gt($start)) {
                $this->editDuration = (int) $start->diffInMinutes($end);
            }
        } catch (\Throwable) {
            // Ignore partial input while the user is typing.
        }
    }

    public function updateNotes() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::find($this->selectedAppointmentId);
        if (!$appointment) return;

        if ($this->dashDenyOnAppointment('edit_notes', $appointment)) return;

        try {
            $appointment->update(['notes' => $this->editNotes]);
            $this->dispatch('notify', type: 'success', message: __('dashboard.appointment_modal.notes_saved'));
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Save the provider's professional notes on the appointment.
     */
    public function updateProviderNotes(): void
    {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::find($this->selectedAppointmentId);
        if (!$appointment) return;

        if ($this->dashDenyOnAppointment('edit_notes', $appointment)) return;

        try {
            $appointment->update(['provider_notes' => $this->editProviderNotes]);
            $this->dispatch('notify', type: 'success', message: __('dashboard.appointment_modal.provider_notes_saved'));
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Add or update a color entry on the current appointment.
     * Called from Alpine via: $wire.addColorToAppointment(colorId, quantity)
     */
    public function addColorToAppointment(int $colorId, float $quantity): void
    {
        if (!$this->selectedAppointmentId) return;

        if ($this->dashDenyOnAppointment('manage_colors', $this->selectedAppointment)) return;

        try {
            $existing = AppointmentColor::where('appointment_id', $this->selectedAppointmentId)
                ->where('color_id', $colorId)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $quantity]);
            } else {
                AppointmentColor::create([
                    'appointment_id' => $this->selectedAppointmentId,
                    'color_id'       => $colorId,
                    'quantity'       => $quantity,
                ]);
            }

            // Invalidate cached appointment details so the modal re-renders
            unset($this->selectedAppointment);

            $this->dispatch('color-added');
            $this->dispatch('notify', type: 'success', message: __('dashboard.appointment_modal.color_added'));
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Remove a color entry from the current appointment.
     * Called from Alpine via: $wire.removeColorFromAppointment(appointmentColorId)
     */
    public function removeColorFromAppointment(int $appointmentColorId): void
    {
        if (!$this->selectedAppointmentId) return;

        if ($this->dashDenyOnAppointment('manage_colors', $this->selectedAppointment)) return;

        try {
            AppointmentColor::where('id', $appointmentColorId)
                ->where('appointment_id', $this->selectedAppointmentId)
                ->delete();

            unset($this->selectedAppointment);

            $this->dispatch('color-removed');
            $this->dispatch('notify', type: 'success', message: __('dashboard.appointment_modal.color_removed'));
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function cancelAppointment() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::with('children')->find($this->selectedAppointmentId);
        if (!$appointment) return;

        if ($this->dashDenyOnAppointment('cancel_appointment', $appointment)) return;

        if (in_array($appointment->payment_status->value, [1, 2, 3]) || $appointment->status->value === 1) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.appointment_modal.cannot_cancel_paid'));
            return;
        }

        // Block cancelling a parent that still has active children.
        $check = $appointment->canBeCancelledOrDeleted();
        if (! $check['allowed']) {
            $numbers = implode(', #', $check['children_numbers'] ?? []);
            $this->dispatch(
                'notify',
                type: 'error',
                message: __('dashboard.cannot_cancel_has_children', ['numbers' => $numbers])
            );
            return;
        }

        try {
            $appointment->update([
                'status' => AppointmentStatus::ADMIN_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Cancelled by staff',
            ]);

            // If the cancelled appointment is a child → rebuild the parent's invoice
            // to remove its items.
            if ($appointment->is_child_booking && $appointment->parent) {
                try {
                    app(InvoiceService::class)->rebuildAggregatedInvoice($appointment->parent);
                } catch (\Throwable $e) {
                    Log::warning('Aggregated invoice rebuild after child cancel failed: ' . $e->getMessage());
                }
            }

            $this->closeAppointmentModal();
            $this->dispatch('notify', type: 'success', message: 'Appointment cancelled');
            $this->dispatch('refreshTimeline');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function deleteAppointment() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::with(['invoice', 'invoice.items', 'children', 'parent'])
            ->find($this->selectedAppointmentId);
        if (!$appointment) return;

        if ($this->dashDenyOnAppointment('delete_appointment', $appointment)) return;

        if (in_array($appointment->payment_status->value, [1, 2, 3]) || $appointment->status->value === 1) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.appointment_modal.cannot_delete_paid'));
            return;
        }

        // Block deleting a parent that still has active children.
        $check = $appointment->canBeCancelledOrDeleted();
        if (! $check['allowed']) {
            $numbers = implode(', #', $check['children_numbers'] ?? []);
            $this->dispatch(
                'notify',
                type: 'error',
                message: __('dashboard.cannot_delete_has_children', ['numbers' => $numbers])
            );
            return;
        }

        try {
            $parent = $appointment->parent; // capture before delete
            DB::transaction(function () use ($appointment) {
                if ($appointment->invoice) {
                    $appointment->invoice->items()->delete();
                    $appointment->invoice->payments()->delete();
                    $appointment->invoice->delete();
                }
                $appointment->services()->detach();
                $appointment->services_record()->delete();
                $appointment->delete();
            });

            // If the deleted appointment was a child → rebuild the parent's invoice.
            if ($parent) {
                try {
                    app(InvoiceService::class)->rebuildAggregatedInvoice($parent);
                } catch (\Throwable $e) {
                    Log::warning('Aggregated invoice rebuild after child delete failed: ' . $e->getMessage());
                }
            }

            $this->closeAppointmentModal();
            $this->dispatch('notify', type: 'success', message: 'Appointment deleted');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function processPayment() {
        if (!$this->selectedAppointmentId) return;

        // Load with parent + children so we can resolve the invoice owner and
        // finalize the whole linked group atomically.
        $appointment = Appointment::with(['invoice', 'parent.invoice', 'children'])
            ->find($this->selectedAppointmentId);
        if (!$appointment) return;

        if ($this->dashDenyOnAppointment('take_payment', $appointment)) return;

        try {
            // The unified invoice always lives on the parent (or self if standalone).
            $invoiceOwner = $appointment->parent ?? $appointment;
            $invoiceService = app(\App\Services\InvoiceService::class);
            $finalizationService = app(InvoiceFinalizationService::class);

            // Ensure a draft invoice exists on the owner.
            $invoice = $invoiceOwner->invoice()->first();
            if (!$invoice) {
                $invoice = $invoiceService->createDtaftInvoiceFromAppointment(
                    $invoiceOwner,
                    'cash',
                    0
                );
            }

            // CRITICAL: rebuild aggregated items BEFORE signing — this ensures
            // TSE signs the correct total covering parent + all children.
            $invoice = $invoiceService->rebuildAggregatedInvoice($invoiceOwner);

            // Apply the final amount through the single source of truth. If the
            // staff lowered the amount it is recorded as a discount (items total
            // is kept, discount_amount is stored, net/tax/total are reconciled on
            // the discounted gross). If they left the suggested amount untouched
            // we pass null = "charge the full items total" (no accidental discount
            // on aggregated invoices whose suggested amount was the parent only).
            $staffChangedAmount = abs($this->paymentAmount - $this->paymentBaseline) >= 0.005;
            $invoice = $invoiceService->applyFinalAmount(
                $invoice,
                $staffChangedAmount ? (float) $this->paymentAmount : null
            );

            $paymentTypeValue = (string) $this->paymentType;

            // finalizeDraftInvoice() now updates EVERY linked appointment
            // (parent + children) to COMPLETED + matching payment_status.
            // Pass the reconciled invoice total (post-discount) so the Payment
            // record + amount_paid always match what was actually charged.
            $finalizedInvoice = $finalizationService->finalizeDraftInvoice(
                $invoice,
                $paymentTypeValue,
                (float) $invoice->total_amount,
                null,
                true
            );

            $this->closePaymentModal();
            $this->dispatch('notify', type: 'success', message: __('dashboard.payment_modal.success'));

            if ($finalizedInvoice && $finalizedInvoice->invoice_number) {
                $this->dispatch('printInvoice', invoiceId: $finalizedInvoice->id);
            }
        } catch (\Exception $e) {
            Log::error('Payment error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function saveTimeOff() {
        if ($this->dashDeny('manage_timeoff')) return;

        // Providers may only add time off for themselves.
        if ($this->isCurrentUserProvider()) {
            $this->timeOffProviderId = $this->currentProviderId();
        }

        if (!$this->timeOffProviderId) {
            $this->dispatch('notify', type: 'error', message: 'Please select a provider');
            return;
        }

        if (!$this->timeOffReasonId) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.time_off_modal.reason_required'));
            return;
        }

        try {
            $data = [
                'user_id' => $this->timeOffProviderId,
                'type' => (int) $this->timeOffType,
                'start_date' => $this->timeOffStartDate,
                'end_date' => $this->timeOffEndDate ?: $this->timeOffStartDate,
                'reason_id' => $this->timeOffReasonId,
            ];

            if ((int) $this->timeOffType === ProviderTimeOff::TYPE_HOURLY) {
                $data['start_time'] = $this->timeOffStartTime;
                $data['end_time'] = $this->timeOffEndTime;
            }

            ProviderTimeOff::create($data);

            $this->closeTimeOffModal();
            $this->dispatch('notify', type: 'success', message: 'Time off added');
            $this->dispatch('refreshTimeline');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function saveTimeOffFromAlpine(array $data) {
        if ($this->dashDeny('manage_timeoff')) return;

        // Providers may only add time off for themselves.
        $providerId = $this->isCurrentUserProvider()
            ? $this->currentProviderId()
            : ($data['providerId'] ?? null);

        if (!$providerId) {
            $this->dispatch('notify', type: 'error', message: 'Please select a provider');
            return;
        }

        if (empty($data['reasonId'])) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.time_off_modal.reason_required'));
            return;
        }

        try {
            $timeOffData = [
                'user_id' => (int) $providerId,
                'type' => (int) ($data['type'] ?? 1),
                'start_date' => $data['startDate'] ?? now()->format('Y-m-d'),
                'end_date' => !empty($data['endDate']) ? $data['endDate'] : ($data['startDate'] ?? now()->format('Y-m-d')),
                'reason_id' => (int) $data['reasonId'],
            ];

            if ((int) ($data['type'] ?? 1) === ProviderTimeOff::TYPE_HOURLY) {
                $timeOffData['start_time'] = $data['startTime'] ?? '';
                $timeOffData['end_time'] = $data['endTime'] ?? '';
            }

            ProviderTimeOff::create($timeOffData);

            $this->dispatch('timeoff-saved');
            $this->dispatch('notify', type: 'success', message: 'Time off added');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ================================================================
    // Add Service to Existing Booking
    // ================================================================

    /**
     * Open the "Add Service" modal anchored to an existing appointment.
     */
    public function openAddServiceModal(int $appointmentId) {
        if ($this->dashDeny('add_service')) return;

        $appointment = Appointment::with(['parent', 'children', 'invoice', 'provider'])
            ->find($appointmentId);

        if (! $appointment) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.add_service.cannot_add'));
            return;
        }

        if (! $this->canActOnAppointment($appointment)) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.not_your_booking_denied'));
            return;
        }

        if (! $appointment->canAcceptNewService()) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.add_service.cannot_add'));
            return;
        }

        $this->addServiceToAppointmentId = $appointmentId;
        $this->addServiceForm = [
            'category_id' => null,
            'service_id' => null,
            'provider_id' => $appointment->provider_id, // default = same provider
            'placement' => 'after',
            'duration_minutes' => 0,
            'start_time' => $appointment->end_time->format('H:i'),
        ];
        $this->addServiceAnalysis = [];

        $this->showAppointmentModal = false;
        $this->showAddServiceModal = true;
    }

    public function closeAddServiceModal() {
        $this->showAddServiceModal = false;
        $this->addServiceToAppointmentId = null;
        $this->addServiceForm = [
            'category_id' => null,
            'service_id' => null,
            'provider_id' => null,
            'placement' => 'after',
            'duration_minutes' => 0,
            'start_time' => null,
        ];
        $this->addServiceAnalysis = [];
    }

    /**
     * Called by Alpine after the user edits any add-service form field.
     * Returns an analysis payload so the UI can show fit/push/reduction options.
     */
    public function analyzeAddServiceGap(): array {
        if (! $this->addServiceToAppointmentId) {
            return ['is_possible' => false, 'reason' => 'no_anchor'];
        }
        $anchor = Appointment::with(['parent', 'children', 'provider'])
            ->find($this->addServiceToAppointmentId);
        if (! $anchor) {
            return ['is_possible' => false, 'reason' => 'anchor_not_found'];
        }

        $form = $this->addServiceForm;
        if (empty($form['service_id']) || empty($form['provider_id']) || empty($form['duration_minutes'])) {
            return ['is_possible' => false, 'reason' => 'incomplete_form'];
        }

        try {
            $service = Service::find($form['service_id']);
            $provider = User::find($form['provider_id']);
            if (! $service || ! $provider) {
                return ['is_possible' => false, 'reason' => 'invalid_selection'];
            }

            $gap = app(GapAnalysisService::class);
            $duration = (int) $form['duration_minutes'];
            $sameProvider = ((int) $form['provider_id']) === ((int) $anchor->provider_id);

            // We DO NOT pass an explicit start_time here — the analyzer
            // computes the back-to-back default. The UI can override via
            // start_time when user types one manually; we keep behavior
            // simple by re-routing through addServiceToBooking() if needed.

            $result = $sameProvider
                ? ($form['placement'] === 'before'
                    ? $gap->analyzeAddBefore($anchor, $service, $duration, null, true)
                    : $gap->analyzeAddAfter($anchor, $service, $duration, null, true))
                : $gap->analyzeChildAdd(
                    $anchor->parent ?? $anchor,
                    $provider,
                    $service,
                    $duration,
                    $form['placement'],
                    null,
                    true
                );

            $this->addServiceAnalysis = $result;
            return $result;
        } catch (\Throwable $e) {
            Log::warning('analyzeAddServiceGap error: ' . $e->getMessage());
            return ['is_possible' => false, 'reason' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Apply the maximum-available duration suggested by the analysis.
     * Called when the user clicks "Reduce to max".
     */
    public function applyMaxDuration() {
        if (! empty($this->addServiceAnalysis['max_duration_available'])) {
            $this->addServiceForm['duration_minutes'] = (int) $this->addServiceAnalysis['max_duration_available'];
            $this->analyzeAddServiceGap();
        }
    }

    /**
     * Confirm and execute the add-service operation.
     * If push is required and applyPush=false → opens the push preview modal.
     */
    public function confirmAddService(bool $applyPush = false) {
        if (! $this->addServiceToAppointmentId) return;
        $anchor = Appointment::with(['parent', 'children', 'provider'])
            ->find($this->addServiceToAppointmentId);
        if (! $anchor) return;

        if ($this->dashDenyOnAppointment('add_service', $anchor)) return;

        try {
            $bookingService = app(BookingService::class);
            $result = $bookingService->addServiceToBooking($anchor, [
                ...$this->addServiceForm,
                'apply_push' => $applyPush,
                // Staff may back-date an added service within today.
                'allow_same_day_past' => true,
            ]);

            $this->closeAddServiceModal();
            $this->showPushPreviewModal = false;
            $this->pushPreviewPlan = [];

            $message = $result['mode'] === 'child_created'
                ? __('dashboard.add_service.child_created', ['number' => $result['appointment']->number])
                : __('dashboard.add_service.added');

            if (! empty($result['pushed_appointments'])) {
                $message .= ' ' . __('dashboard.add_service.pushed_count', ['count' => count($result['pushed_appointments'])]);
            }

            $this->dispatch('notify', type: 'success', message: $message);
            $this->dispatch('refreshTimeline');

        } catch (PushRequiredException $e) {
            // Surface the push preview to the user
            $this->pushPreviewPlan = $e->plan;
            $this->showPushPreviewModal = true;
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            Log::error('addServiceToBooking error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Called by Alpine when the user clicks "Confirm Push & Add Service"
     * in the push preview modal.
     */
    public function confirmPushAndAddService() {
        $this->confirmAddService(applyPush: true);
    }

    public function cancelPushPreview() {
        $this->showPushPreviewModal = false;
        $this->pushPreviewPlan = [];
    }

    /**
     * Print the unified invoice for a given appointment (parent, child, or standalone).
     * Always resolves to the parent's invoice when the appointment is a child.
     */
    public function printInvoiceForAppointment(int $appointmentId) {
        if ($this->dashDeny('print_invoice')) return;

        $appointment = Appointment::with(['invoice', 'parent.invoice'])
            ->find($appointmentId);
        if (! $appointment) return;

        $invoiceOwner = $appointment->parent ?? $appointment;
        $invoice = $invoiceOwner->invoice;

        if (! $invoice || ! $invoice->status->isPaid()) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.print.not_paid'));
            return;
        }

        $this->dispatch('printInvoice', invoiceId: $invoice->id);
    }

    /**
     * Print the operational order-ticket for a given appointment.
     * - Works for both PENDING and COMPLETED appointments.
     * - Blocked for cancelled appointments.
     * - For child appointments, the controller resolves to the parent's combined ticket.
     */
    public function printAppointmentTicket(int $appointmentId) {
        if ($this->dashDeny('print_ticket')) return;

        $appointment = Appointment::find($appointmentId);
        if (! $appointment) return;

        $cancelledStatuses = [
            \App\Enum\AppointmentStatus::USER_CANCELLED,
            \App\Enum\AppointmentStatus::ADMIN_CANCELLED,
        ];
        if (in_array($appointment->status, $cancelledStatuses, true)) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.print.order_cancelled'));
            return;
        }

        $this->dispatch('printAppointment', appointmentId: $appointment->id);
    }

    public function getTimelineData(): array {
        $allProviders = $this->allProviders;
        return $this->getTimelineDataFromProviders($allProviders);
    }

    public function getTimelineDataFromProviders($allProviders): array {
        $salonSchedule = $this->dashboardService->getSalonScheduleForDate($this->selectedDate);

        if (!$salonSchedule || !$salonSchedule->is_open) {
            return [
                'is_open' => false,
                'providers' => [],
                'appointments' => [],
                'time_offs' => [],
                'start_time' => '09:00',
                'end_time' => '21:00',
            ];
        }

        $startTime = substr($salonSchedule->open_time, 0, 5);
        $endTime = substr($salonSchedule->close_time, 0, 5);

        $providers = $allProviders
            ->filter(fn($provider) => $this->isProviderSelectableForTimeline($provider))
            ->filter(fn($provider) => in_array($provider['id'], $this->selectedProviderIds, true))
            ->values();

        // Provider scoping (single enforcement point — cannot be bypassed from
        // the client):
        //   - A provider without `view_team` is hard-limited to their own column.
        //   - Otherwise the optional "My bookings" toggle narrows to their column.
        // We select from the FULL provider list (not the work-day/selection
        // filtered one) so a provider always sees their own column under
        // "My bookings" — even on a day they are not scheduled to work.
        $forceMine = $this->isCurrentUserProvider() && ! $this->dashCan('view_team');
        if (($this->onlyMine || $forceMine) && $this->currentProviderId()) {
            $providers = $allProviders
                ->filter(fn($provider) => (int) $provider['id'] === $this->currentProviderId())
                ->values();
        }

        $visibleProviderIds = $providers->pluck('id')->all();

        $appointments = empty($visibleProviderIds)
            ? collect()
            : $this->dashboardService->getAppointmentsForDate($this->selectedDate, $visibleProviderIds);

        $timeOffs = empty($visibleProviderIds)
            ? collect()
            : $this->dashboardService->getTimeOffsForDate($this->selectedDate, $visibleProviderIds);

        $appointmentsByProvider = [];
        foreach ($appointments as $apt) {
            $pid = $apt->provider_id;
            if (!isset($appointmentsByProvider[$pid])) {
                $appointmentsByProvider[$pid] = [];
            }
            $services = $apt->services_record->map(fn($s) => $s->service_name)->implode(', ');
            $primaryServiceColor = $apt->services_record
                ->sortBy('sequence_order')
                ->first()?->service?->color_code;

            $serviceColorCode = is_string($primaryServiceColor) && preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryServiceColor)
                ? $primaryServiceColor
                : null;

            // Linked-group root id = parent_appointment_id ?? own id
            // Used by the SVG overlay in Blade to draw connecting lines.
            $linkedRootId = $apt->parent_appointment_id ?? $apt->id;

            $appointmentsByProvider[$pid][] = [
                'id' => $apt->id,
                'number' => $apt->number,
                'start_time' => $apt->start_time->format('H:i'),
                'end_time' => $apt->end_time->format('H:i'),
                'duration' => $apt->duration_minutes,
                'customer_name' => $apt->customer ? $apt->customer->full_name : ($apt->getRawOriginal('customer_name') ?: 'Guest'),
                'has_account' => (bool) $apt->customer_id,
                'services' => $services,
                'status' => $apt->status->value,
                'status_label' => $apt->status->getLabel(),
                'payment_status' => $apt->payment_status->value,
                'total_amount' => (float) $apt->total_amount,
                'service_color_code' => $serviceColorCode,
                // Force/override booking marker — drives the ⚡ badge on the card.
                'is_override' => (bool) $apt->is_override,
                // Whether this booking belongs to the logged-in provider (drives
                // the "not your booking" warning). Always true for admin/manager.
                'is_owned' => $this->currentProviderId() === null
                    ? true
                    : ((int) $apt->provider_id === $this->currentProviderId()),
                // ---- Linked / Push metadata ----
                'parent_appointment_id' => $apt->parent_appointment_id,
                'linked_group_root_id' => $linkedRootId,
                'is_child_booking' => $apt->parent_appointment_id !== null,
                'was_pushed' => (bool) $apt->was_pushed,
                'original_start_time' => $apt->original_start_time?->format('H:i'),
                'original_end_time' => $apt->original_end_time?->format('H:i'),
            ];
        }

        $timeOffsByProvider = [];
        foreach ($timeOffs as $to) {
            $pid = $to->user_id;
            if (!isset($timeOffsByProvider[$pid])) {
                $timeOffsByProvider[$pid] = [];
            }
            $timeOffsByProvider[$pid][] = [
                'id' => $to->id,
                'type' => $to->type,
                'start_time' => $to->type === ProviderTimeOff::TYPE_HOURLY ? ($to->start_time?->format('H:i') ?? '') : $startTime,
                'end_time' => $to->type === ProviderTimeOff::TYPE_HOURLY ? ($to->end_time?->format('H:i') ?? '') : $endTime,
                'reason' => $to->reason?->name ?? '',
            ];
        }

        return [
            'is_open' => true,
            'providers' => $providers->toArray(),
            'appointments' => $appointmentsByProvider,
            'time_offs' => $timeOffsByProvider,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    public function getCalendarData(): array {
        return $this->dashboardService->getBookingCountsForMonth($this->calendarYear, $this->calendarMonth);
    }

    public function getCategoriesProperty() {
        return $this->dashboardService->getCategories();
    }

    public function getServicesForCategory(int $categoryId) {
        return $this->dashboardService->getServicesByCategory($categoryId)->toArray();
    }

    public function getProvidersForService(int $serviceId) {
        return $this->dashboardService->getProvidersForService($serviceId)->toArray();
    }

    public function searchCustomers() {
        return $this->dashboardService->getCustomers($this->customerSearch)->toArray();
    }

    public function getReasonLeavesProperty() {
        return $this->dashboardService->getReasonLeaves();
    }

    private function syncSelectedProvidersForSelectedDate(): void {
        $this->selectedProviderIds = $this->getSelectableProviderIdsFromProviders(
            $this->allProviders
        );
    }

    private function getSelectableProviderIdsFromProviders($providers): array {
        return $providers
            ->filter(fn($provider) => $this->isProviderSelectableForTimeline($provider))
            ->pluck('id')
            ->map(fn($providerId) => (int) $providerId)
            ->values()
            ->all();
    }

    private function isProviderSelectableForTimeline(array $provider): bool {
        return (bool) ($provider['is_work_day'] ?? false) && !((bool) ($provider['has_day_off'] ?? false));
    }

    private function resetBookingForm() {
        $this->customerType = 'existing';
        $this->selectedCustomerId = null;
        $this->guestName = '';
        $this->guestPhone = '';
        $this->guestEmail = '';
        $this->customerSearch = '';
        $this->bookingNotes = '';
        $this->bookingServices = [];
        $this->addEmptyBookingService();
    }

    private function resetTimeOffForm() {
        $this->timeOffProviderId = null;
        $this->timeOffType = '1';
        $this->timeOffStartDate = '';
        $this->timeOffEndDate = '';
        $this->timeOffStartTime = '';
        $this->timeOffEndTime = '';
        $this->timeOffReasonId = null;
    }

    // ==================== Attendance (Check-in / Check-out) ====================

    /**
     * Recompute today's attendance snapshot. Called at page load (mount) and
     * after a check-in/out — never on the 5s poll, to keep request volume low.
     */
    private function refreshAttendanceState(): void {
        if (! $this->isCurrentUserProvider()) {
            $this->attendanceState = [];
            return;
        }

        $this->attendanceState = app(AttendanceService::class)->todayState($this->dashUser());
    }

    /**
     * Open the check-in confirmation modal: shows the current time and the
     * provider's last checkout time before they confirm.
     */
    public function openCheckInModal(): void {
        if (! $this->isCurrentUserProvider()) return;

        $svc = app(AttendanceService::class);
        $lastOut = $svc->lastCheckOut($this->dashUser());

        $this->checkInPreview = [
            'now'            => now()->format('H:i'),
            'now_date'       => now()->isoFormat('ddd, D MMM YYYY'),
            'last_out'       => $lastOut?->check_out_at?->format('Y-m-d H:i'),
            'last_out_human' => $lastOut?->check_out_at?->diffForHumans(),
        ];
        $this->showCheckInModal = true;
    }

    /**
     * Open the check-out confirmation modal: shows the open session's check-in
     * time and the resulting shift duration before they confirm.
     */
    public function openCheckOutModal(): void {
        if (! $this->isCurrentUserProvider()) return;

        $svc = app(AttendanceService::class);
        $open = $svc->openSession($this->dashUser());

        if (! $open) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.attendance.no_open_session'));
            return;
        }

        $minutes = (int) $open->check_in_at->diffInMinutes(now());

        $this->checkOutPreview = [
            'check_in'      => $open->check_in_at->format('H:i'),
            'check_in_date' => $open->check_in_at->isoFormat('ddd, D MMM YYYY'),
            'now'           => now()->format('H:i'),
            'duration'      => $this->formatAttendanceMinutes($minutes),
        ];
        $this->showCheckOutModal = true;
    }

    public function closeCheckInModal(): void  { $this->showCheckInModal = false; }
    public function closeCheckOutModal(): void { $this->showCheckOutModal = false; }

    /** Confirm buttons inside the modals. */
    public function confirmCheckIn(): void  { $this->checkIn();  $this->showCheckInModal = false; }
    public function confirmCheckOut(): void { $this->checkOut(); $this->showCheckOutModal = false; }

    /**
     * Open the attendance history popup (last 30 sessions, newest first).
     */
    public function openAttendanceHistoryModal(): void {
        if (! $this->isCurrentUserProvider()) return;

        $this->attendanceHistory = app(AttendanceService::class)
            ->recentSessions($this->dashUser(), 30)
            ->map(fn ($s) => [
                'date'     => $s->work_date?->format('Y-m-d'),
                'day'      => $s->work_date?->isoFormat('ddd'),
                'in'       => $s->check_in_at?->format('H:i'),
                'out'      => $s->check_out_at?->format('H:i'),
                'duration' => $s->duration_minutes !== null ? $this->formatAttendanceMinutes($s->duration_minutes) : null,
                'open'     => $s->check_out_at === null,
            ])
            ->all();

        $this->showAttendanceHistoryModal = true;
    }

    public function closeAttendanceHistoryModal(): void {
        $this->showAttendanceHistoryModal = false;
    }

    private function formatAttendanceMinutes(int $minutes): string {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? ($h . 'h ' . $m . 'm') : ($m . 'm');
    }

    /**
     * Open a new attendance session for the logged-in provider.
     */
    public function checkIn(): void {
        if (! $this->isCurrentUserProvider()) return;

        try {
            $result = app(AttendanceService::class)->checkIn($this->dashUser());
            $this->refreshAttendanceState();

            $this->dispatch('notify', type: 'success', message: __('dashboard.attendance.checked_in'));

            if ($result['outside_shift']) {
                $this->dispatch('notify', type: 'error', message: __('dashboard.attendance.outside_shift_warning'));
            }
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Close the provider's most recent open attendance session.
     */
    public function checkOut(): void {
        if (! $this->isCurrentUserProvider()) return;

        try {
            app(AttendanceService::class)->checkOut($this->dashUser());
            $this->refreshAttendanceState();

            $this->dispatch('notify', type: 'success', message: __('dashboard.attendance.checked_out'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    // ==================== Bulletin Board ====================

    /**
     * Post a new message to the board. Admin posts are pinned automatically.
     */
    public function addMessage() {
        if ($this->dashDeny('post_message')) return;

        try {
            $this->messageService->add(auth()->user(), $this->newMessageBody, $this->selectedDate, $this->newMessageExpiry);
            $this->newMessageBody = '';
            $this->newMessageExpiry = 'never';
            $this->dispatch('notify', type: 'success', message: __('dashboard.messages.posted'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('notify', type: 'error', message: collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * Soft-delete a message (history preserved). Admin: any; member: own only.
     */
    public function deleteMessage(int $messageId) {
        $deleted = $this->messageService->delete($messageId, auth()->user());

        if ($deleted) {
            $this->dispatch('notify', type: 'success', message: __('dashboard.messages.deleted'));
        } else {
            $this->dispatch('notify', type: 'error', message: __('dashboard.messages.delete_denied'));
        }
    }

    /**
     * Active messages for the selected day, formatted for the sidebar board.
     * Changing the calendar day reloads this list automatically (Livewire render).
     */
    private function getMessagesForView(): array {
        $actor = auth()->user();

        return $this->messageService->listActive($this->selectedDate)->map(function (DashboardMessage $message) use ($actor) {
            $authorName = $message->user?->full_name ?: __('dashboard.messages.unknown_author');

            return [
                'id'           => $message->id,
                'body'         => $message->body,
                'author_name'  => $authorName,
                'is_pinned'    => $message->is_pinned,
                'created_human' => $message->created_at?->diffForHumans() ?? '',
                'can_delete'   => $actor ? $this->messageService->canDelete($message, $actor) : false,
            ];
        })->all();
    }

    public function render() {
        $allProviders = $this->allProviders;
        $this->selectedProviderIds = array_values(array_intersect(
            $this->selectedProviderIds,
            $this->getSelectableProviderIdsFromProviders($allProviders)
        ));
        $timelineData = $this->getTimelineDataFromProviders($allProviders);
        $calendarCounts = $this->getCalendarData();

        return view('livewire.staff-dashboard', [
            'timelineData' => $timelineData,
            'calendarCounts' => $calendarCounts,
            'allProviders' => $allProviders,
            'activeLanguages' => $this->getActiveLanguages(),
            'selectedAppointment' => $this->selectedAppointment,
            'preloadedData' => $this->getPreloadedData(),
            'dashboardMessages' => $this->getMessagesForView(),
        ])->layout('layouts.dashboard');
    }

    // getActiveLanguages() provided by ProvidesDashboardChrome trait

    private function getPreloadedData(): array {
        return cache()->remember('dashboard_preloaded_data_' . app()->getLocale(), 60, function () {
            $serviceData = $this->dashboardService->getAllServicesGrouped();
            return [
                'categories' => $serviceData['categories'],
                'services'   => $serviceData['services'],
                'customers'  => $this->dashboardService->getAllCustomers(),
                'colors'     => $this->dashboardService->getAllColors(),
            ];
        });
    }
}
