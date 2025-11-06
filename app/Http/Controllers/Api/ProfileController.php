<?php
namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController
{
  public function show(Request $request)
{
    return response()->json([
        'success' => true,
        'message' => 'Profile retrieved successfully',
        'data' => new UserResource($request->user()),
    ], 200);
}

    public function update(Request $request)
    {

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:255',
            'image' => 'sometimes|image|max:2048',
        ]);

        $user = $request->user();

        if ($request->hasFile('image')) {
            $user->updateProfileImage($request->file('image'));
        }

        if (isset($data['first_name']))
            $user->first_name = $data['first_name'];

        if (isset($data['last_name']))
            $user->last_name = $data['last_name'];

        if (isset($data['phone']))
            $user->phone = $data['phone'];

        if (isset($data['address']))
            $user->address = $data['address'];

        if (isset($data['city']))
            $user->city = $data['city'];

        $user->save();
        $user=User::find($user->id);
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user),
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
                'current_password' => 'required',
                'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()]
        ]);
        $user = $request->user();
        if (!Hash::check($request->current_password??"", $user->password)) {
            return response()->json(['message' => 'Current password incorrect'], 422);
        }
        $user->password = bcrypt($request->password??"");
        $user->save();
        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked' => true]);
        return response()->json(['message' => 'Password updated']);
    }
}
