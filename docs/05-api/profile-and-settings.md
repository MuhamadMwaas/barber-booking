# Profile, Settings & Devices API

> **الملفات:** `app/Http/Controllers/Api/ProfileController.php:1`، `app/Http/Controllers/Api/SettingsController.php:1`، `app/Http/Controllers/Api/DevicesController.php:1`

---

## 1. `GET /api/profile` — عرض الملف

```
GET /api/profile
Authorization: Bearer ...
→ 200 { success, data:{id, first_name, last_name, email, phone, city, address, avatar_url} }
```

## 2. `POST /api/profile` — تحديث (multipart)

```
POST /api/profile
Content-Type: multipart/form-data
first_name, last_name, phone, address, city, image (file max 2MB)
→ 200 { success, data:{...} }
```

## 3. `POST /api/profile/change-password`

```json
{ "current_password": "...", "password": "New1@", "password_confirmation": "New1@" }
→ 200 { message: "Password updated" }  // يبطل كل tokens
→ 422 current_password incorrect
```

## 4. `DELETE /api/profile` — حذف الحساب

```
DELETE /api/profile   { current_password }   // إلزامي فقط إذا الحساب إلو كلمة سر
→ 200 { success: true, message: "Account deleted successfully" }
→ 403 حساب موظف (أي role من User::STAFF_ROLES)
→ 422 current_password incorrect
```

`AccountDeletionService::deleteCustomerAccount()` — كله داخل transaction واحدة:

| الجدول | شو بيصير |
|--------|----------|
| `users` | **يُحذف نهائياً** (`forceDelete`) — مش soft delete. الإيميل والرقم بيرجعوا متاحين للتسجيل |
| `appointments` | **تبقى**. الحقول الفاضية من `customer_name / customer_email / customer_phone` تتعبى من الحساب (القيم الموجودة ما تتغير)، بعدين `customer_id = NULL` و`customer_account_deleted_at = now()`. المواعيد القادمة PENDING تتلغى `USER_CANCELLED` |
| `invoices` | **تبقى** بأرقامها ومبالغها، `customer_id = NULL`. الفاتورة المطبوعة بتاخد الاسم من الحجز |
| `payments` | ما إلها رابط بالمستخدم — ما بتتأثر |
| tokens, refresh tokens, devices, sessions, OTPs, reviews, notifications, saved payment methods, صورة البروفايل | تنحذف |

> `customer_account_deleted_at` موجود ليمنع `GuestBookingClaimService` من اعتبار هالحجوزات حجوزات ضيف وتسليمها لأول حساب جديد يأكد نفس الرقم.

## 5. Phone Verification (بعد الدخول)

```
POST /api/profile/phone/send-otp { phone }  → throttle 6,1
POST /api/profile/phone/verify-otp { phone, otp } → throttle 10,1
```

`PhoneVerificationController.php:1`

## 6. `GET /api/settings` + `PATCH /api/settings/{key}`

```
GET /api/settings → { catalog:[{key, default, rule}], values:{reminder_push_enabled:true, ...} }
PATCH /api/settings/reminder_push_enabled { value: false } → 200
```

- الكتالوج من `AppSetting`، القيم من `UserSetting` (override) — `UserSettingService.php:1`
- القنوات الثلاث: `reminder_push_enabled` / `reminder_email_enabled` / `reminder_sms_enabled` — كلها gated بـ `ReminderChannelResolver`.

## 7. Devices — Push

```
POST /api/register-device   { device_id: <OneSignal Subscription ID>, platform: android|ios, ... }
POST /api/deregister-device { device_id: <OneSignal Subscription ID> }
```

- `device_id` **هو** OneSignal Subscription ID (`OneSignal.User.pushSubscription.id`) — `NotificationService::resolveDeviceIdsForUsers()` يرسل إلى هذا العمود عبر `include_player_ids`، و`device_token` لا يُستخدم في الإرسال.
- `device_id` فريد على مستوى الجدول: تسجيله من حساب ثانٍ على نفس الجوال ينقل الصف لهذا الحساب.
- دليل تطبيق Flutter الكامل: [`../ONESIGNAL_PUSH_MOBILE_GUIDE_AR.md`](../ONESIGNAL_PUSH_MOBILE_GUIDE_AR.md)

---

*التالي: [`cms-and-landing.md`](cms-and-landing.md)*
