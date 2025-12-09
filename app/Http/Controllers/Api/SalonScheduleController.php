<?php

namespace App\Http\Controllers\Api;

use App\Filament\Schemas\SalonScheduleForm;
use App\Http\Controllers\Controller;
use App\Models\SalonSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalonScheduleController extends Controller
{
    /**
     * Get salon schedule for a specific branch
     */
    public function show(int $branchId): JsonResponse
    {
        try {
            $data = SalonScheduleForm::loadScheduleData($branchId);
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load schedule',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save salon schedule for a specific branch
     */
    public function store(Request $request, int $branchId): JsonResponse
    {
        try {
            $data = $request->all();

            // Validate data
            $errors = SalonScheduleForm::validateSchedule($data);

            if (!empty($errors)) {
                return response()->json([
                    'error' => 'Validation failed',
                    'errors' => $errors
                ], 422);
            }

            // Save schedule
            SalonScheduleForm::saveScheduleData($branchId, $data);

            return response()->json([
                'success' => true,
                'message' => 'Schedule saved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to save schedule',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all schedules grouped by branch
     */
    public function index(): JsonResponse
    {
        try {
            $schedules = SalonSchedule::with('branch')
                ->orderBy('branch_id')
                ->orderBy('day_of_week')
                ->get()
                ->groupBy('branch_id');

            return response()->json($schedules);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load schedules',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
