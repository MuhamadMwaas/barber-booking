<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookingCreateRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Exceptions\SlotUnavailableException;
use InvalidArgumentException;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Create a new booking
     *
     * @param BookingCreateRequest $request
     * @return JsonResponse
     */
    public function store(BookingCreateRequest $request): JsonResponse
    {
        try {
            $customer = request()->user();

            // الحجوزات عبر API دائماً Online بغض النظر عن طريقة الدفع
            $bookingData = $request->validated();
            $bookingData['booking_source'] = 'online';

            // Pulled out before the service sees it: the reminder is scheduled
            // against the appointment that comes back, so BookingService has no
            // use for it and should not have to learn about reminders at all.
            $reminderOffsetHours = $bookingData['reminder_offset_hours'] ?? null;
            unset($bookingData['reminder_offset_hours']);

            $appointment = $this->bookingService->createBooking($customer, $bookingData);

            if ($reminderOffsetHours !== null) {
                $this->scheduleReminderForBooking($appointment, (int) $reminderOffsetHours);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => new AppointmentResource($appointment->load('activeReminder')),
            ], 201);

        } catch (SlotUnavailableException $e) {
            // Not a validation failure: the request was legal and the slot was
            // free when the customer saw it — somebody else booked it first.
            // 409 lets the app tell those two cases apart and refresh the slots
            // instead of blaming the customer's input (BOOK-02).
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'slot_conflict',
            ], 409);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'validation_error',
            ], 422);

        } catch (\Throwable $e) {
            // Throwable, not Exception: PHP's TypeError extends Error, so a typed
            // parameter receiving null slipped past `catch (\Exception)` entirely
            // and surfaced as an unhandled 500 with no envelope (BOOK-09). The
            // guards upstream should keep that from happening — this is the net
            // under them, and it logs so a real defect is never silently dressed
            // up as an ordinary failure.
            Log::error('Booking creation failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the booking',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Attach the reminder the customer chose on the booking screen.
     *
     * A FAILURE HERE MUST NOT COST THE BOOKING. The slot is already held and the
     * customer already has it; losing that because a queue insert failed would
     * trade a real, contended resource for a convenience. So the reminder is
     * scheduled after createBooking() rather than inside its transaction, and any
     * failure is logged and swallowed — the response then carries
     * `reminder: null`, which is exactly what the app needs to see to offer the
     * customer a retry through POST /api/appointments/reminders.
     *
     * The trade-off is deliberate and narrow: the booking is the thing that
     * cannot be recreated, the reminder is one tap away.
     */
    protected function scheduleReminderForBooking(Appointment $appointment, int $offsetHours): void
    {
        try {
            $reminderService = app(AppointmentReminderService::class);

            $remindAt = $reminderService->remindAtFromOffset($appointment, $offsetHours);

            // A lead time longer than the notice the customer gave us lands in
            // the past — booking at 09:00 for 10:00 with a 24-hour reminder. Not
            // an error: the booking is fine, there is simply no moment left to
            // remind them at.
            if ($remindAt->lte(now())) {
                Log::info('Booking reminder skipped: lead time already elapsed', [
                    'appointment_id' => $appointment->id,
                    'offset_hours' => $offsetHours,
                ]);
                return;
            }

            [$params, $data] = $reminderService->buildPayload($appointment);

            $reminderService->rescheduleReminder($appointment, $remindAt, $params, $data);
        } catch (\Throwable $e) {
            Log::error('Booking succeeded but its reminder could not be scheduled', [
                'appointment_id' => $appointment->id,
                'offset_hours' => $offsetHours,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get customer bookings
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = auth()->user();
            $status = $request->query('status');

            $bookings = $this->bookingService->getCustomerBookings($customer, $status);

            return response()->json([
                'success' => true,
                'message' => 'Bookings retrieved successfully',
                'data' => AppointmentResource::collection($bookings),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving bookings',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Get booking details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $customer = auth()->user();
            $appointment = $this->bookingService->getBookingDetails($id, $customer);

            return response()->json([
                'success' => true,
                'message' => 'Booking details retrieved successfully',
                'data' => new AppointmentResource($appointment),
            ], 200);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'authorization_error',
            ], 403);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving booking details',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }

    /**
     * Cancel a booking
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        // Validated OUTSIDE the try: ValidationException is an Exception, so the
        // generic catch below would turn a 422 into a 500. The sibling endpoint
        // /api/appointments/{id}/cancel has always bounded this field; here the
        // reason went into a TEXT column unchecked.
        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        try {
            $customer = auth()->user();
            $appointment = $this->bookingService->getBookingDetails($id, $customer);

            $reason = $validated['cancellation_reason'] ?? null;
            $this->bookingService->cancelBooking($appointment, $reason);

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => new AppointmentResource($appointment->fresh()),
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
                'message' => 'An error occurred while cancelling the booking',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
        }
    }
}
