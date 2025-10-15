<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Services\ProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProvidersController extends Controller
{
    protected $providerService;

    public function __construct(ProviderService $providerService)
    {
        $this->providerService = $providerService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'branch_id' => $request->get('branch_id'),
                'service_id' => $request->get('service_id'),
                'sort_by' => $request->get('sort_by', 'first_name'),
                'sort_direction' => $request->get('sort_direction', 'asc'),
            ];

            $perPage = $request->get('per_page', 15);

            $providers = $this->providerService->getProvidersWithServices($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => ProviderResource::collection($providers),
                'pagination' => [
                    'current_page' => $providers->currentPage(),
                    'last_page' => $providers->lastPage(),
                    'per_page' => $providers->perPage(),
                    'total' => $providers->total(),
                ],
                'message' => 'Providers retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve providers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $locale = $request->get('locale');
            $providerData = $this->providerService->getProviderDetailsWithServices($id, $locale);


            $provider = $providerData['provider'];
            $provider->services = $providerData['services'];
            $provider->total_booking_count = $providerData['total_booking_count'];

            return response()->json([
                'success' => true,
                'data' => new ProviderResource($provider),
                'message' => 'Provider retrieved successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Provider not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve provider',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
