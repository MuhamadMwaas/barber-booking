# Rate Limiting

> **الملفات:** `routes/api.php:53`، `config/rate_limits.php:1`، `app/Providers/AppServiceProvider.php:1` (`registerAuthRateLimiters`)

---

## 1. لماذا نوعان من Limiters؟

- **Inline** (`throttle:5,1`): بُعد واحد (IP).
- **Named** (`throttle:auth-login`): بُعدان (IP + targeted account) — المهاجم يتحكم في IP لكن ليس في الحساب المستهدف.

الـ named limiters معرّفة في `AppServiceProvider::registerAuthRateLimiters()` وتقرأ حدودها من `config/rate_limits.php`.

## 2. كل الحدود

| المسار | Limiter | الحد | المفتاح | السبب |
|--------|---------|------|---------|-------|
| `POST /api/auth/login` | `auth-login` | 5/min | IP + email | credential stuffing |
| `POST /api/auth/register` | `auth-register` | 3/min | IP | spam |
| `POST /api/auth/refresh` | `auth-refresh` | 10/min | IP | — |
| `POST /api/auth/request-otp` | `otp-send` | 3/min | IP + destination | SMS cost |
| `POST /api/auth/verify-otp` | `otp-verify` | 10/min | IP + destination | brute force |
| `POST /api/auth/forgot-password` | `throttle:5,1` | 5/min | IP | + per-destination cooldown في Controller |
| `GET /api/availability/service` | `throttle:40,1` | 40/min | IP | expensive fan-out |
| `GET /api/availability/provider` | `throttle:40,1` | 40/min | IP | — |
| `GET /api/availability/calendar` | `throttle:30,1` | 30/min | IP | ×31 days |
| `POST /api/appointments/reminders` | `throttle:30,1` | 30/min | User | queue jobs |
| `GET /api/appointments/reminders/options` | `throttle:60,1` | 60/min | User | — |

## 3. الاستجابة عند التجاوز

```
429 Too Many Requests
Headers: Retry-After: 60, X-RateLimit-Reset: 1726051200
Body: { "message": "Too Many Attempts." }
```

التطبيق يجب أن يحترم `Retry-After` ولا يعيد المحاولة فورًا.

---

*التالي: [`roles-and-permissions.md`](roles-and-permissions.md)*
