# نموذج الخدمة — Service

> **الملفات:** `app/Models/Service.php:1` (239 سطر)، `app/Models/ServiceCategory.php:1`، `database/migrations/2025_10_10_134548_create_services_table.php:1`

---

## 1. الحقول — `services` table

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `category_id` | FK → service_categories | الفئة (Hair, Nails, Skin...) |
| `name` | string | اسم الخدمة |
| `description` | text nullable | الوصف |
| `price` | decimal(10,2) | السعر الأساسي GROSS (شامل الضريبة) |
| `discount_price` | decimal(10,2) nullable | سعر مخفض (إن وجد وأقل من price) |
| `duration_minutes` | integer | المدة بالدقائق |
| `is_active` | boolean | نشط/غير نشط |
| `is_featured` | boolean | مميز في القوائم |
| `sort_order` | integer | ترتيب العرض |
| `color_code` | string nullable | لون للواجهة `#FF6B9D` |
| `deleted_at` | timestamp nullable | حذف ناعم (SoftDeletes) |

```php
// app/Models/Service.php:10
use SoftDeletes;
protected $casts = ['price' => 'decimal:2', 'discount_price' => 'decimal:2'];
protected $appends = ['image_url', 'translated_name'];
```

---

## 2. العلاقات

```php
category()        → BelongsTo(ServiceCategory)
providers()       → BelongsToMany(User, 'provider_service')
                     withPivot(['is_active','custom_price','custom_duration','notes'])
activeProviders() → providers()->wherePivot('is_active',true)->where('users.is_active',true)
translations()    → HasMany(ServiceTranslation)
reviews()         → HasMany(ServiceReview)
appointmentServices() → HasMany(AppointmentService)
image() / icon()  → MorphOne(File)
invoiceItems()    → MorphMany(InvoiceItem)
```

### Pivot `provider_service`

| العمود | الوصف |
|--------|-------|
| `provider_id` | FK → users |
| `service_id` | FK → services |
| `is_active` | هل يقدم المزود هذه الخدمة حاليًا؟ |
| `custom_price` | سعر مخصص لهذا المزود (nullable) |
| `custom_duration` | مدة مخصصة (nullable — غير مستخدم حاليًا بسبب dead code في BookingService) |
| `notes` | ملاحظات |

---

## 3. التسعير الفعلي — `getEffectivePrice()`

```php
// app/Services/BookingService.php:346
$effectivePrice = $pivot->custom_price ?? $service->price;
if ($service->discount_price && $service->discount_price < $effectivePrice) {
    return (float) $service->discount_price; // الأقل يفوز
}
return (float) $effectivePrice;
```

**ترتيب الأولوية:**

```
discount_price (إن < effective)  ← أعلى أولوية
    ↓
custom_price (من pivot)
    ↓
service.price                    ← افتراضي
```

---

## 4. الترجمة

```php
// ServiceTranslation: service_id, language_id, name, description
$service->getNameIn('ar'); // يرجع الترجمة العربية أو الاسم الأصلي
$service->translated_name; // accessor حسب App::getLocale()
```

`app/Models/Translation/ServiceTranslation.php:1` — `Service::translations()`.

---

## 5. ServiceCategory

| العمود | الوصف |
|--------|-------|
| `name` | اسم الفئة |
| `description` | وصف |
| `is_active` | نشط |
| `sort_order` | ترتيب |
| `deleted_at` | حذف ناعم |

- `ServiceCategory::translations()` → `ServiceCategoryTranslation`
- `ServiceCategory::services()` → `HasMany(Service)`

---

*التالي: [`invoice-and-payment.md`](invoice-and-payment.md)*
