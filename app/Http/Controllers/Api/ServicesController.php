<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\SingleServiceResource;
use App\Models\Service;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Service::with(['translations', 'category:id,name', 'category.translations', 'image',
                'providers' => function ($query) {
                    $query->select('users.id', 'users.first_name', 'users.last_name')
                        ->with('profile_image');
                },
            ])
                // ->withCount('reviews')
                // ->withAvg('reviews as average_rating', 'rating')
                ->active();

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('featured')) {
                $query->featured();
            }

            if ($request->has('search')) {
                $query->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
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
                'message' => 'Services retrieved successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve services',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified service with complete information.
     *
     * @param  int  $id
     */
    public function show($id): JsonResponse
    {
        try {
            $service = Service::with(['translations', 'category.translations', 'providers', 'reviews.user'])
                ->active()
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => new SingleServiceResource($service),
                'message' => 'Service retrieved successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
