@php $isRtl = app()->getLocale() === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('booking_email.subject_customer', ['number' => $appointment->number]) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fb; font-family: Arial, 'Segoe UI', Tahoma, sans-serif; color: #1f2937;">
    <div style="max-width: 640px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); text-align: {{ $isRtl ? 'right' : 'left' }};">

        @if($companyName)
            <h2 style="margin: 0 0 24px; font-size: 20px; color: #111827;">{{ $companyName }}</h2>
        @endif

        <p style="margin: 0 0 16px; font-size: 16px;">
            {{ __('booking_email.greeting', ['name' => $appointment->customer_name]) }}
        </p>

        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #374151;">
            {{ __('booking_email.confirm_intro') }}
        </p>

        @include('emails.booking.partials.details', ['appointment' => $appointment, 'currency' => $currency])

        <p style="margin: 28px 0 0; font-size: 15px; color: #111827; font-weight: 600;">
            {{ __('booking_email.thanks', ['company' => $companyName ?: config('app.name')]) }}
        </p>

        <hr style="margin: 28px 0 16px; border: none; border-top: 1px solid #eee;">
        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
            {{ __('booking_email.footer') }}
        </p>
    </div>
</body>
</html>
