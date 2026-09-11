# Authentication API

> **الملفات:** `app/Http/Controllers/Api/AuthController.php:1`، `app/Http/Controllers/Api/OtpController.php:1`، `app/Services/AuthTokenService.php:1`

---

## 1. التسجيل — `POST /api/auth/register`

```json
POST /api/auth/register
{
  "first_name": "Test", "last_name": "User",
  "registration_method": "email",
  "email": "test@example.com",
  "password": "Password1@",
  "password_confirmation": "Password1@"
}
→ 201 { user, requires_otp_verification:true, masked_destination, otp (dev only) }
```

- لا token حتى التحقق — `AuthController.php:40`.
- `registration_method`: `email` أو `phone` — `RegistrationMethod` enum.

## 2. الدخول — `POST /api/auth/login`

```json
POST /api/auth/login
{ "registration_method": "email", "email": "...", "password": "..." }
→ 200 { access_token, refresh_token, user }  (إن موثق)
→ 403 { requires_otp_verification:true, masked_destination } (إن غير موثق → OTP جديد)
→ 401 { message: "Invalid credentials" }
```

- Throttle: `auth-login` (5/min IP+email) — `routes/api.php:56`.

## 3. OTP — `POST /api/auth/request-otp` + `POST /api/auth/verify-otp`

```json
POST /api/auth/request-otp { "registration_method": "email", "email": "..." }
POST /api/auth/verify-otp  { "registration_method": "email", "email": "...", "otp": "123456" }
→ 200 { access_token, refresh_token, email_verified:true }
```

- `OtpService` يبطل السابق لنفس `destination+type+purpose`.
- Throttles: `otp-send` (3/min), `otp-verify` (10/min).

## 4. Refresh — `POST /api/auth/refresh`

```json
POST /api/auth/refresh { "refresh_token": "def..." }
→ 200 { access_token, access_expires_at }
→ 401 Invalid/expired
→ 403 غير موثق (verification challenge)
```

- Rotation: يبطل القديم — `AuthTokenService.php:1`.

## 5. Google OAuth

```
POST /api/auth/google/mobile { id_token } → verify → findOrCreate → token
GET  /auth/google/redirect → Socialite
GET  /auth/google/callback → token
```

`SocialAuthController.php:1` — `google_id` على `users`.

## 6. كلمة المرور

```
POST /api/auth/forgot-password { email } → OTP
POST /api/auth/password/verify-otp { email, otp } → grant
POST /api/auth/reset-password { email, otp, password, password_confirmation }
```

`PasswordResetService.php:1` — `PasswordResetGrant` بعد verify.

---

*التالي: [`availability.md`](availability.md)*
