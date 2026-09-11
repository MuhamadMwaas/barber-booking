<?php

namespace App\Http\Requests\Api;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class BookingCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'services' => 'required|array|min:1|max:10',
            'services.*.service_id' => ['required', 'integer', $this->liveServiceExistsRule()],
            'services.*.provider_id' => [
                'required',
                'integer',
                $this->activeProviderExistsRule(),
            ],
            'services.*.start_time' => 'required|date_format:H:i',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|string|in:cash,online',

            /*
             * Optional reminder lead time, in hours before the appointment.
             *
             * The reminder toggle lives on the booking screen itself, so letting
             * the booking call carry it spares the app a second round trip whose
             * failure would leave the customer believing they set a reminder
             * they did not get. Omitting it books without a reminder; the
             * dedicated /appointments/reminders endpoints change or remove it
             * afterwards.
             *
             * Validated against the same list as that endpoint — one source of
             * truth for "which lead times exist" (config/appointment_reminders).
             */
            'reminder_offset_hours' => [
                'nullable',
                'integer',
                Rule::in(array_map('intval', config('appointment_reminders.offset_hours', []))),
            ],
        ];
    }

    /**
     * Accept only services that have not been soft-deleted.
     *
     * `exists:services,id` runs a raw table query, so it has no idea the model
     * uses SoftDeletes and happily matches a deleted row. BookingService then
     * loads the same id through Eloquent, the global scope removes it, and a null
     * reaches a typed parameter — a 500 instead of a validation error (BOOK-09).
     * Deleting a service from the panel was enough to trigger it.
     */
    private function liveServiceExistsRule(): Exists
    {
        return Rule::exists('services', 'id')->whereNull('deleted_at');
    }

    /**
     * Accept only active users carrying the provider role.
     *
     * The model_type predicate matters because Spatie's role pivot is polymorphic:
     * a different model may legitimately have the same numeric model_id.
     */
    private function activeProviderExistsRule(): Exists
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $modelKey = config('permission.column_names.model_morph_key', 'model_id');
        $userMorphClass = (new User)->getMorphClass();

        return Rule::exists('users', 'id')->where(
            function (Builder $query) use (
                $rolesTable,
                $modelHasRolesTable,
                $roleKey,
                $modelKey,
                $userMorphClass,
            ): void {
                $query
                    // Same soft-delete trap as the service rule above.
                    ->whereNull('users.deleted_at')
                    ->where('users.is_active', true)
                    ->whereExists(function (Builder $roles) use (
                        $rolesTable,
                        $modelHasRolesTable,
                        $roleKey,
                        $modelKey,
                        $userMorphClass,
                    ): void {
                        $roles
                            ->selectRaw('1')
                            ->from("{$modelHasRolesTable} as booking_role_assignments")
                            ->join(
                                "{$rolesTable} as booking_roles",
                                'booking_roles.id',
                                '=',
                                "booking_role_assignments.{$roleKey}",
                            )
                            ->whereColumn("booking_role_assignments.{$modelKey}", 'users.id')
                            ->where('booking_role_assignments.model_type', $userMorphClass)
                            ->where('booking_roles.name', 'provider');
                    });
            },
        );
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'services.required' => 'يجب تحديد خدمة واحدة على الأقل',
            'services.array' => 'يجب أن تكون الخدمات عبارة عن مصفوفة',
            'services.min' => 'يجب تحديد خدمة واحدة على الأقل',
            'services.max' => 'لا يمكن تحديد أكثر من 10 خدمات',
            'services.*.service_id.required' => 'معرف الخدمة مطلوب',
            'services.*.service_id.integer' => 'معرف الخدمة يجب أن يكون رقماً صحيحاً',
            // Same message as the provider rule below, on purpose: distinguishing
            // "this service does not exist" from "this pair is unavailable" turns
            // the endpoint into a way to enumerate real service ids. The specific
            // reason is logged, never returned — see
            // BookingValidationService::rejectProviderServicePair().
            'services.*.service_id.exists' => __('booking.provider_unavailable_for_service'),
            'services.*.provider_id.required' => 'معرف مقدم الخدمة مطلوب',
            'services.*.provider_id.integer' => 'معرف مقدم الخدمة يجب أن يكون رقماً صحيحاً',
            'services.*.provider_id.exists' => __('booking.provider_unavailable_for_service'),
            'services.*.start_time.required' => 'وقت البدء مطلوب',
            'services.*.start_time.date_format' => 'وقت البدء يجب أن يكون بصيغة HH:MM',
            'date.required' => 'تاريخ الحجز مطلوب',
            'date.date_format' => 'تاريخ الحجز يجب أن يكون بصيغة Y-m-d',
            'date.after_or_equal' => 'لا يمكن الحجز في تاريخ سابق',
            'notes.string' => 'الملاحظات يجب أن تكون نصاً',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'payment_method.string' => 'طريقة الدفع يجب أن تكون نصاً',
            'payment_method.in' => 'طريقة الدفع يجب أن تكون cash أو online',
            'reminder_offset_hours.integer' => __('main.appointment.validation.offset_hours.integer'),
            'reminder_offset_hours.in' => __('main.appointment.validation.offset_hours.in'),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'بيانات غير صحيحة',
                'errors' => $validator->errors(),
                'error_type' => 'validation_error',
            ], 422)
        );
    }
}
