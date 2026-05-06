<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedViaOtp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->requiresOtpVerification()) {
            return response()->json([
                'message' => 'Your account is not verified. Please verify it using the OTP sent to your registered contact.',
                'registration_method' => $user->registration_method?->value ?? $user->registration_method,
                'email_verified' => (bool) $user->email_verified_at,
                'phone_verified' => (bool) $user->phone_verified_at,
                'is_account_verified' => false,
                'requires_otp_verification' => true,
            ], 403);
        }

        return $next($request);
    }
}
