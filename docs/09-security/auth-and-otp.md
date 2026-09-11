# التوثيق و OTP

> **الملفات:** `app/Services/OtpService.php:1`، `app/Services/OtpDeliveryService.php:1`، `app/Services/AuthTokenService.php:1`، `app/Models/Otp.php:1`، `config/otp.php:1`

---

## 1. OtpService

```php
generate(destination, type, purpose) → Otp { otp: 6 digits, expires_at, attempts:0 }
verify(destination, code, type, purpose) → bool
```

- عند `generate`: يبطل الأكواد السابقة لنفس `destination+type+purpose` فقط — لا يمس purposes أخرى.
- `expires_at` من `config/otp.php` (دقائق)، `max_attempts` محدود.
- `OpcPurpose`: `verification` vs `password_reset` — منفصلان.

## 2. OtpDeliveryService

```php
deliver(Otp $otp) → via SmsGateway أو Mail حسب type
```

- `type=EMAIL_OTP` → Mail (`emails/otp.blade.php`)
- `type=SMS_OTP` → `SmsManager` → Seven/Vonage/Log driver.

## 3. AuthTokenService + Refresh Rotation

```php
createTokens(User) → {access_token, refresh_token, access_expires_at, refresh_expires_at}
refresh(refresh_token) → new access_token (يبطل القديم)
```

- `access_token` = Sanctum `personal_access_tokens` — TTL من `config/auth_tokens.php`.
- `refresh_token` = `refresh_tokens` table — rotation آمن: كل refresh يبطل القديم وينشئ جديدًا — `2026_08_29_200000_harden_refresh_token_rotation.php`.
- `PasswordResetService` ينشئ `PasswordResetGrant` بعد verify OTP — الـ reset لا يقبل OTP مباشرًا.

## 4. Google OAuth

```
POST /api/auth/google/mobile { id_token } → verify Google → findOrCreate by google_id → token
```

`SocialAuthController.php:1` + `google/apiclient`.

---

*التالي: [`rate-limiting.md`](rate-limiting.md)*
