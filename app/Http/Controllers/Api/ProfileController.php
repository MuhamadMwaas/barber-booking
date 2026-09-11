<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Rules\PasswordRequirements;
use App\Rules\PhoneNumber;
use App\Services\AccountDeletionService;
use App\Support\ImageUploadRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
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
        $user = $request->user();

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            // AUTH-07: validated on the number's digits, not on the punctuation
            // a human typed around them. See App\Rules\PhoneNumber.
            'phone' => ['sometimes', 'string', 'max:20', new PhoneNumber, Rule::unique('users', 'phone')->ignore($user->id)],
            'address' => 'sometimes|string|max:500',
            'city' => 'sometimes|string|max:255',
            // AUTH-06: size alone does not bound a decode. See ImageUploadRules.
            'image' => ImageUploadRules::profileImage(),
        ]);

        if ($request->hasFile('image')) {
            $user->updateProfileImage($request->file('image'));
        }

        if (isset($data['first_name']))
            $user->first_name = $data['first_name'];

        if (isset($data['last_name']))
            $user->last_name = $data['last_name'];

        // Changing the phone number invalidates any prior verification: the new
        // number must be re-verified before it counts as verified again.
        if (isset($data['phone']) && $data['phone'] !== $user->phone) {
            $user->phone = $data['phone'];
            $user->phone_verified_at = null;
        }

        if (isset($data['address']))
            $user->address = $data['address'];

        if (isset($data['city']))
            $user->city = $data['city'];

        $user->save();
        $user = $user->fresh();
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
                'password' => ['required', 'string', 'confirmed', new PasswordRequirements],
        ]);
        $user = $request->user();
        if (!Hash::check($request->current_password??"", $user->password)) {
            return response()->json(['message' => 'Current password incorrect'], 422);
        }
        $user->password = bcrypt($request->password??"");
        $user->save();
        // Changing a password is the first thing a user does when they suspect
        // their account is compromised, so it has to end every session an
        // attacker might be holding: access tokens AND refresh tokens, on this
        // device and on every other one. The current device is included on
        // purpose - a session that survives the change is a session that was
        // never proven to belong to whoever just supplied the new password.
        $user->tokens()->delete();
        RefreshToken::revokeAllFor($user->id, RefreshToken::REASON_PASSWORD_CHANGE);
        return response()->json(['message' => 'Password updated']);
    }

    public function destroy(Request $request, AccountDeletionService $accountDeletionService)
    {
        $user = $request->user();

        if ($user->hasRole('admin') || $user->hasRole('provider')) {
            return response()->json([
                'message' => 'This endpoint is available for customer accounts only.',
            ], 403);
        }

        $request->validate([
            // Accounts created through OTP-only flows have no password to confirm,
            // so the field is only mandatory when the account actually has one.
            'current_password' => [Rule::requiredIf((bool) $user->password), 'string'],
        ]);

        if ($user->password && !Hash::check($request->current_password ?? '', $user->password)) {
            return response()->json([
                'message' => 'Current password incorrect',
            ], 422);
        }

        $accountDeletionService->deleteCustomerAccount($user);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}
