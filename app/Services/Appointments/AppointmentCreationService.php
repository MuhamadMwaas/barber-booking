<?php

namespace App\Services\Appointments;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingValidationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentCreationService
{
    public function create(array $formData): Appointment
    {
        $services = $this->normalizeServices($formData['services_record'] ?? []);
        $formData['services_record'] = $services;

        $this->validate($formData);

        $appointmentData = $this->prepareAppointmentData($formData, $services);

        return DB::transaction(function () use ($appointmentData, $services) {
            $appointment = Appointment::create($appointmentData);

            foreach ($services as $index => $serviceData) {
                $serviceData['sequence_order'] ??= $index + 1;
                $appointment->services_record()->create($serviceData);
            }

            if (config('app.debug')) {
                Log::debug('AppointmentCreationService - Appointment created', [
                    'appointment_id' => $appointment->id,
                    'services_count' => count($services),
                ]);
            }

            return $appointment->load(['customer', 'provider', 'services_record']);
        });
    }

    public function validate(array $formData): void
    {
        $services = $this->normalizeServices($formData['services_record'] ?? []);
        $formData['services_record'] = $services;

        if (config('app.debug')) {
            Log::debug('AppointmentCreationService - validate data check', [
                'services_count' => count($services),
                'services_data' => $services,
            ]);
        }

        $this->validateFormData($formData);
        $this->performProfessionalValidations($formData);
    }

    public function normalizeServices(array $services): array
    {
        $services = array_values(array_filter($services, function ($item) {
            return ! empty($item) && is_array($item) && ! empty($item['service_id']);
        }));

        return array_map(function (array $service, int $index): array {
            $serviceModel = Service::find($service['service_id']);

            if (empty($service['service_name']) && $serviceModel) {
                $service['service_name'] = $serviceModel->name;
            }

            $service['sequence_order'] = $index + 1;

            return $service;
        }, $services, array_keys($services));
    }

    protected function prepareAppointmentData(array $data, array $services): array
    {
        unset($data['services_record']);

        $data = $this->prepareCustomerData($data);
        $data = $this->calculateTotalsFromServices($data, $services);
        $data = $this->prepareDateTimeFieldsFromServices($data, $services);

        return $data;
    }

    protected function validateFormData(array $formData): void
    {
        if (! isset($formData['services_record']) || ! is_array($formData['services_record'])) {
            throw new \InvalidArgumentException(__('messages.appointment.at_least_one_service'));
        }

        $services = $this->normalizeServices($formData['services_record']);

        if (count($services) === 0) {
            throw new \InvalidArgumentException(__('messages.appointment.at_least_one_service'));
        }

        $hasRegisteredCustomer = ! empty($formData['customer_id']);
        $hasGuestCustomer = ! empty(trim((string) ($formData['customer_name'] ?? '')))
            && ! empty(trim((string) ($formData['customer_phone'] ?? '')));

        if (! $hasRegisteredCustomer && ! $hasGuestCustomer) {
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

        $duration = $formData['duration_minutes'] ?? collect($services)->sum('duration_minutes');

        if ($duration <= 0) {
            throw new \InvalidArgumentException(__('messages.appointment.duration_greater_than_zero'));
        }
    }

    protected function performProfessionalValidations(array $formData): void
    {
        $validationService = app(BookingValidationService::class);
        $provider = User::findOrFail($formData['provider_id']);
        $customer = ! empty($formData['customer_id'])
            ? User::findOrFail($formData['customer_id'])
            : null;
        $date = Carbon::parse($formData['appointment_date'])->format('Y-m-d');

        $services = $this->normalizeServices($formData['services_record'] ?? []);
        $duration = $formData['duration_minutes'] ?? collect($services)->sum('duration_minutes');

        $startTime = Carbon::parse($date . ' ' . Carbon::parse($formData['start_time'])->format('H:i:s'));
        $endTime = $startTime->copy()->addMinutes($duration);

        $validationService->validateBasicData($services, $date);
        if ($customer) {
            $validationService->validateDailyBookingLimit($customer, $date);
        }

        $serviceIds = array_column($services, 'service_id');
        $serviceModels = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        foreach ($services as $serviceData) {
            $service = $serviceModels->get($serviceData['service_id']);

            if (! $service) {
                throw new \InvalidArgumentException(__('messages.appointment.service_not_found'));
            }

            $validationService->validateProviderOffersService($provider, $service);
        }

        $firstService = $serviceModels->first();
        $validationService->validateTimeSlotAvailability(
            $provider,
            $firstService,
            $startTime,
            $endTime,
        );

        if ($customer) {
            $validationService->validateNoDuplicateBooking($customer, $startTime, $serviceIds);
        } else {
            $validationService->validateNoDuplicateBookingByPhone(
                $formData['customer_phone'] ?? null,
                $startTime,
                $serviceIds,
            );
        }
    }

    protected function prepareCustomerData(array $data): array
    {
        if (! empty($data['customer_id'])) {
            $customer = User::find($data['customer_id']);

            if ($customer) {
                $data['customer_name'] = $customer->full_name;
                $data['customer_email'] = $customer->email;
                $data['customer_phone'] = $customer->phone;
            }

            return $data;
        }

        $data['customer_id'] = null;
        $data['customer_name'] = trim((string) ($data['customer_name'] ?? ''));
        $data['customer_phone'] = trim((string) ($data['customer_phone'] ?? ''));
        $data['customer_email'] = filled($data['customer_email'] ?? null)
            ? trim((string) $data['customer_email'])
            : null;

        return $data;
    }

    protected function calculateTotalsFromServices(array $data, array $services): array
    {
        $services = array_filter($services, function ($item) {
            return ! empty($item) && is_array($item) && isset($item['price']);
        });

        $subtotal = collect($services)->sum('price');
        $taxRate = (float) get_setting('tax_rate', 0);
        $taxAmount = $subtotal * ($taxRate / 100);

        $data['subtotal'] = round($subtotal, 2);
        $data['tax_amount'] = round($taxAmount, 2);
        $data['total_amount'] = round($subtotal + $taxAmount, 2);

        return $data;
    }

    protected function prepareDateTimeFieldsFromServices(array $data, array $services): array
    {
        $services = array_filter($services, function ($item) {
            return ! empty($item) && is_array($item) && isset($item['duration_minutes']);
        });

        $duration = $data['duration_minutes'] ?? collect($services)->sum('duration_minutes');

        if ($duration <= 0) {
            $duration = collect($services)->sum('duration_minutes');
        }

        $date = Carbon::parse($data['appointment_date'])->format('Y-m-d');
        $time = Carbon::parse($data['start_time'])->format('H:i:s');
        $startDateTime = Carbon::parse("{$date} {$time}");

        $data['duration_minutes'] = $duration;
        $data['start_time'] = $startDateTime;
        $data['appointment_date'] = $date;
        $data['end_time'] = $startDateTime->copy()->addMinutes($duration);

        return $data;
    }
}
