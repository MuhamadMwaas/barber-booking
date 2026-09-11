# الإعدادات — Configuration

> **المجلد:** `config/` (24 ملف) — `.env.example:1` — `app/Helpers/Main.php:1`

---

## 1. `.env` — متغيرات البيئة

| المتغير | الوصف | مثال |
|---------|-------|------|
| `APP_KEY` | مفتاح التشفير | `base64:...` |
| `APP_URL` | رابط التطبيق | `http://localhost:8000` |
| `DATABASE_URL` | PostgreSQL | `postgres://...` |
| `MAIL_MAILER` | `smtp` / `log` / `array` | `smtp` |
| `VONAGE_KEY/SECRET` | Vonage SMS | — |
| `SEVEN_API_KEY` | Seven.io SMS | — |
| `ONESIGNAL_APP_ID` | OneSignal | — |
| `FISKALY_API_KEY` | Fiskaly (معطل) | — |
| `GOOGLE_CLIENT_ID/SECRET` | Google OAuth | — |

## 2. `config/` — 24 ملف

| الملف | الغرض |
|-------|-------|
| `app.php` | اسم/URL/locale |
| `auth.php` + `auth_tokens.php` | Sanctum TTL |
| `rate_limits.php` | حدود الـ throttling |
| `sms.php` | `driver: log/seven/vonage`, `enabled` |
| `fiskaly.php` | Fiskaly (معطل) |
| `invoice-line-types.php` | أنواع أسطر الفاتورة |
| `appointment_reminders.php` | مهل التذكير |
| `otp.php` | expiry + attempts |
| `permission.php` | Spatie |
| `queue.php` | Queue driver |
| `mail.php` | Mailer |

## 3. `SalonSetting` + `get_setting()`

```php
// app/Helpers/Main.php:10 — composer.json files autoload
function get_setting(string $key, mixed $default = null): mixed {
    return SalonSetting::where('key', $key)->value('value') ?? $default;
}
get_setting('tax_rate', '19'); // "19"
get_setting('max_booking_days', '10');
```

| Key | Default | الغرض |
|-----|---------|-------|
| `tax_rate` | 19 | نسبة الضريبة |
| `max_booking_days` | 10 | أقصى حجز مسبق |
| `max_services_per_booking` | 10 | خدمات لكل حجز |
| `max_daily_bookings` | 10 | حجوزات العميل/يوم |
| `book_buffer` | 60 | دقائق قبل الموعد |
| `company_name/address/phone/tax_number` | — | للفاتورة |

## 4. `AppSetting` / `UserSetting`

- `AppSetting` = كتالوج (key, default, validation rule).
- `UserSetting` = override per-user — `UserSettingService::get($user, $key)`.
- مثال: `reminder_channel_push` / `email` / `sms` — كلها `AppSetting` + `UserSetting` + `ReminderChannelResolver`.

---

*التالي: [`seeding.md`](seeding.md)*
