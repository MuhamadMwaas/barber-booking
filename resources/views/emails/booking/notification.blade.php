@php $isRtl = app()->getLocale() === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('booking_email.subject_company', ['number' => $appointment->number]) }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fb; font-family: Arial, 'Segoe UI', Tahoma, sans-serif; color: #1f2937;">
    <div style="max-width: 640px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); text-align: {{ $isRtl ? 'right' : 'left' }};">

        <h2 style="margin: 0 0 8px; font-size: 20px; color: #111827;">
            {{ __('booking_email.subject_company', ['number' => $appointment->number]) }}
        </h2>
        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7; color: #374151;">
            {{ __('booking_email.company_intro') }}
        </p>

        {{-- Customer contact block (internal) --}}
        <div style="margin: 0 0 24px; padding: 16px; background-color: #f9fafb; border-radius: 10px;">
            <h3 style="margin: 0 0 12px; font-size: 15px; color: #111827;">{{ __('booking_email.customer') }}</h3>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px; color: #1f2937;">
                <tr>
                    <td style="padding: 4px 0; color: #6b7280; width: 120px;">{{ __('booking_email.name') }}</td>
                    <td style="padding: 4px 0; font-weight: 600;">{{ $appointment->customer_name }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; color: #6b7280;">{{ __('booking_email.email') }}</td>
                    <td style="padding: 4px 0; font-weight: 600;">{{ $appointment->customer_email ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; color: #6b7280;">{{ __('booking_email.phone') }}</td>
                    <td style="padding: 4px 0; font-weight: 600;">{{ $appointment->customer_phone ?: '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 0; color: #6b7280;">{{ __('booking_email.source') }}</td>
                    <td style="padding: 4px 0; font-weight: 600;">{{ $appointment->booking_source?->label() ?? '—' }}</td>
                </tr>
            </table>
        </div>

        @include('emails.booking.partials.details', ['appointment' => $appointment, 'currency' => $currency])

        <hr style="margin: 28px 0 16px; border: none; border-top: 1px solid #eee;">
        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
            {{ __('booking_email.footer') }}
        </p>
    </div>
</body>
</html>
