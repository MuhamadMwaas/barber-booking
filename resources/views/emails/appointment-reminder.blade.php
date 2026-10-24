{{-- Appointment reminder email: branded layout, salon-approved wording (booking_email.reminder.*). --}}
{{-- No booking number, payment method, notes, duration or prices: the salon asked for exactly these five lines. --}}
{{-- Table layout + inline styles only, so Gmail and Outlook render it. Images are CID-embedded. --}}
{{-- Expects: $appointment, $companyName, $cards (see AppointmentReminderMail::cards()), $socialLinks --}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $dir = $isRtl ? 'rtl' : 'ltr';
    $start = $isRtl ? 'right' : 'left';
    $end = $isRtl ? 'left' : 'right';

    $logo = $message->embed(public_path('image/email/logo.png'));
    $calendar = $message->embed(public_path('image/email/calendar.png'));
    $socialIcons = collect($socialLinks)->map(fn ($url, $platform) => [
        'url' => $url,
        'src' => $message->embed(public_path("image/email/{$platform}.png")),
        'alt' => ucfirst($platform),
    ]);

    $dark = '#0b0b0b'; // the logo's own background, so its square edge disappears
    $font = "Arial, 'Segoe UI', Tahoma, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ __('booking_email.reminder.subject', ['company' => $companyName]) }}</title>
    <style>
        /* Phones: drop the calendar column so the five lines keep their width. */
        @media only screen and (max-width: 480px) {
            .rm-icon, .rm-divider { display: none !important; }
            .rm-body { padding: 24px 16px !important; }
            .rm-label { width: 84px !important; }
            .rm-band { padding: 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: {{ $font }}; color: #1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5;">
<tr>
<td align="center" style="padding: 32px 12px;">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" dir="{{ $dir }}" style="width: 100%; max-width: 600px; border-collapse: separate;">

        {{-- Header --}}
        <tr>
            <td class="rm-band" align="{{ $start }}" style="background-color: {{ $dark }}; border-radius: 12px 12px 0 0; padding: 20px 32px;">
                <img src="{{ $logo }}" width="80" height="75" alt="{{ $companyName }}" style="display: block; border: 0; width: 80px; height: auto;">
            </td>
        </tr>

        {{-- Body --}}
        <tr>
            <td class="rm-body" dir="{{ $dir }}" style="background-color: #ffffff; padding: 32px; text-align: {{ $start }};">

                <h1 style="margin: 0 0 16px; font-size: 21px; line-height: 1.3; font-weight: 700; color: #111827;">
                    {{ __('booking_email.reminder.greeting', ['name' => $appointment->customer_name]) }}
                </h1>

                <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #1f2937;">
                    {{ __('booking_email.reminder.intro', ['company' => $companyName]) }}<br>
                    {{ __('booking_email.reminder.details_lead') }}
                </p>

                @foreach($cards as $card)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}" style="margin: 0 0 16px; border: 1px solid #e7e3dc; border-radius: 10px; background-color: #fbfaf8; border-collapse: separate;">
                    <tr>
                        <td class="rm-icon" width="110" align="center" valign="middle" style="width: 110px; padding: 24px 0;">
                            <img src="{{ $calendar }}" width="56" height="56" alt="" style="display: block; border: 0; width: 56px; height: 56px;">
                        </td>
                        <td class="rm-divider" width="1" style="width: 1px; padding: 20px 0;">
                            <div style="width: 1px; height: 150px; background-color: #e2c792;"></div>
                        </td>
                        <td valign="middle" style="padding: 20px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}" style="font-size: 15px; line-height: 1.4;">
                                @foreach([
                                    'date'     => e($card['date']),
                                    'time'     => __('booking_email.reminder.time_value', ['time' => '<span dir="ltr">' . e($card['time']) . '</span>']),
                                    'provider' => e($card['provider']),
                                    'service'  => e($card['services']),
                                    'salon'    => e($card['salon']),
                                ] as $label => $value)
                                <tr>
                                    <td class="rm-label" width="120" valign="top" style="width: 120px; padding: 4px 0; color: #6b7280; text-align: {{ $start }};">{{ __('booking_email.reminder.' . $label) }}:</td>
                                    <td valign="top" style="padding: 4px 0; font-weight: 700; color: #111827; text-align: {{ $start }};">{!! $value !!}</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                </table>
                @endforeach

                <p style="margin: 24px 0 8px; font-size: 15px; line-height: 1.6; color: #1f2937;">
                    {{ __('booking_email.reminder.closing') }}
                </p>
                <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #1f2937;">
                    {{ __('booking_email.reminder.signoff', ['company' => $companyName]) }}
                </p>
            </td>
        </tr>

        {{-- Footer --}}
        <tr>
            <td class="rm-band" style="background-color: {{ $dark }}; border-radius: 0 0 12px 12px; padding: 20px 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="{{ $dir }}">
                    <tr>
                        <td valign="middle" align="{{ $start }}" style="width: 33%;">
                            <img src="{{ $logo }}" width="48" height="45" alt="{{ $companyName }}" style="display: block; border: 0; width: 48px; height: auto;">
                        </td>
                        <td valign="middle" align="center" style="width: 34%; white-space: nowrap;">
                            @foreach($socialIcons as $icon)
                                <a href="{{ $icon['url'] }}" target="_blank" style="display: inline-block; margin: 0 6px; text-decoration: none;"><img src="{{ $icon['src'] }}" width="24" height="24" alt="{{ $icon['alt'] }}" style="display: block; border: 0; width: 24px; height: 24px;"></a>
                            @endforeach
                        </td>
                        <td valign="middle" align="{{ $end }}" style="width: 33%; font-size: 12px; line-height: 1.6; color: #e8d5a8; text-align: {{ $end }};">
                            {!! nl2br(e(__('booking_email.reminder.tagline'))) !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>

</td>
</tr>
</table>
</body>
</html>
