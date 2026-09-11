<?php

namespace App\Http\Requests\Api;

use App\Enum\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates a request to set (or change) an appointment's reminder.
 *
 * TWO WAYS TO SAY WHEN, ONE OF THEM PREFERRED:
 *
 *  - `offset_hours` (preferred) — a lead time from the allowed list. The server
 *    derives the instant from the appointment's own start_time, so the answer is
 *    the same no matter what timezone the client thinks it is in.
 *  - `remind_at` (legacy) — an absolute datetime. Kept working so an already
 *    published build of the app does not break, but it carries a real hazard:
 *    `APP_TIMEZONE` is Asia/Baghdad while the salon runs Berlin hours, so a naive
 *    `09:00` and a `09:00Z` are hours apart. New clients should send
 *    `offset_hours`.
 *
 * Exactly one is required; sending both is rejected rather than silently
 * resolved, because guessing which one the client meant is how a reminder ends
 * up firing at a time nobody asked for.
 */
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
                // Scoped to the caller's own appointments: the rule doubles as
                // the ownership check, so a probe for someone else's booking id
                // is indistinguishable from a nonexistent one.
                //
                // No `whereNull('deleted_at')` here, unlike the service and
                // provider rules in BookingCreateRequest: `appointments` does not
                // soft-delete and has no such column, so the guard the BOOK-09
                // note prescribes for soft-deleting tables would filter against a
                // column that isn't there.
                Rule::exists('appointments', 'id')->where(function ($query) {
                    $query->where('customer_id', $this->user()?->id);
                }),
            ],
            'offset_hours' => [
                'required_without:remind_at',
                'nullable',
                'integer',
                Rule::in($this->allowedOffsetHours()),
            ],
            'remind_at' => [
                'required_without:offset_hours',
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function allowedOffsetHours(): array
    {
        return array_values(array_map(
            'intval',
            config('appointment_reminders.offset_hours', [1, 2, 3, 4, 5, 6, 24])
        ));
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => __('main.appointment.validation.appointment_id.required'),
            'appointment_id.integer' => __('main.appointment.validation.appointment_id.integer'),
            'appointment_id.exists' => __('main.appointment.validation.appointment_id.exists'),
            'offset_hours.required_without' => __('main.appointment.validation.offset_hours.required'),
            'offset_hours.integer' => __('main.appointment.validation.offset_hours.integer'),
            'offset_hours.in' => __('main.appointment.validation.offset_hours.in'),
            'remind_at.required_without' => __('main.appointment.validation.offset_hours.required'),
            'remind_at.date' => __('main.appointment.validation.remind_at.date'),
            'remind_at.after' => __('main.appointment.validation.remind_at.after'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Refuse an ambiguous request instead of picking a winner.
            if ($this->filled('offset_hours') && $this->filled('remind_at')) {
                $validator->errors()->add(
                    'offset_hours',
                    __('main.appointment.validation.offset_hours.conflict')
                );
                return;
            }

            $appointmentId = $this->input('appointment_id');
            if (! $appointmentId || ! $this->user()) {
                return;
            }

            $appointment = Appointment::where('id', $appointmentId)
                ->where('customer_id', $this->user()->id)
                ->first();

            // Null here means the `exists` rule already failed, or the row was
            // soft-deleted between the two queries. Either way the rule reports
            // it — re-reporting would leak which of the two happened.
            if (! $appointment) {
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
                return;
            }

            // An offset is validated against the appointment, not the clock: a
            // 24-hour lead time on a booking made this afternoon would land in
            // the past, and the customer should be told that rather than have a
            // reminder scheduled that can never fire.
            if ($this->filled('offset_hours')) {
                $remindAt = $appointment->start_time->copy()->subHours((int) $this->input('offset_hours'));

                if ($remindAt->lte(now())) {
                    $validator->errors()->add(
                        'offset_hours',
                        __('main.appointment.validation.offset_hours.too_late')
                    );
                }

                return;
            }

            try {
                $remindAt = Carbon::parse($this->input('remind_at'));
                if ($remindAt->gte($appointment->start_time)) {
                    $validator->errors()->add(
                        'remind_at',
                        __('main.appointment.validation.remind_at.before_appointment')
                    );
                }
            } catch (\Throwable $e) {
                // An unparseable date is already reported by the `date` rule.
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
