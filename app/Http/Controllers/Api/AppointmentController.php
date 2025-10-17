<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class AppointmentController extends Controller
{
    protected $appointmentService;




    /**

     *
     *  quyer list
     * - status: PENDING, COMPLETED, USER_CANCELLED, ADMIN_CANCELLED, ALL (default: ALL)
     * - payment_status: PENDING, PAID_ONLINE, PAID_ONSTIE_CASH, PAID_ONSTIE_CARD, FAILED, REFUNDED, PARTIALLY_REFUNDED
     * - date_from: Y-m-d
     * - date_to: Y-m-d
     * - type: upcoming, past
     * - sort_by: appointment_date, created_at, total_amount (default: appointment_date)
     * - sort_direction: asc, desc
     * - per_page: 1-100
     */
    public function index(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'status'            => 'nullable|string|in:PENDING,COMPLETED,USER_CANCELLED,ADMIN_CANCELLED,ALL',
                'payment_status'    => 'nullable|string|in:PENDING,PAID_ONLINE,PAID_ONSTIE_CASH,PAID_ONSTIE_CARD,FAILED,REFUNDED,PARTIALLY_REFUNDED',
                'date_from'         => 'nullable|date_format:Y-m-d',
                'date_to'           => 'nullable|date_format:Y-m-d',
                'type'              => 'nullable|string|in:upcoming,past',
                'sort_by'           => 'nullable|string|in:appointment_date,created_at,total_amount',
                'sort_direction'    => 'nullable|string|in:asc,desc',
                'per_page'          => 'nullable|integer|min:1|max:100',
            ]);


            $appointments = $this->appointmentService->getCustomerAppointments(
                auth()->user(),
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة الحجوزات بنجاح',
                'data' => AppointmentResource::collection($appointments)->response()->getData(true),
                'meta' => [
                    'current_page' => $appointments->currentPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'last_page' => $appointments->lastPage(),
                ],
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
                'message' => 'حدث خطأ أثناء جلب الحجوزات',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }


    public function show(int $id): JsonResponse
    {
        try {

            $appointment = $this->appointmentService->getAppointmentDetails(
                $id,
                request()->user()
            );


            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات الحجز بنجاح',
                'data' => new AppointmentResource($appointment),
            ], 200);

        } catch (InvalidArgumentException $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'access_error',
            ], 403);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'success' => false,
                'message' => 'الحجز المطلوب غير موجود',
                'error_type' => 'not_found',
            ], 404);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب بيانات الحجز',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }


    public function upcoming(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'days' => 'nullable|integer|min:1|max:90',
            ]);

            $days = $validated['days'] ?? 7;


            $appointments = $this->appointmentService->getUpcomingAppointments(
                auth()->user(),
                $days
            );


            return response()->json([
                'success' => true,
                'message' => "تم جلب الحجوزات المقبلة خلال {$days} أيام",
                'data' => AppointmentResource::collection($appointments),
                'count' => $appointments->count(),
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
                'message' => 'حدث خطأ أثناء جلب الحجوزات المقبلة',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }


    public function past(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $limit = $validated['limit'] ?? 10;


            $appointments = $this->appointmentService->getPastAppointments(
                auth()->user(),
                $limit
            );


            return response()->json([
                'success' => true,
                'message' => 'تم جلب الحجوزات السابقة',
                'data' => AppointmentResource::collection($appointments),
                'count' => $appointments->count(),
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
                'message' => 'حدث خطأ أثناء جلب الحجوزات السابقة',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }


    public function statistics(): JsonResponse
    {
        try {

            $stats = $this->appointmentService->getAppointmentStatistics(
                auth()->user()
            );


            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات الحجوزات',
                'data' => $stats,
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
                'message' => 'حدث خطأ أثناء جلب الإحصائيات',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }


    public function search(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'query' => 'required|string|min:2',
            ]);


            $appointments = $this->appointmentService->searchAppointments(
                auth()->user(),
                $validated['query']
            );


            return response()->json([
                'success' => true,
                'message' => 'تم البحث بنجاح',
                'data' => AppointmentResource::collection($appointments),
                'count' => $appointments->count(),
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
                'message' => 'حدث خطأ أثناء البحث',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }


    public function cancel(int $id, Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);


            $appointment = $this->appointmentService->cancelAppointment(
                $id,
                auth()->user(),
                $validated['reason'] ?? ''
            );


            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء الحجز بنجاح',
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
                'message' => 'حدث خطأ أثناء إلغاء الحجز',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }
}
