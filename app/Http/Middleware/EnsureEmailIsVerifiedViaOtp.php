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

        if ($user) {


            if (!$user->email_verified_via_otp_at) {
                return response()->json([
                    'message' => 'Your email address is not verified. Please verify your email using OTP.',
                    'email_verified' => false,
                    'requires_otp_verification' => true
                ], 403);
            }
        }

        return $next($request);
    }
}
