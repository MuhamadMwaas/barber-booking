<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevicesController extends Controller{



    public function registerDevice(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
            'device_token' => 'nullable|string',
            'platform' => 'nullable|in:android,ios',
            'os_version' => 'nullable|string',
            'app_version' => 'nullable|string',
            'meta' => 'nullable|array',
        ]);

        $user = $request->user();

        // Keyed on device_id ALONE, matching the table's unique index. device_id
        // is the OneSignal Subscription ID, which belongs to the phone, not the
        // account: when a second account signs in on the same phone the row is
        // handed over to it. Keying on (user_id, device_id) made that INSERT
        // collide with the unique index (500), and while the old row stayed
        // active the previous owner's reminders kept landing on this phone.
        $device = UserDevice::updateOrCreate(
            [
                'device_id' => $request->input('device_id'),
            ],
            [
                'user_id' => $user->id,
                'device_token' => $request->input('device_token'),
                'platform' => $request->input('platform'),
                'os_version' => $request->input('os_version'),
                'app_version' => $request->input('app_version'),
                'is_active' => true,
                'last_active_at' => now(),
                'meta' => $request->input('meta', []),
            ]
        );
        Log::info('Device registered', [
            'user_id' => $user->id,
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'os_version' => $device->os_version,
            'app_version' => $device->app_version,
        ]);

        return response()->json([
            'message' => 'Device registered successfully',
            'data' => $device,
        ], 201);
    }

    public function unregisterDevice(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $user = $request->user();

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_id', $request->input('device_id'))
            ->first();

        if ($device) {
            $device->is_active = false;
            $device->save();

            return response()->json([
                'message' => 'Device unregistered successfully',
            ], 200);
        }

        return response()->json([
            'message' => 'Device not found',
        ], 404);
    }

}
