@php $isRtl = app()->getLocale() === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('otp_email.subject.' . $purpose->value, ['company' => $company]) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fb; font-family: Arial, 'Segoe UI', Tahoma, sans-serif; color: #1f2937;">
    <div style="max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); text-align: {{ $isRtl ? 'right' : 'left' }};">
        <p style="margin: 0 0 16px; font-size: 16px;">{{ __('otp_email.greeting', ['name' => $userName]) }}</p>

        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7;">
            {{ $intro }}
        </p>

        <div style="margin: 24px 0; padding: 18px; background-color: #eef2ff; border-radius: 10px; text-align: center;">
            <span dir="ltr" style="font-size: 30px; font-weight: 700; letter-spacing: 8px; color: #111827;">{{ $otp }}</span>
        </div>

        <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.7; color: #374151;">
            {{ __('otp_email.validity', ['date' => $expiryDate, 'time' => $expiryTime]) }}
        </p>

        <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.7; color: #4b5563;">
            {{ __('otp_email.not_requested') }}
        </p>

        <p style="margin: 0; font-size: 15px; font-weight: 600; color: #111827;">
            {{ __('otp_email.signoff', ['company' => $company]) }}
        </p>
    </div>
</body>
</html>
