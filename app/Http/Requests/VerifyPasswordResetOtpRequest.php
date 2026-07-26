<?php

namespace App\Http\Requests;

/**
 * Step 2 of the reset flow: prove the code was received.
 * Same destination fields as step 1, plus the code itself.
 */
class VerifyPasswordResetOtpRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'otp' => ['required', 'string', 'max:10'],
        ]);
    }
}
