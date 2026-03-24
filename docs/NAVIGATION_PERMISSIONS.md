# 🔐 Navigation Permissions Guide

## نظرة عامة

تم تطبيق `NavigationDefaultAccess` trait على جميع **13 Resources** و **6 Pages** في Filament Panel لتوحيد التحقق من الصلاحيات.

---

## 📋 الـ Resources المحمية

| # | Resource | التحقق من | Permissions Required |
|---|----------|----------|----------------------|
| 1 | **Appointment** | canAccess() | Appointment:access |
| 2 | **User** | canAccess() | User:access |
| 3 | **Service** | canAccess() | Service:access |
| 4 | **ServiceCategory** | canAccess() | ServiceCategory:access |
| 5 | **Language** | canAccess() | Language:access |
| 6 | **SalonSetting** | canAccess() | SalonSetting:access |
| 7 | **ReasonLeave** | canAccess() | ReasonLeave:access |
| 8 | **ProviderScheduledWork** | canAccess() | ProviderScheduledWork:access |
| 9 | **Page** | canAccess() | Page:access |
| 10 | **Provider** | canAccess() | Provider:access |
| 11 | **InvoiceTemplate** | canAccess() | InvoiceTemplate:access |
| 12 | **PrinterSetting** | canAccess() | PrinterSetting:access |
| 13 | **PrintLog** | canAccess() | PrintLog:access |

---

## 📄 الـ Pages المحمية

| # | Page | التحقق من | Permissions Required |
|---|------|----------|----------------------|
| 1 | **Reports** | canAccess() | Reports:access |
| 2 | **ProviderReport** | canAccess() | ProviderReport:access |
| 3 | **ManageSalonSchedules** | canAccess() | ManageSalonSchedules:access |
| 4 | **ManageProviderSchedules** | canAccess() | ManageProviderSchedules:access |
| 5 | **ManageProviderLeaves** | canAccess() | ManageProviderLeaves:access |
| 6 | **ViewProviderScheduleTimeline** | canAccess() | ViewProviderScheduleTimeline:access |

---

## 🔍 كيف يعمل النظام

### 1. **القائمة الجانبية (Navigation)**

عندما يزور المستخدم لوحة التحكم، Filament يفحص `canAccess()` لكل Resource/Page:

```php
// في Filament Navigation
if (AppointmentResource::canAccess()) {
    // عرض Resource في القائمة الجانبية
}
```

### 2. **Trait: NavigationDefaultAccess**

الـ Trait يوفر دوال موحدة:

```php
use App\Traits\NavigationDefaultAccess;

class AppointmentResource extends Resource {
    use NavigationDefaultAccess;

    // هذا يقوم به الـ trait تلقائياً:
    // public static function canAccess(): bool
    // public static function canCreate(): bool
    // public static function canDeleteAny(): bool
    // public static function canEdit(Model $record): bool
    // public static function canView(Model $record): bool
}
```

### 3. **Permission Format**

الصيغة الموحدة: `ResourceName:ability`

```
// مثال:
Appointment:access     // عرض في القائمة
Appointment:view       // رؤية التفاصيل
Appointment:create     // إنشاء جديد
Appointment:edit       // تعديل
Appointment:delete     // حذف
Appointment:cancel     // قدرة خاصة إضافية
```

---

## 🛡️ مستويات الحماية

### المستوى 1: القائمة الجانبية (Navigation)
- **الشروط**: `canAccess()` = false
- **النتيجة**: Resource/Page لا يظهر في القائمة الجانبية
- **لكن**: المستخدم يمكنه الدخول مباشرة عبر URL

### المستوى 2: الدخول المباشر (Strict Mode)
- **الشروط**: `canAccess()` = false + middleware على الـ Route
- **النتيجة**: حماية كاملة (لا يظهر في القائمة ولا يمكن الدخول عبر URL)
- **الحالة الحالية**: القائمة الجانبية فقط

---

## 📊 موزّع الصلاحيات حسب Role

### SuperAdmin & Admin
```
✅ كل الصلاحيات (كل Resources و Pages)
```

### Manager (مدير الصالون)
```
✅ Appointment (كل العمليات)
✅ Provider (view, create, edit فقط)
✅ Service & ServiceCategory
✅ ProviderScheduledWork
✅ ReasonLeave
✅ User (view only)
✅ InvoiceTemplate (view, print)
✅ PrinterSetting & PrintLog
✅ Reports Pages

❌ Language (إعداد نظام)
❌ SalonSetting (حساس)
❌ Page (صفحات النظام)
```

### Provider (الموظف)
```
✅ Appointment (view, cancel)
✅ ProviderScheduledWork (view)
✅ ProviderReport
✅ ManageProviderSchedules
✅ ManageProviderLeaves
✅ ViewProviderScheduleTimeline

❌ كل شيء آخر
```

### Customer
```
❌ لا وصول للوحة التحكم
```

---

## 💡 الاستخدام في الكود

### مثال 1: التحقق البسيط

```php
// في Resource
use App\Traits\NavigationDefaultAccess;

class AppointmentResource extends Resource {
    use NavigationDefaultAccess;
    // يتم كل شيء تلقائياً!
}
```

### مثال 2: تفعيل Strict Mode (اختياري)

إذا أردت حماية كاملة (غير قابل للدخول عبر URL مباشر):

```php
// يمكن إضافة middleware لاحقاً إذا لزم الأمر
Route::get('/filament/admin/appointments', function () {
    if (!AppointmentResource::canAccess()) {
        abort(403);
    }
})->middleware('auth:admin');
```

### مثال 3: Custom Permission في Page

إذا أردت صلاحية custom بدلاً من `canAccess()`:

```php
use App\Traits\NavigationDefaultAccess;

class CustomPage extends Page {
    use NavigationDefaultAccess;

    public static function canAccess(): bool {
        return static::canCustom('custom.permission');
    }
}
```

---

## 🔄 تدفق الطلب الكامل

```
1. المستخدم يفتح لوحة التحكم
   ↓
2. Filament يبني Navigation Menu
   ↓
3. لكل Resource/Page، يستدعي canAccess()
   ↓
4. canAccess() يستدعي allowed('access')
   ↓
5. allowed() يتحقق من:
   - هل المستخدم مسجل دخول؟
   - هل هو SuperAdmin/Admin؟ → وصول كامل
   - هل لديه Permission('ResourceName:access')؟ → عرض
   - وإلا → إخفاء
   ↓
6. Navigation محدثة بناءً على الصلاحيات
```

---

## ⚡ الصلاحيات من RoleSeeder

تم ربط كل Role بالصلاحيات المناسبة في [database/seeders/RoleSeeder.php](../database/seeders/RoleSeeder.php):

**مثال:**
```php
'manager' => [
    'Appointment:access', 'Appointment:view', 'Appointment:create',
    'Appointment:edit',   'Appointment:cancel', 'Appointment:reschedule',
    // ... المزيد
],
```

---

## 🚀 شرح إضافة Resource جديد

إذا أضفت Resource جديد وأردت حمايته:

### 1️⃣ أضف الـ Trait

```php
use App\Traits\NavigationDefaultAccess;

class NewResource extends Resource {
    use NavigationDefaultAccess;
}
```

### 2️⃣ أضف الصلاحيات في PermissionsSeeder

```php
private const EXTRA_ABILITIES = [
    'NewResource' => ['extra_ability_1', 'extra_ability_2'],
];
```

### 3️⃣ أضف Role Permissions في RoleSeeder

```php
'manager' => [
    'NewResource:access',
    'NewResource:view',
    'NewResource:create',
    'NewResource:edit',
    // إضافة الـ extra abilities
],
```

### 4️⃣ شغّل الـ Seeders

```bash
php artisan db:seed --class=PermissionsSeeder
php artisan db:seed --class=RoleSeeder
```

---

## 📌 ملخص الملفات المعدّلة

| الملف | التغيير |
|------|--------|
| `app/Filament/Resources/*/XResource.php` | ✅ أضيف `use NavigationDefaultAccess` |
| `app/Filament/Pages/*.php` | ✅ أضيف `use NavigationDefaultAccess` |
| `database/seeders/PermissionsSeeder.php` | ✅ منتسب تلقائي من Resources |
| `database/seeders/RoleSeeder.php` | ✅ توزيع صلاحيات حسب Role |

---

## 🔗 المراجع

- [NavigationDefaultAccess Trait](../app/Traits/NavigationDefaultAccess.php)
- [Permissions Seeder](../database/seeders/PermissionsSeeder.php)
- [Role Seeder](../database/seeders/RoleSeeder.php)
- [Filament Authorization](https://filamentphp.com/docs/3.x/admin/users/authorization)
- [Spatie Permissions](https://github.com/spatie/laravel-permission)

---

## ✅ Checklist

- [x] إضافة trait لجميع 13 Resources
- [x] إضافة trait لجميع 6 Pages
- [x] PermissionsSeeder يكشف تلقائياً عن Resources
- [x] RoleSeeder يوزع الصلاحيات بناءً على Role
- [x] Guard = 'admin' موحد في كليهما
- [x] SuperAdmin و Admin لديهم وصول كامل
- [x] Manager محدود بصلاحيات معينة
- [x] Provider محدود جداً (حجوزاته فقط)
- [x] Customer بدون وصول للوحة التحكم

---

**Last Updated:** 2026-02-28
**Version:** 1.0
**Guard:** admin
