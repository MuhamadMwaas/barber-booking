<?php

namespace App\Http\Requests\Api;

use App\Enum\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AppointmentReminderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'appointment_id' => [
                'required',
                'integer',
                Rule::exists('appointments', 'id')->where(function ($query) {
                    $query->where('customer_id', $this->user()?->id);
                }),
            ],
            'remind_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => __('main.appointment.validation.appointment_id.required'),
            'appointment_id.integer' => __('main.appointment.validation.appointment_id.integer'),
            'appointment_id.exists' => __('main.appointment.validation.appointment_id.exists'),
            'remind_at.required' => __('main.appointment.validation.remind_at.required'),
            'remind_at.date' => __('main.appointment.validation.remind_at.date'),
            'remind_at.after' => __('main.appointment.validation.remind_at.after'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $appointmentId = $this->input('appointment_id');
            $remindAtInput = $this->input('remind_at');

            if (!$appointmentId || !$remindAtInput || !$this->user()) {
                return;
            }

            $appointment = Appointment::where('id', $appointmentId)
                ->where('customer_id', $this->user()->id)
                ->first();

            if (!$appointment) {
                return;
            }

            $statusValue = $appointment->status?->value ?? $appointment->status;
            if ($appointment->cancelled_at || in_array($statusValue, AppointmentStatus::getCancelledStatuses(), true)) {
                $validator->errors()->add(
                    'appointment_id',
                    __('main.appointment.validation.appointment_cancelled')
                );
                return;
            }

            if ($appointment->start_time <= now()) {
                $validator->errors()->add(
                    'appointment_id',
                    __('main.appointment.validation.appointment_past')
                );
            }

            try {
                $remindAt = Carbon::parse($remindAtInput);
                if ($remindAt->gte($appointment->start_time)) {
                    $validator->errors()->add(
                        'remind_at',
                        __('main.appointment.validation.remind_at.before_appointment')
                    );
                }
            } catch (\Throwable $e) {
                // Invalid date already handled by base validation.
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => __('main.validation.failed'),
                'errors' => $validator->errors(),
                'error_type' => 'validation_error',
            ], 422)
        );
    }
}
