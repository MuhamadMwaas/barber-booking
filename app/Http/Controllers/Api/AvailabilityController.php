<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ServiceAvailabilityService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
class AvailabilityController extends Controller
{

    protected $availabilityService;

    public function __construct(ServiceAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Providers who can actually take this service on a given day.
     *
     * The counterpart to {@see getProviderAvailability()}: instead of asking
     * "is provider X free?", the app asks "who is free?" and gets back only the
     * providers with at least one bookable slot, each with their own slots and
     * pricing — one request instead of one per provider.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getServiceAvailability(Request $request): JsonResponse
    {
        // Validated OUTSIDE the try: `validate()` throws ValidationException, and
        // the generic `catch (\Exception)` below would turn Laravel's 422 into a
        // 500 with the field error buried in an `error` string.
        $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d|after_or_equal:today',
            'branch_id'  => 'nullable|integer|exists:branchs,id',
        ]);

        try {
            $availability = $this->availabilityService->getAvailableSlotsByDate(
                $request->get('service_id'),
                $request->get('date'),
                $request->get('branch_id')
            );

            return response()->json([
                'success' => true,
                'data' => $availability,
                'message' => 'Service availability retrieved successfully'
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service availability',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getProviderAvailability(Request $request): JsonResponse
    {
        // Outside the try — see the note in getServiceAvailability().
        $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'provider_id' => 'required|integer|exists:users,id',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'branch_id'   => 'nullable|integer'
        ]);

        try {
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
     * The days of a month on which one provider still has a free slot for a service.
     *
     * provider_id is deliberately not `exists:users`: whether the id is unknown,
     * not a provider, inactive, or simply does not offer the service, the answer
     * is the same 422 — the endpoint must not tell users apart (see
     * BookingValidationService::rejectProviderServicePair()).
     */
    public function getProviderMonthDays(Request $request): JsonResponse
    {
        // Outside the try — see the note in getServiceAvailability().
        $request->validate([
            'service_id'  => ['required', 'integer', $this->activeServiceRule()],
            'provider_id' => 'required|integer',
            'month'       => 'nullable|date_format:Y-m',
        ]);

        return $this->monthResponse(fn () => $this->availabilityService->getProviderMonthAvailability(
            (int) $request->get('service_id'),
            (int) $request->get('provider_id'),
            $request->get('month'),
        ));
    }

    /**
     * The days of a month on which anybody can take a service, and who.
     */
    public function getServiceMonthDays(Request $request): JsonResponse
    {
        // Outside the try — see the note in getServiceAvailability().
        $request->validate([
            'service_id' => ['required', 'integer', $this->activeServiceRule()],
            'month'      => 'nullable|date_format:Y-m',
            'branch_id'  => 'nullable|integer|exists:branchs,id',
        ]);

        return $this->monthResponse(fn () => $this->availabilityService->getServiceMonthAvailability(
            (int) $request->get('service_id'),
            $request->get('month'),
            $request->filled('branch_id') ? (int) $request->get('branch_id') : null,
        ));
    }

    /**
     * A bookable service: `exists:` alone queries the table raw, so it matches
     * soft-deleted rows that Service::findOrFail() then cannot see (BOOK-09), and
     * it accepts a service the booking call refuses as inactive.
     */
    private function activeServiceRule(): Exists
    {
        return Rule::exists('services', 'id')
            ->whereNull('deleted_at')
            ->where('is_active', true);
    }

    /**
     * Shared envelope for the month endpoints. A rejection (provider cannot take
     * the service, month outside the booking window) is the caller's input and
     * answers 422; anything else is logged by class and answers a bare 500.
     */
    private function monthResponse(Closure $resolve): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $resolve(),
                'message' => 'Availability days retrieved successfully',
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Month availability failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve availability days',
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailabilityCalendar(Request $request): JsonResponse
    {
        // Outside the try — see the note in getServiceAvailability().
        $request->validate([
            'service_id' => 'required|integer|exists:services,id',
            'provider_id'=> 'nullable|integer|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'branch_id'  => 'nullable|integer|exists:branchs,id',
        ]);

        try {
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
