<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fb; font-family: Arial, sans-serif; color: #1f2937;">
    <div style="max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);">
        <p style="margin: 0 0 16px; font-size: 16px;">{{ $userName }}</p>

        <h2 style="margin: 0 0 16px; font-size: 18px; color: #111827;">{{ $title }}</h2>

        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7;">
            {{ $body }}
        </p>
    </div>
</body>
</html>
