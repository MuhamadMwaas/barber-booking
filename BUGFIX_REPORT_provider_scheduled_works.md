# تقرير إصلاح خطأ ProviderScheduledWorks Form

## ملخص المشكلة

عند الدخول لصفحة إضافة جدول دوام (`/admin/provider-scheduled-works/create`) يظهر خطأ:

```
ErrorException - Internal Server Error
foreach() argument must be of type array|object, null given
```

## سبب المشكلة

المشكلة في ملف:
```
app/Filament/Resources/ProviderScheduledWorks/Schemas/ProviderScheduledWorkForm.php:167
```

### التحليل:

1. **وصف المشكلة:** الكود يستخدم `foreach ($get('days') as $day)` بشكل مباشر بدون التحقق من null

2. **لماذا تحدث:** عند الدخول لصفحة الإنشاء (`create`)، لا توجد بيانات محفوظة بعد. hence:
   - `$get('days')` ترجع `null` وليس `array`
   - `foreach()` 无法 تتعامل مع `null`
   - hence يحدث الخطأ: "foreach() argument must be of type array|object, null given"

3. **مسار الظهور:**
   ```
   المستخدم يدخل /admin/provider-scheduled-works/create
   → Load صفحة الإنشاء
   → render الـ Form schema
   → Section::createSummarySection()
   → Placeholder::make('total_working_minutes') 
   → $get('days') = null
   → foreach($null) → Error!
   ```

## الإصلاح

### التغييرات في الملف:
`app/Filament/Resources/ProviderScheduledWorks/Schemas/ProviderScheduledWorkForm.php`

**قبل:**
```php
Placeholder::make('total_working_minutes')
    ->content(function (Get $get) {
        $minutes = self::calculateWeeklyWorkingMinutes($get('days') ?? []);
        
        foreach ($get('days') as $day) {  // ← خطأ! null هنا
            foreach ($day['shifts'] as $shiftId => $shift) {
                // ...
            }
        }
        return self::formatMinutes($totalMinutes);
    }),
```

**بعد:**
```php
Placeholder::make('total_working_minutes')
    ->content(function (Get $get) {
        $days = $get('days') ?? [];
        if (!is_array($days) || empty($days)) {
            return self::formatMinutes(0);
        }
        $totalMinutes = 0;

        foreach ($days as $day) {
            if (!is_array($day) || !isset($day['shifts']) || !is_array($day['shifts'])) {
                continue;
            }
            foreach ($day['shifts'] as $shiftId => $shift) {
                if (empty($shift['is_work_day'])) {
                    continue;
                }
                // ... rest of logic
            }
        }
        return self::formatMinutes($totalMinutes);
    }),
```

## ملفات المعدلة

| الملف | التغيير |
|------|----------|
| `app/Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm.php` | إضافة تحقق null safety في Placeholder sections |

## الملخص

- **المشكلة:** `foreach()` على `null`
- **السبب:** عدم وجود بيانات عند صفحة الإنشاء
- **الإصلاح:** إضافة تحقق من null/empty قبل الـ foreach
- **الحالة:** تم الإصلاح ✓