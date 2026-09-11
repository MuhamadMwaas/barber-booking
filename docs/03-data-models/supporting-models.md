# النماذج المساندة — Supporting Models

> **الملفات:** `app/Models/` — 20 نموذج مساند

---

## 1. Branch — `app/Models/Branch.php:1`

| العمود | الوصف |
|--------|-------|
| `name` | اسم الفرع |
| `address` | العنوان |
| `phone` | الهاتف |
| `latitude/longitude` | الإحداثيات |

- `Branch::users()` → HasMany(User) — المزودون في هذا الفرع
- `Branch::salonSchedules()` → HasMany(SalonSchedule)
- حاليًا فرع واحد: `BranchSeeder` — `Main Branch`

---

## 2. Language — `app/Models/Language.php:1`

| العمود | الوصف |
|--------|-------|
| `code` | `en` / `ar` / `de` |
| `name` | الاسم |
| `is_default` | افتراضي؟ |
| `is_active` | نشط |

`LanguageSeeder` ينشئ 3 لغات — `en` افتراضي تقني، `ar` افتراضي للـ CMS.

---

## 3. Otp — `app/Models/Otp.php:1`

| العمود | الوصف |
|--------|-------|
| `email/phone` | الوجهة (واحد nullable) |
| `otp` | الرمز (6 أرقام) |
| `type` | `1=email, 2=phone` (OtpType) |
| `purpose` | `verification` / `password_reset` (OtpPurpose) |
| `expires_at` | انتهاء الصلاحية |
| `attempts` | عدد المحاولات |
| `is_used` | مستخدم؟ |

- `OtpService` يبطل الأكواد السابقة لنفس القناة+الغرض عند إنشاء جديد.

---

## 4. RefreshToken — `app/Models/RefreshToken.php:1`

| العمود | الوصف |
|--------|-------|
| `user_id` | FK → users |
| `token` | hash |
| `expires_at` | انتهاء |
| `is_revoked` | مبطل؟ |

- Rotation: كل refresh يبطل القديم — `2026_08_29_200000_harden_refresh_token_rotation.php`

---

## 5. File — `app/Models/File.php:1` (Polymorphic)

| العمود | الوصف |
|--------|-------|
| `fileable_id/type` | morph → User / Service / ... |
| `path` | المسار |
| `type` | `profile_image` / `service_image` / `icon` |

```php
User::profile_image() → MorphOne(File)
Service::image() → MorphOne(File)
Service::icon() → MorphOne(File)
```

---

## 6. AppointmentReminder — `app/Models/AppointmentReminder.php:1`

| العمود | الوصف |
|--------|-------|
| `appointment_id` | FK → appointments |
| `active_slot` | lead_minutes (nullable = لا تذكير) |
| `remind_at` | وقت الإرسال |
| `delivered_channels` | json — القنوات المرسلة |
| `is_sent` | مرسل؟ |

- قيد فريد: `one_active` — تذكير واحد نشط لكل موعد — `2026_09_11_000001_fix_appointment_reminders_unique_constraint.php`
- `AppointmentReminderService` يدير: schedule/reschedule/cancel — واحد live فقط.

---

## 7. UserDevice — `app/Models/UserDevice.php:1`

| العمود | الوصف |
|--------|-------|
| `user_id` | FK → users |
| `player_id` | OneSignal player ID |
| `platform` | `ios` / `android` / `web` |

`DevicesController@registerDevice` — `routes/api.php:263`

---

## 8. SalonSetting / AppSetting / UserSetting

| النموذج | الجدول | الغرض |
|---------|--------|-------|
| `SalonSetting` | `salon_settings` | إعدادات قديمة (tax_rate, max_booking_days, ...) — `get_setting()` |
| `AppSetting` | `app_settings` | كتالوج الإعدادات (key, default, rule) |
| `UserSetting` | `user_settings` | override per-user لـ AppSetting |

```php
get_setting('tax_rate', '19'); // Helper — app/Helpers/Main.php:10
app(SettingsService::class)->get('tax_rate');
app(UserSettingService::class)->get($user, 'reminder_channel_push'); // مع override
```

---

## 9. PrinterSetting / PrintLog

| النموذج | الوصف |
|---------|-------|
| `PrinterSetting` | إعداد طابعة (name, connection, paper_width) |
| `PrintLog` | سجل كل طباعة (invoice_id, printer_id, printed_at, printed_by) |

`PrintController` ينشئ `PrintLog` عند كل طباعة — `app/Http/Controllers/PrintController.php:1`

---

## 10. باقي النماذج

| النموذج | الوصف |
|---------|-------|
| `Slider` / `SliderItem` | سلايدرات الهبوط |
| `CmsPage` / `SamplePage` | صفحات CMS / ثابتة |
| `LandingSection` | أقسام الهبوط (11 قسم) |
| `Color` / `AppointmentColor` | ألوان المواعيد (للتقويم) |
| `DashboardMessage` | رسائل لوحة الموظفين (pinned, auto-expiry) |
| `ProviderAttendance` | حضور المزود (check-in/out sessions) |
| `AboutUsPage` / `AboutUsTeamMember` | صفحة من نحن |
| `PasswordResetGrant` | منحة reset (بعد verify OTP) |
| `ReasonLeave` | سبب إجازة |

---

*التالي: [`04-services/README.md`](../04-services/README.md)*
