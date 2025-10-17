<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ServiceAvailabilityService;
use Illuminate\Http\JsonResponse;
class AvailabilityController extends Controller
{

    protected $availabilityService;

    public function __construct(ServiceAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getProviderAvailability(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'service_id' => 'required|integer|exists:services,id',
                'provider_id' => 'required|integer|exists:users,id',
                'date' => 'required|date_format:Y-m-d|after_or_equal:today',
                'branch_id'   => 'nullable|integer'
            ]);

            $serviceId = $request->get('service_id');
            $providerId = $request->get('provider_id');
            $date = $request->get('date');

            $availability = $this->availabilityService->getProviderAvailableSlotsByDate(
                $serviceId,
                $providerId,
                $date
            );

            return response()->json([
                'success' => true,
                'data' => $availability,
                'message' => 'Provider availability retrieved successfully'
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve provider availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailabilityCalendar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'service_id' => 'required|integer|exists:services,id',
                'provider_id'=> 'nullable|integer|exists:users,id',
                'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
                'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
                'branch_id'  => 'nullable|integer|exists:branchs,id',
            ]);

            $serviceId = $request->get('service_id');
            $providerId = $request->get('provider_id');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $branchId = $request->get('branch_id');

            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            if ($start->diffInDays($end) > 31) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 31 days'
                ], 400);
            }

            $calendar = $this->availabilityService->getAvailabilityCalendar(
                $serviceId,
                $providerId,
                $startDate,
                $endDate,
                $branchId
            );

            return response()->json([
                'success' => true,
                'data' => $calendar,
                'message' => 'Availability calendar retrieved successfully'
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve availability calendar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
