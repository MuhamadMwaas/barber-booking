<?php

namespace App\Livewire;

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService as AppointmentServiceModel;
use App\Models\ProviderTimeOff;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingService;
use App\Services\BookingValidationService;
use App\Services\DashboardService;
use App\Services\InvoiceFinalizationService;
use App\Services\ServiceAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class StaffDashboard extends Component {
    public string $selectedDate;
    public array $selectedProviderIds = [];
    public int $calendarYear;
    public int $calendarMonth;

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
    public int $editDuration = 0;

    public float $paymentAmount = 0;
    public string $paymentType = '2';

    public ?int $timeOffProviderId = null;
    public string $timeOffType = '1';
    public string $timeOffStartDate = '';
    public string $timeOffEndDate = '';
    public string $timeOffStartTime = '';
    public string $timeOffEndTime = '';
    public ?int $timeOffReasonId = null;

    protected DashboardService $dashboardService;

    public function boot(DashboardService $dashboardService) {
        $this->dashboardService = $dashboardService;
    }

    public function mount() {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->calendarYear = (int) Carbon::today()->format('Y');
        $this->calendarMonth = (int) Carbon::today()->format('n');

        $providers = $this->dashboardService->getProviders();
        $this->selectedProviderIds = $providers->pluck('id')->toArray();

        $this->addEmptyBookingService();
    }

    public function selectDate(string $date) {
        $this->selectedDate = $date;
        $this->dispatch('dateChanged', date: $date);
    }

    public function goToToday() {
        $today = Carbon::today();
        $this->selectedDate = $today->format('Y-m-d');
        $this->calendarYear = (int) $today->format('Y');
        $this->calendarMonth = (int) $today->format('n');
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
        if (in_array($providerId, $this->selectedProviderIds)) {
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
        $appointment = $this->dashboardService->getAppointmentDetails($appointmentId);
        if ($appointment) {
            $this->editStartTime = $appointment->start_time->format('H:i');
            $this->editDuration = $appointment->duration_minutes;
        }
        $this->showAppointmentModal = true;
    }

    public function closeAppointmentModal() {
        $this->showAppointmentModal = false;
        $this->selectedAppointmentId = null;
    }

    public function openPaymentModal(int $appointmentId) {
        $this->selectedAppointmentId = $appointmentId;
        $appointment = $this->dashboardService->getAppointmentDetails($appointmentId);
        if ($appointment) {
            $this->paymentAmount = (float) $appointment->total_amount;
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

    public function getAvailableProvidersForBooking(int $serviceId, string $startTime, int $duration): array {
        return $this->dashboardService->getAvailableProvidersForServiceAtTime(
            $serviceId,
            $this->selectedDate,
            $startTime,
            $duration ?: 30
        );
    }

    public function saveBookingFromAlpine(array $data) {
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

        try {
            $newStart = Carbon::parse($this->selectedDate . ' ' . $this->editStartTime);
            $newEnd = $newStart->copy()->addMinutes($this->editDuration);

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

    public function cancelAppointment() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::find($this->selectedAppointmentId);
        if (!$appointment) return;

        if (in_array($appointment->payment_status->value, [1, 2, 3]) || $appointment->status->value === 1) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.appointment_modal.cannot_cancel_paid'));
            return;
        }

        try {
            $appointment->update([
                'status' => AppointmentStatus::ADMIN_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Cancelled by staff',
            ]);

            $this->closeAppointmentModal();
            $this->dispatch('notify', type: 'success', message: 'Appointment cancelled');
            $this->dispatch('refreshTimeline');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function deleteAppointment() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::with(['invoice', 'invoice.items'])->find($this->selectedAppointmentId);
        if (!$appointment) return;

        if (in_array($appointment->payment_status->value, [1, 2, 3]) || $appointment->status->value === 1) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.appointment_modal.cannot_delete_paid'));
            return;
        }

        try {
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

            $this->closeAppointmentModal();
            $this->dispatch('notify', type: 'success', message: 'Appointment deleted');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function processPayment() {
        if (!$this->selectedAppointmentId) return;

        $appointment = Appointment::with('invoice')->find($this->selectedAppointmentId);
        if (!$appointment) return;

        try {
            $finalizationService = app(InvoiceFinalizationService::class);

            $invoice = $appointment->invoice;

            if (!$invoice) {
                $invoiceService = app(\App\Services\InvoiceService::class);
                $invoice = $invoiceService->createDtaftInvoiceFromAppointment(
                    $appointment,
                    'cash',
                    0
                );
            }

            if ($this->paymentAmount != $invoice->total_amount) {
                $invoice->update([
                    'total_amount' => $this->paymentAmount,
                ]);
                $invoice->refresh();
            }

            $paymentTypeValue = (string) $this->paymentType;

            $finalizedInvoice = $finalizationService->finalizeDraftInvoice(
                $invoice,
                $paymentTypeValue,
                (float) $this->paymentAmount,
                null,
                true
            );

            $appointment->refresh();
            $appointment->update([
                'status' => AppointmentStatus::COMPLETED,
            ]);

            $this->closePaymentModal();
            $this->dispatch('notify', type: 'success', message: 'Payment processed');

            if ($finalizedInvoice && $finalizedInvoice->invoice_number) {
                $this->dispatch('printInvoice', invoiceId: $finalizedInvoice->id);
            }
        } catch (\Exception $e) {
            Log::error('Payment error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function saveTimeOff() {
        if (!$this->timeOffProviderId) {
            $this->dispatch('notify', type: 'error', message: 'Please select a provider');
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

    public function getTimelineData(): array {
        $allProviders = $this->dashboardService->getProvidersWithStatus($this->selectedDate);
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

        $appointments = $this->dashboardService->getAppointmentsForDate(
            $this->selectedDate,
            $this->selectedProviderIds
        );

        $timeOffs = $this->dashboardService->getTimeOffsForDate(
            $this->selectedDate,
            $this->selectedProviderIds
        );

        $providers = $allProviders
            ->filter(fn($p) => in_array($p['id'], $this->selectedProviderIds))
            ->values();

        $appointmentsByProvider = [];
        foreach ($appointments as $apt) {
            $pid = $apt->provider_id;
            if (!isset($appointmentsByProvider[$pid])) {
                $appointmentsByProvider[$pid] = [];
            }
            $services = $apt->services_record->map(fn($s) => $s->service_name)->implode(', ');
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

    public function render() {
        $allProviders = $this->dashboardService->getProvidersWithStatus($this->selectedDate);
        $timelineData = $this->getTimelineDataFromProviders($allProviders);
        $calendarCounts = $this->getCalendarData();

        $selectedAppointment = null;
        if ($this->selectedAppointmentId) {
            $selectedAppointment = $this->dashboardService->getAppointmentDetails($this->selectedAppointmentId);
        }

        return view('livewire.staff-dashboard', [
            'timelineData' => $timelineData,
            'calendarCounts' => $calendarCounts,
            'allProviders' => $allProviders,
            'selectedAppointment' => $selectedAppointment,
            'preloadedData' => $this->getPreloadedData(),
        ])->layout('layouts.dashboard');
    }

    private function getPreloadedData(): array {
        return cache()->remember('dashboard_preloaded_data', 60, function () {
            $serviceData = $this->dashboardService->getAllServicesGrouped();
            return [
                'categories' => $serviceData['categories'],
                'services' => $serviceData['services'],
                'customers' => $this->dashboardService->getAllCustomers(),
            ];
        });
    }
}
