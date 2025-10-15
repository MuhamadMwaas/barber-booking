<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController
{
    public function show(Request $request)
    {
        return response()->json($request->user()->only(['id', 'name', 'email', 'avatar_url', 'email_verified_at']));
    }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => 'sometimes|string|max:255', 'avatar' => 'sometimes|image|max:2048']);
        $user = $request->user();
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = Storage::disk('s3')->url($path);
        }
        if (isset($data['name']))
            $user->name = $data['name'];
        $user->save();
        return response()->json($user->only(['id', 'name', 'email', 'avatar_url']));
    }

    public function changePassword(Request $request)
    {
        $request->validate(['current_password' => 'required', 'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()]]);
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