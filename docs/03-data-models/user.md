# نموذج المستخدم — User

> **الملف:** `app/Models/User.php:1` (351 سطر) — النموذج المركزي للمستخدمين

---

## 1. الحقول — `users` table

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `first_name` / `last_name` | string | الاسم |
| `email` | string unique nullable | البريد |
| `phone` | string unique nullable | الهاتف |
| `password` | string hashed | كلمة المرور |
| `email_verified_at` | datetime nullable | تحقق البريد (Laravel default) |
| `email_verified_via_otp_at` | datetime nullable | تحقق عبر OTP |
| `phone_verified_at` | datetime nullable | تحقق الهاتف |
| `registration_method` | string | `email` / `phone` |
| `user_type` | string | نوع المستخدم (قديم) |
| `is_active` | boolean | نشط؟ |
| `branch_id` | FK → branches nullable | الفرع (للمزودين) |
| `google_id` | string nullable | Google OAuth ID |
| `locale` | string | اللغة المفضلة |
| `avatar_url` | string nullable | الصورة |
| `address` / `city` | string nullable | العنوان |
| `notes` | text nullable | ملاحظات |
| `remember_token` | string | Laravel remember |
| `deleted_at` | timestamp nullable | حذف ناعم |

---

## 2. Traits والـ Interfaces

```php
class User extends Authenticatable implements FilamentUser, HasName {
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;
}
```

| Trait/Interface | الغرض |
|-----------------|-------|
| `HasApiTokens` (Sanctum) | `tokens()` — API tokens |
| `HasRoles` (Spatie) | `hasRole()`, `assignRole()` |
| `SoftDeletes` | `deleted_at` |
| `FilamentUser` | `canAccessPanel()` |
| `HasName` | `getFilamentName()` |

**Observer:** `#[ObservedBy(UserObserver::class)]` — `app/Observers/UserObserver.php:1`

---

## 3. الثوابت والدوال

```php
const STAFF_ROLES = ['SuperAdmin','admin','manager','provider'];

hasStaffRole(): bool → hasAnyRole(STAFF_ROLES)
isActiveStaff(): bool → is_active && hasStaffRole()  // AUTHZ-01
isProvider(): bool → hasRole('provider')
isAccountVerified(): bool → حسب registration_method
requiresOtpVerification(): bool → !isAccountVerified()
canAccessPanel(Panel $panel): bool → isActiveStaff()
getRegistrationMethodEnum(): RegistrationMethod
```

**Accessors (Appends):**

```php
full_name → first_name + last_name
profile_image_url → File morph أو avatar_url أو Laravolt Avatar
is_account_verified → bool
requires_otp_verification → bool
```

---

## 4. العلاقات

```php
branch() → BelongsTo(Branch)
scheduledWorks() → HasMany(ProviderScheduledWork)
timeOffs() → HasMany(ProviderTimeOff)
customerAppointments() → HasMany(Appointment, 'customer_id')
appointmentsAsProvider() → HasMany(Appointment, 'provider_id')
services() → BelongsToMany(Service, 'provider_service')
invoices() → HasMany(Invoice, 'customer_id')
devices() → HasMany(UserDevice)
profile_image() → MorphOne(File)
```

---

## 5. Casts

```php
protected $casts = [
    'password' => 'hashed',
    'is_active' => 'boolean',
    'email_verified_at' => 'datetime',
    'email_verified_via_otp_at' => 'datetime',
];
```

---

*التالي: [`appointment.md`](appointment.md)*
