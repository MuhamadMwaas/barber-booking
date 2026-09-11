# 09 — الأمان والامتثال — Security & Compliance

> **الملفات:** `app/Services/OtpService.php:1`، `config/rate_limits.php:1`، `app/Providers/AppServiceProvider.php:1`، `app/Services/Fiskaly/` (معطل)

---

## 1. خريطة الأمان

```
┌─────────────────────────────────────────────────┐
│  Rate Limiting (Throttling)                     │
│  routes/api.php + config/rate_limits.php        │
│  auth-login, otp-verify, availability/*, ...    │
├─────────────────────────────────────────────────┤
│  Authentication                                  │
│  Sanctum (Bearer) + Refresh Rotation            │
│  Google OAuth (Socialite)                       │
├─────────────────────────────────────────────────┤
│  OTP & Verification                             │
│  OtpService (purpose-aware, attempt-capped)     │
│  PasswordResetService (grant-based)             │
├─────────────────────────────────────────────────┤
│  Authorization                                   │
│  Spatie Roles (SuperAdmin/admin/manager/...)    │
│  canAccessPanel() + EnsureStaffDashboardAccess   │
├─────────────────────────────────────────────────┤
│  Booking Security                                │
│  BookingLockService (SELECT FOR UPDATE)         │
│  force_booking permission (server-side only)     │
├─────────────────────────────────────────────────┤
│  Tax Compliance                                  │
│  TSE (Fiskaly) — معطل عمداً                     │
│  GROSS pricing + bcmath                         │
└─────────────────────────────────────────────────┘
```

---

## 2. Rate Limiting

| المسار | Limiter | الحد | المفتاح |
|--------|---------|------|---------|
| `POST /api/auth/login` | `auth-login` | 5/min | IP + email |
| `POST /api/auth/verify-otp` | `otp-verify` | 10/min | IP + destination |
| `POST /api/auth/request-otp` | `otp-send` | 3/min | IP + destination |
| `GET /api/availability/service` | `throttle:40,1` | 40/min | IP |
| `GET /api/availability/calendar` | `throttle:30,1` | 30/min | IP |
| `POST /api/auth/forgot-password` | `throttle:5,1` | 5/min | IP |
| `GET /api/appointments/reminders/*` | `throttle:60,1` | 60/min | User |

التعريف في `AppServiceProvider::registerAuthRateLimiters()` + `config/rate_limits.php:1`.

---

## 3. OTP

- 6 أرقام، expiry من `config/otp.php`، محاولات محدودة.
- Purpose-aware: `verification` vs `password_reset` — إبطال منفصل.
- `PasswordResetService` ينشئ `PasswordResetGrant` بعد verify — لا reset مباشر بـ OTP.

---

## 4. الأدوار

`SuperAdmin > admin > manager > provider > customer` — انظر [`01-overview/user-roles.md`](../01-overview/user-roles.md).

`force_booking` يُفحص server-side فقط — العميل لا يستطيع رفعه.

---

## 5. TSE — معطل عمداً

- `app/Services/Fiskaly/` (6 ملفات) خارج مسار الدفع.
- `InvoiceFinalizationService` يسجل `tse_enabled=false`.
- إعادة التفعيل = مشروع تكامل منفصل، ليس `FISKALY_API_KEY` فقط.

---

## 6. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`auth-and-otp.md`](auth-and-otp.md) | OTP + Refresh Rotation + Google OAuth |
| [`rate-limiting.md`](rate-limiting.md) | كل الـ Throttles بالتفصيل |
| [`roles-and-permissions.md`](roles-and-permissions.md) | Spatie + canAccessPanel + StaffDashboard |
| [`tse-and-tax.md`](tse-and-tax.md) | الامتثال الألماني + GROSS + bcmath |

---

*التالي: [`10-operations/README.md`](../10-operations/README.md)*
