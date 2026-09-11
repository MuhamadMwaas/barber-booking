# الأدوار والصلاحيات

> **الملفات:** `app/Models/User.php:80`، `database/seeders/RoleSeeder.php:1`، `database/seeders/PermissionsSeeder.php:1`، `config/permission.php:1`

---

## 1. الأدوار

| الدور | الوصف |
|-------|-------|
| `SuperAdmin` | كل شيء + إدارة الإشعارات |
| `admin` | مدير الصالون — كل CRUD + تقارير |
| `manager` | مدير مناوب — قريب من admin |
| `provider` | مزود خدمة |
| `customer` | عميل |

`Spatie HasRoles` — `User::hasRole()`, `hasAnyRole()`, `assignRole()`.

## 2. `canAccessPanel()` — `User.php:120`

```php
public function canAccessPanel(Panel $panel): bool {
    return $this->isActiveStaff(); // is_active && hasStaffRole()
}
```

- `is_active=false` → لا دخول حتى لو admin — إصلاح `AUTHZ-01`.
- `customer` → لا دخول.

## 3. Staff Dashboard

```
EnsureStaffDashboardAccess middleware — routes/web.php:30
  → hasStaffRole() && is_active
  → وإلا redirect /login
```

## 4. Permissions الدقيقة

```
PermissionsSeeder:
  view_appointments, create_appointments, edit_appointments,
  view_providers, manage_schedules,
  view_reports, manage_settings,
  force_booking (تجاوز التوفر — server-side فقط)
```

- `force_booking` يُفحص في `StaffDashboard.php` قبل رفع `bypass_availability` / `allow_customer_overlap`.
- العميل لا يستطيع رفعها — `BookingService.php:75` يعلق "Raised server-side only".

---

*التالي: [`tse-and-tax.md`](tse-and-tax.md)*
