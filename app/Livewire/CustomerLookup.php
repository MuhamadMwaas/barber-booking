<?php

namespace App\Livewire;

use App\Livewire\Concerns\ProvidesDashboardChrome;
use App\Models\Appointment;
use App\Models\User;
use App\Services\CustomerLookupService;
use App\Services\DashboardService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CustomerLookup extends Component
{
    use ProvidesDashboardChrome;

    // ── Search State ─────────────────────────────────────────────────────────
    public string $search     = '';
    public bool   $searched   = false;

    // ── Results ───────────────────────────────────────────────────────────────
    public array $registeredResults  = [];
    public array $guestResults       = [];
    public bool  $registeredCapped   = false;  // hit the 25-result limit
    public bool  $guestCapped        = false;  // hit the 50-result limit

    // ── Navigation State ──────────────────────────────────────────────────────
    public ?int   $selectedCustomerId    = null;
    public ?array $selectedCustomerInfo  = null;
    public array  $customerAppointments  = [];

    // ── Detail Modal ──────────────────────────────────────────────────────────
    public ?int $selectedAppointmentId = null;

    // ── Injected Services (non-serialized) ───────────────────────────────────
    protected CustomerLookupService $lookupService;
    protected DashboardService      $dashboardService;

    public function boot(
        CustomerLookupService $lookupService,
        DashboardService      $dashboardService
    ): void {
        $this->lookupService    = $lookupService;
        $this->dashboardService = $dashboardService;
    }

    // ── Search ────────────────────────────────────────────────────────────────

    public function performSearch(): void
    {
        $q = trim($this->search);

        if (mb_strlen($q) < 2) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.customer_lookup.min_chars'));
            return;
        }

        $this->resetCustomerView();

        $registered = $this->lookupService->searchRegisteredCustomers($q, 25);
        $guests     = $this->lookupService->searchGuestAppointments($q, 50);

        $this->registeredCapped = $registered->count() === 25;
        $this->guestCapped      = $guests->count() === 50;

        $this->registeredResults = $registered->map(fn ($u) => [
            'id'                 => $u->id,
            'name'               => $u->full_name,
            'email'              => $u->email,
            'phone'              => $u->phone,
            'appointments_count' => $u->customer_appointments_count,
        ])->toArray();

        $this->guestResults = $guests->map(fn ($apt) => [
            'id'                   => $apt->id,
            'number'               => $apt->number,
            'customer_name'        => $apt->getRawOriginal('customer_name') ?: 'Guest',
            'customer_phone'       => $apt->getRawOriginal('customer_phone'),
            'customer_email'       => $apt->getRawOriginal('customer_email'),
            'appointment_date_fmt' => $apt->appointment_date?->format('d M Y'),
            'start_time'           => $apt->start_time?->format('H:i'),
            'provider_name'        => $apt->provider?->full_name,
            'services_summary'     => $apt->services_record
                                         ->sortBy('sequence_order')
                                         ->pluck('service_name')
                                         ->implode(', '),
            'has_notes'            => ! empty($apt->getRawOriginal('notes')) || ! empty($apt->provider_notes),
            'colors_count'         => $apt->color_records_count,
            'status'               => $apt->status->value,
            'status_label'         => $apt->status->getLabel(),
        ])->toArray();

        $this->searched = true;
    }

    // ── Customer Selection ────────────────────────────────────────────────────

    public function selectCustomer(int $userId): void
    {
        $user = User::find($userId, ['id', 'first_name', 'last_name', 'email', 'phone']);
        if (! $user) {
            return;
        }

        $this->selectedCustomerId   = $userId;
        $this->selectedAppointmentId = null;

        $this->selectedCustomerInfo = [
            'id'    => $user->id,
            'name'  => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        $appointments = $this->lookupService->getCustomerAppointments($userId);

        $this->customerAppointments = $appointments->map(fn ($apt) => [
            'id'                   => $apt->id,
            'number'               => $apt->number,
            'appointment_date_fmt' => $apt->appointment_date?->format('d M Y'),
            'start_time'           => $apt->start_time?->format('H:i'),
            'end_time'             => $apt->end_time?->format('H:i'),
            'provider_name'        => $apt->provider?->full_name,
            'services_summary'     => $apt->services_record
                                         ->sortBy('sequence_order')
                                         ->pluck('service_name')
                                         ->implode(', '),
            'total_amount'         => (float) $apt->total_amount,
            'has_notes'            => ! empty($apt->getRawOriginal('notes')) || ! empty($apt->provider_notes),
            'colors_count'         => $apt->color_records_count,
            'status'               => $apt->status->value,
            'status_label'         => $apt->status->getLabel(),
            'payment_status'       => $apt->payment_status->value,
        ])->toArray();
    }

    public function backToResults(): void
    {
        $this->resetCustomerView();
    }

    // ── Appointment Detail ────────────────────────────────────────────────────

    public function viewAppointment(int $appointmentId): void
    {
        $this->selectedAppointmentId = $appointmentId;
        unset($this->selectedAppointment);
    }

    public function closeAppointment(): void
    {
        $this->selectedAppointmentId = null;
        unset($this->selectedAppointment);
    }

    #[Computed]
    public function selectedAppointment(): ?Appointment
    {
        if (! $this->selectedAppointmentId) {
            return null;
        }
        return app(DashboardService::class)->getAppointmentDetails($this->selectedAppointmentId);
    }

    // ── Clear ─────────────────────────────────────────────────────────────────

    public function clearSearch(): void
    {
        $this->search   = '';
        $this->searched = false;
        $this->resetCustomerView();
        $this->registeredResults = [];
        $this->guestResults      = [];
        $this->registeredCapped  = false;
        $this->guestCapped       = false;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.customer-lookup', [
            'activeLanguages'     => $this->getActiveLanguages(),
            'selectedAppointment' => $this->selectedAppointment,
        ])->layout('layouts.dashboard');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resetCustomerView(): void
    {
        $this->selectedCustomerId    = null;
        $this->selectedCustomerInfo  = null;
        $this->customerAppointments  = [];
        $this->selectedAppointmentId = null;
        unset($this->selectedAppointment);
    }
}
