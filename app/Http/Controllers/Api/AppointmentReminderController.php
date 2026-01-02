<?php

namespace App\Http\Controllers\Api;

use App\Enum\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AppointmentReminderStoreRequest;
use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class AppointmentReminderController extends Controller
{
    public function __construct(
        protected AppointmentReminderService $reminderService
    ) {
    }

    public function store(AppointmentReminderStoreRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $remindAt = Carbon::parse($request->validated('remind_at'));

            // ========== validate appointment ownership ==========

            $remindAtInput = $remindAt;

            $appointment = Appointment::where('id', $request->validated('appointment_id'))
            ->where('customer_id', $user->id)
            ->firstOrFail();

            $statusValue = $appointment->status?->value ?? $appointment->status;

            if ($appointment->cancelled_at || in_array($statusValue, AppointmentStatus::getCancelledStatuses(), true)) {
                throw new InvalidArgumentException(__('main.appointment.errors.cancelled'));

            }

            if ($appointment->start_time <= now()) {
                throw new InvalidArgumentException(__('main.appointment.errors.past'));

            }
            // ====================================================

            $params = [
                'date' => [
                    'type' => 'value',
                    'value' => $appointment->start_time->format('Y-m-d'),
                ],
                'time' => [
                    'type' => 'value',
                    'value' => $appointment->start_time->format('H:i'),
                ],
                'number' => [
                    'type' => 'value',
                    'value' => $appointment->number,
                ],
            ];

            $data = [
                'type' => 'appointment_reminder',
                'appointment_id' => $appointment->id,
            ];
            

            $reminder = $this->reminderService->rescheduleReminder(
                $appointment,
                $remindAt,
                $params,
                $data
            );

            return response()->json([
                'success' => true,
                'message' =>__('main.appointment.success.reminder_created'),
                'data' => [
                    'reminder_id' => $reminder->id,
                    'appointment_id' => $appointment->id,
                    'remind_at' => $reminder->remind_at->toIso8601String(),
                    'status' => $reminder->status,
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'business_error',
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.appointment.errors.not_found'),
                'error_type' => 'not_found',
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.appointment.server_error'),
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }
}
