# الأدوار والصلاحيات — User Roles & Permissions

> **الملفات:** `app/Models/User.php:80`، `database/seeders/RoleSeeder.php`، `config/permission.php`، `app/Http/Middleware/EnsureStaffDashboardAccess.php`

---

## 1. الأدوار الخمسة

| الدور | الاسم في DB | الوصف | يُنشأ بواسطة |
|-------|-------------|--------|--------------|
| **SuperAdmin** | `SuperAdmin` | أعلى صلاحية — يدير الإشعارات، كل شيء | `RoleSeeder` |
| **Admin** | `admin` | مدير الصالون — كل CRUD + التقارير + الطباعة | `RoleSeeder` + `UserSeeder` (`admin@elitebeauty.ae`) |
| **Manager** | `manager` | مدير مناوب — صلاحيات قريبة من Admin | `RoleSeeder` |
| **Provider** | `provider` | مزود خدمة (حلاق/مصفف) — له جدول عمل وخدمات | `UserSeeder` (8 providers) |
| **Customer** | `customer` | عميل — يحجز ويتابع حجوزاته | التسجيل عبر API |

**التعريف في الكود:**

```php
// app/Models/User.php:15
const STAFF_ROLES = ['SuperAdmin', 'admin', 'manager', 'provider'];

public function hasStaffRole(): bool {
    return $this->hasAnyRole(self::STAFF_ROLES);
}
public function isActiveStaff(): bool {
    return $this->is_active && $this->hasStaffRole(); // AUTHZ-01 fix
}
```

---

## 2. أين تُفحص الصلاحيات؟

### 2.1 لوحة Filament (`/admin`)

```php
// app/Models/User.php:120
public function canAccessPanel(Panel $panel): bool {
    return $this->isActiveStaff(); // يجب أن يكون staff + active
}
```

- `is_active = false` → لا دخول حتى لو كان `admin` — هذا إصلاح `AUTHZ-01`.
- `customer` → لا دخول أبدًا.

### 2.2 Staff Dashboard (Livewire)

```
routes/web.php:30  →  EnsureStaffDashboardAccess middleware
  → يفحص hasStaffRole() + is_active
  → إن فشل → redirect إلى /login
```

### 2.3 API

| المسار | الحماية | الفحص |
|--------|---------|--------|
| `POST /api/bookings` | `auth:sanctum` + `verified.customer` | أي `customer` موثق |
| `GET /api/appointments/{id}` | `auth:sanctum` | `appointment.customer_id === auth()->id()` — `AppointmentService.php` |
| `GET /api/appointments/reminders/*` | `auth:sanctum` | يملك الموعد |
| `POST /api/notifications/*` | `role:SuperAdmin` | `routes/api.php:249` |
| `GET /api/availability/*` | لا شيء (public) | — |
| `GET /api/services` | لا شيء (public) | — |

### 2.4 صلاحيات دقيقة (Permissions via Spatie)

```
PermissionsSeeder ينشئ permissions مثل:
  - view_appointments, create_appointments, edit_appointments
  - view_providers, manage_schedules
  - view_reports, manage_settings
  - force_booking (تجاوز التوفر — StaffDashboard فقط)
```

- `force_booking` تُفحص server-side فقط في `StaffDashboard.php` قبل رفع `bypass_availability` أو `allow_customer_overlap`.
- العميل لا يستطيع رفع هذه الـ flags أبدًا — `BookingService.php:75` يعلق: "Raised server-side only after a force_booking permission check".

---

## 3. جدول الصلاحيات التفصيلي

| العملية | Customer | Provider | Manager | Admin | SuperAdmin |
|---------|:--------:|:--------:|:-------:|:-----:|:----------:|
| تصفح الخدمات/المزودين | ✅ | ✅ | ✅ | ✅ | ✅ |
| معرفة التوفر | ✅ | ✅ | ✅ | ✅ | ✅ |
| إنشاء حجز (API) | ✅ | ❌ | ✅* | ✅* | ✅* |
| إنشاء حجز (Staff Dashboard) | — | — | ✅ | ✅ | ✅ |
| إلغاء حجزه | ✅ | ❌ | ❌ | ❌ | ❌ |
| إلغاء أي حجز (Filament) | ❌ | ❌ | ✅ | ✅ | ✅ |
| تعديل موعد (Staff Dashboard) | ❌ | ❌ | ✅ | ✅ | ✅ |
| إضافة خدمة لحجز | ❌ | ❌ | ✅ | ✅ | ✅ |
| معالجة الدفع (finalize) | ❌ | ❌ | ✅ | ✅ | ✅ |
| طباعة فاتورة | ❌ | ❌ | ✅ | ✅ | ✅ |
| إدارة المزودين/الخدمات | ❌ | ❌ | ❌ | ✅ | ✅ |
| إدارة الإعدادات | ❌ | ❌ | ❌ | ✅ | ✅ |
| إدارة الإشعارات | ❌ | ❌ | ❌ | ❌ | ✅ |
| `force_booking` (تجاوز) | ❌ | ❌ | ✅ | ✅ | ✅ |

`*` عبر Staff Dashboard فقط، ليس عبر `POST /api/bookings` المخصص للعملاء.

---

## 4. بيانات الدخول الافتراضية (Seeders)

| الدور | Email | Password | الملف |
|-------|-------|----------|-------|
| Admin | `admin@elitebeauty.ae` | `password` | `UserSeeder.php` |
| Customer (موثق) | `hala.alhashimi@gmail.com` | `password` | `UserSeeder.php` |
| Providers (8) | `sarah.johnson@...` إلخ | `password` | `UserSeeder.php` |

---

## 5. تسجيل حساب جديد

```
POST /api/auth/register { first_name, last_name, email/phone, password, registration_method }
  → User (is_active=true, لكن email_verified_via_otp_at = null)
  → OTP → POST /api/auth/verify-otp → email_verified_via_otp_at = now() → token
```

- `registration_method`: `email` أو `phone` — `app/Enum/RegistrationMethod.php`
- `is_account_verified` accessor في `User.php:180` يفحص `email_verified_via_otp_at` أو `phone_verified_at` حسب الطريقة.
- الـ Middleware `verified.customer` (`app/Http/Middleware/EnsureCustomerIsVerified.php`) يمنع غير الموثقين من `bookings` و `appointments`.

---

## 6. Google OAuth

```
POST /api/auth/google/mobile { id_token } → verify → findOrCreate by google_id → token
GET /auth/google/redirect → Socialite → GET /auth/google/callback
```

`app/Http/Controllers/Api/SocialAuthController.php:1`

---

*التالي: [`02-architecture/README.md`](../02-architecture/README.md)*
