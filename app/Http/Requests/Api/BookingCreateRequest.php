<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'services.*.service_id' => 'required|integer|exists:services,id',
            'services.*.provider_id' => 'required|integer|exists:users,id',
            'services.*.start_time' => 'required|date_format:H:i',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|string|in:cash,online',
        ];
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
            'services.*.service_id.exists' => 'الخدمة المحددة غير موجودة',
            'services.*.provider_id.required' => 'معرف مقدم الخدمة مطلوب',
            'services.*.provider_id.integer' => 'معرف مقدم الخدمة يجب أن يكون رقماً صحيحاً',
            'services.*.provider_id.exists' => 'مقدم الخدمة المحدد غير موجود',
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
