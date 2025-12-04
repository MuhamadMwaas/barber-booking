<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookingCreateRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
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
     * Create a new booking
     *
     * @param BookingCreateRequest $request
     * @return JsonResponse
     */
    public function store(BookingCreateRequest $request): JsonResponse
    {
        try {
            $customer = request()->user();

            $appointment = $this->bookingService->createBooking($customer, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => new AppointmentResource($appointment),
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
                'message' => 'An error occurred while creating the booking',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_type' => 'server_error',
            ], 500);
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
        try {
            $customer = auth()->user();
            $appointment = $this->bookingService->getBookingDetails($id, $customer);

            $reason = $request->input('cancellation_reason');
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
