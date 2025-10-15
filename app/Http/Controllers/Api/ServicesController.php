<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServicesController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Service::with(['category', 'branch', 'providers', 'reviews'])
                ->active();


            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('featured')) {
                $query->featured();
            }

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            }

            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            $query->orderBy($sortBy, $sortDirection);


            $perPage = $request->get('per_page', 15);
            $services = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ServiceResource::collection($services),
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                ],
                'message' => 'Services retrieved successfully'
            ], 200);


        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified service with complete information.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $service = Service::with(['category', 'branch', 'providers', 'reviews.user'])
                ->active()
                ->findOrFail($id);

            // Calculate average rating
            $averageRating = $service->reviews->avg('rating');

            // Count reviews
            $reviewCount = $service->reviews->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'service' => $service,
                    'statistics' => [
                        'average_rating' => round($averageRating, 1),
                        'review_count' => $reviewCount,
                    ]
                ],
                'message' => 'Service retrieved successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
