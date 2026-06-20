<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserSettingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * User-facing application settings (e.g. appointment-reminder channels).
 *
 *  GET   /api/settings        → the full options catalog + this user's values
 *  PATCH /api/settings/{key}  → update ONE option's value (the generic route)
 *
 * The update route is intentionally generic: validation and type come from the
 * `app_settings` row, so it serves every present and future option unchanged.
 */
class SettingsController extends Controller
{
    public function __construct(
        protected UserSettingService $settings
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settings->catalog($request->user()),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        try {
            $userSetting = $this->settings->set($request->user(), $key, $request->input('value'));

            return response()->json([
                'success' => true,
                'message' => __('main.settings.updated'),
                'data' => [
                    'key' => $userSetting->key,
                    'value' => $this->settings->get($request->user(), $key),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.settings.not_found'),
                'error_type' => 'not_found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.validation.failed'),
                'errors' => $e->errors(),
                'error_type' => 'validation_error',
            ], 422);
        }
    }
}
