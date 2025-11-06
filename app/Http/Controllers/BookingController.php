<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookingValidateRequest;
use App\Http\Requests\Api\BookingCreateRequest;
use App\Http\Resources\AppointmentResource;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Validate booking data before creating
     *
     * POST /api/bookings/validate
     *
     * @param BookingValidateRequest $request
     * @return JsonResponse
     */
    public function validate(BookingValidateRequest $request): JsonResponse
    {
        try {
            $customer = $request->user();

            $bookingData = [
                'services' => $request->input('services'),
                'date' => $request->input('date'),
            ];

            $validation = $this->bookingService->validateBooking($customer, $bookingData);

            return response()->json([
                'success' => true,
                'message' => 'تم التحقق من صحة بيانات الحجز بنجاح',
                'data' => $validation,
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'validation_error',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء التحقق من الحجز',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Create new booking
     *
     * POST /api/bookings/create
     *
     * @param BookingCreateRequest $request
     * @return JsonResponse
     */
    public function create(BookingCreateRequest $request): JsonResponse
    {
        try {
            $customer = $request->user();

            $bookingData = [
                'services' => $request->input('services'),
                'date' => $request->input('date'),
                'notes' => $request->input('notes'),
                'payment_method' => $request->input('payment_method'),
            ];

            $appointment = $this->bookingService->createBooking($customer, $bookingData);

            $responseMessage = $appointment->created_status
                ? 'تم إنشاء الحجز بنجاح'
                : 'تم إنشاء الحجز بنجاح. يرجى إكمال عملية الدفع لتأكيد الحجز';

            return response()->json([
                'success' => true,
                'message' => $responseMessage,
                'data' => new AppointmentResource($appointment),
                'requires_payment' => !$appointment->created_status,
                'payment_info' => !$appointment->created_status ? [
                    'appointment_id' => $appointment->id,
                    'amount' => $appointment->total_amount,
                    'currency' => 'EUR',
                ] : null,
            ], 201);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'validation_error',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الحجز',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Confirm payment and activate booking
     *
     * POST /api/bookings/{id}/confirm-payment
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmPayment(int $id, Request $request): JsonResponse
    {
        try {
            $customer = $request->user();

            $validated = $request->validate([
                'payment_transaction_id' => 'nullable|string|max:255',
                'payment_metadata' => 'nullable|array',
            ]);

            $appointment = $this->bookingService->confirmPayment(
                $id,
                $customer,
                $validated['payment_transaction_id'] ?? null,
                $validated['payment_metadata'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تأكيد الدفع وتفعيل الحجز بنجاح',
                'data' => new AppointmentResource($appointment),
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'business_error',
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'الحجز المطلوب غير موجود',
                'error_type' => 'not_found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تأكيد الدفع',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Get available time slots for multiple services
     *
     * GET /api/bookings/available-slots
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableSlots(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'services' => 'required|array|min:1',
                'services.*.service_id' => 'required|integer|exists:services,id',
                'services.*.provider_id' => 'required|integer|exists:users,id',
                'date' => 'required|date_format:Y-m-d',
            ]);

            $slots = $this->bookingService->getAvailableSlotsForMultipleServices(
                $validated['services'],
                $validated['date']
            );

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الأوقات المتاحة بنجاح',
                'data' => $slots,
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'validation_error',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الأوقات المتاحة',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }
}
