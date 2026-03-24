<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f7fb; font-family: Arial, sans-serif; color: #1f2937;">
    <div style="max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 12px; padding: 32px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);">
        <p style="margin: 0 0 16px; font-size: 16px;">Hello {{ $userName }},</p>

        <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.7;">
            Use the following OTP code to continue your request. This code will expire at
            <strong>{{ $expiresAt->format('Y-m-d H:i') }}</strong>.
        </p>

        <div style="margin: 24px 0; padding: 18px; background-color: #eef2ff; border-radius: 10px; text-align: center;">
            <span style="font-size: 30px; font-weight: 700; letter-spacing: 8px; color: #111827;">{{ $otp }}</span>
        </div>

        <p style="margin: 24px 0 0; font-size: 14px; line-height: 1.7; color: #4b5563;">
            If you did not request this code, you can ignore this email.
        </p>
    </div>
</body>
</html>
