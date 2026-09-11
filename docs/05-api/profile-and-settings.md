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
DELETE /api/profile
→ AccountDeletionService: يلغي المواعيد القادمة + يجهل السابقة
```

## 5. Phone Verification (بعد الدخول)

```
POST /api/profile/phone/send-otp { phone }  → throttle 6,1
POST /api/profile/phone/verify-otp { phone, otp } → throttle 10,1
```

`PhoneVerificationController.php:1`

## 6. `GET /api/settings` + `PATCH /api/settings/{key}`

```
GET /api/settings → { catalog:[{key, default, rule}], values:{reminder_channel_push:true, ...} }
PATCH /api/settings/reminder_channel_push { value: false } → 200
```

- الكتالوج من `AppSetting`، القيم من `UserSetting` (override) — `UserSettingService.php:1`
- القنوات الثلاث: `reminder_channel_push` / `email` / `sms` — كلها gated بـ `ReminderChannelResolver`.

## 7. Devices — Push

```
POST /api/register-device { player_id, platform: ios/android/web }
POST /api/deregister-device { player_id }
```

`UserDevice` — OneSignal `player_id` — `DevicesController.php:1`

---

*التالي: [`cms-and-landing.md`](cms-and-landing.md)*
