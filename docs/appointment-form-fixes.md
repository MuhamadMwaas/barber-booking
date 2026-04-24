# تقرير إصلاح نموذج حجز المواعيد

**الملفات المعدّلة:**
- `app/Filament/Resources/Appointments/Schemas/AppointmentForm.php`
- `app/Filament/Resources/Appointments/Pages/CreateAppointment.php`

---

## ملخص المشاكل والإصلاحات

| # | المشكلة | الخطورة | الحالة |
|---|---------|---------|--------|
| 1 | قائمة العملاء تعرض الأدمن ومقدمي الخدمة | 🔴 حرجة | ✅ مُصلحة |
| 2 | حساب الضريبة معكوس (NET بدلاً من GROSS) | 🔴 حرجة | ✅ مُصلحة |
| 3 | فلتر مقدمي الخدمة يعرض من يقدم *أي* خدمة لا *كل* الخدمات | 🟠 عالية | ✅ مُصلحة |
| 4 | التايم لاين يظهر قبل اختيار service_id فعلي | 🟡 متوسطة | ✅ مُصلحة |
| 5 | لا يوجد تحقق عند الضغط على "التالي" في الخطوة الثانية | 🟠 عالية | ✅ مُصلحة |
| 6 | `getProvidersTimeline` يعرض مقدمي *أي* خدمة لا *كل* الخدمات | 🟠 عالية | ✅ مُصلحة |
| 7 | نفس خطأ حساب الضريبة في `CreateAppointment.php` | 🔴 حرجة | ✅ مُصلحة |

---

## تفاصيل كل إصلاح

---

### 🔴 إصلاح 1 — قائمة العملاء تعرض جميع المستخدمين

**الملف:** `AppointmentForm.php` — السطر ~76

**المشكلة:**
```php
// قبل: يعرض كل المستخدمين بما فيهم الأدمن ومقدمو الخدمة
->relationship('customer', 'first_name')
```

الـ `relationship()` بدون تصفية كان يجلب كل `users` بدون تمييز الدور.

**الإصلاح:**
```php
// بعد: يعرض فقط المستخدمين ذوو الدور 'customer' والنشطين
->relationship(
    name: 'customer',
    titleAttribute: 'first_name',
    modifyQueryUsing: fn ($query) => $query
        ->role('customer')
        ->where('is_active', true),
)
```

**لماذا؟**
- النظام يستخدم Spatie Permissions برثلاثة أدوار: `admin`, `provider`, `customer`
- يجب أن يرى الأدمن فقط العملاء الحقيقيين عند إنشاء الحجز
- الـ `is_active = true` يحذف العملاء المعطلين من القائمة

---

### 🔴 إصلاح 2 — حساب الضريبة معكوس

**الملف:** `AppointmentForm.php` — دالة `calculateTotals()`

**المشكلة:**
النظام يخزن الأسعار كـ **GROSS (شامل الضريبة)**، لكن الكود كان يعاملها كـ **NET (قبل الضريبة)** ثم يضيف الضريبة فوقها:

```php
// قبل: خاطئ — يعامل السعر كـ NET ويضيف ضريبة فوقه
$subtotal  = $services->sum('price');         // مثلاً €100
$taxAmount = $subtotal * ($taxRate / 100);    // 19% = €19
$total     = $subtotal + $taxAmount;          // €119 ❌ (الخدمة المسعّرة €100 تصبح €119!)
```

**الإصلاح:**
```php
// بعد: صحيح — استخراج الضريبة بالحساب العكسي من GROSS
$grossTotal = $services->sum('price');                    // €100 (إجمالي شامل الضريبة)
$netTotal   = $grossTotal / (1 + $taxRate / 100);         // €84.03 (صافي)
$taxAmount  = $grossTotal - $netTotal;                    // €15.97 (الضريبة)

$set('subtotal',    round($netTotal,   2));  // €84.03
$set('tax_amount',  round($taxAmount,  2));  // €15.97
$set('total_amount', round($grossTotal, 2)); // €100.00 ✅
```

**لماذا؟**
- القاعدة الأساسية في Agent.md: *"All prices stored in the database include tax. Tax is extracted using reverse calculation."*
- الصيغة الصحيحة: `net = gross / (1 + rate/100)` و `tax = gross - net`
- التصحيح يضمن أن المبلغ الذي يدفعه العميل يساوي سعر الخدمة المعروض، ولا يزيد عليه

---

### 🟠 إصلاح 3 — فلتر مقدمي الخدمة في القائمة المنسدلة

**الملف:** `AppointmentForm.php` — `Select::make('provider_id')` options

**المشكلة:**
```php
// قبل: يعرض مقدمي الخدمة الذين يقدمون أي خدمة من القائمة (ANY)
->whereHas('services', function($query) use ($serviceIds) {
    $query->whereIn('services.id', $serviceIds); // مزود يقدم خدمة 1 فقط يظهر حتى لو الحجز يحتاج 1+2
})
```

**الإصلاح:**
```php
// بعد: يعرض فقط مقدمي الخدمة الذين يقدمون جميع الخدمات المطلوبة (ALL)
$query = User::role('provider')->where('is_active', true);
foreach ($serviceIds as $serviceId) {
    $query->whereHas('services', fn ($q) => $q
        ->where('services.id', $serviceId)
        ->where('provider_service.is_active', true)
    );
}
return $query->get()->pluck('full_name', 'id')->toArray();
```

**لماذا؟**
- الحجز ينتمي لمزود واحد (`provider_id` واحد)
- يجب أن يكون المزود قادراً على تقديم **كل** الخدمات في الحجز
- الإصلاح يستخدم `whereHas` لكل خدمة على حدة (AND logic بدلاً من OR)
- إضافة `provider_service.is_active = true` لاستبعاد الخدمات المعطلة للمزود تحديداً

---

### 🟡 إصلاح 4 — ظهور التايم لاين قبل اختيار service_id فعلي

**الملف:** `AppointmentForm.php` — `ViewField::make('timeline_view')`

**المشكلة:**
```php
// قبل: يظهر التايم لاين بمجرد وجود سجل في الـ Repeater حتى لو لم يُختر service_id
->visible(fn (Get $get) => !empty($get('appointment_date')) && !empty($get('services_record')))
```

الـ Repeater يبدأ بـ `defaultItems(1)` فكان دائماً `!empty(services_record)` = true حتى قبل اختيار الخدمة.

**الإصلاح:**
```php
// بعد: يتحقق أن على الأقل خدمة واحدة تم اختيار service_id لها فعلياً
->visible(fn (Get $get) =>
    !empty($get('appointment_date')) &&
    collect($get('services_record') ?? [])->filter(fn ($s) => !empty($s['service_id']))->isNotEmpty()
)
```

**لماذا؟**
- التايم لاين يجلب المزودين بناءً على الخدمات المختارة
- لو ظهر قبل اختيار الخدمة سيعرض كل المزودين أو لن يعرض شيئاً (قائمة فارغة)
- الإصلاح يجعله يظهر فقط حين تكون هناك خدمة مختارة حقيقية

---

### 🟠 إصلاح 5 — إضافة تحقق كامل عند الضغط على "التالي" في الخطوة الثانية

**الملف:** `AppointmentForm.php` — `Wizard\Step::make('service_schedule')`

**المشكلة:**
لم يكن هناك أي تحقق تجاري (business validation) عند الانتقال من الخطوة الثانية إلى الثالثة. المستخدم كان يمكنه الانتقال بدون اختيار مزود أو وقت.

**الإصلاح:**
أضفنا `->afterValidation()` على الخطوة الثانية بخمس مراحل:

```php
->afterValidation(function (Get $get) {
    // 1. تأكد أن خدمة واحدة على الأقل مختارة
    $services = collect($get('services_record') ?? [])
        ->filter(fn ($s) => !empty($s['service_id']));
    if ($services->isEmpty()) { /* notification + Halt */ }

    // 2. تأكد اختيار التاريخ
    if (empty($get('appointment_date'))) { /* notification + Halt */ }

    // 3. تأكد اختيار مقدم الخدمة
    if (empty($get('provider_id'))) { /* notification + Halt */ }

    // 4. تأكد اختيار وقت البداية
    if (empty($get('start_time'))) { /* notification + Halt */ }

    // 5. تحقق من توفر الوقت المختار (Business Rules)
    try {
        app(BookingValidationService::class)
            ->validateTimeSlotAvailability($provider, $firstService, $start, $end);
    } catch (\InvalidArgumentException $e) {
        Notification::make()->danger()->body($e->getMessage())->send();
        throw new Halt();
    }
})
```

**لماذا `Filament\Support\Exceptions\Halt`؟**
- هذه هي الـ exception الرسمية في Filament 5 لإيقاف انتقال خطوة الـ Wizard
- الـ Wizard يلتقطها في `callAfterValidation()` ويبقى على الخطوة الحالية
- الـ `Notification::make()` يعرض رسالة خطأ واضحة للمستخدم قبل الـ Halt

**ماذا يتحقق `validateTimeSlotAvailability`؟**
1. المزود يعمل هذا اليوم (جدول العمل الأسبوعي)
2. الوقت ضمن ساعات عمل المزود
3. لا يوجد إجازة يوم كامل
4. لا تعارض مع إجازات بالساعة
5. لا تعارض مع مواعيد محجوزة أخرى
6. الوقت ليس في الماضي
7. يتجاوز الـ `book_buffer` (افتراضياً 60 دقيقة مسبقاً)

---

### 🟠 إصلاح 6 — `getProvidersTimeline` يعرض مقدمي أي خدمة

**الملف:** `AppointmentForm.php` — دالة `getProvidersTimeline()`

**المشكلة:**
```php
// قبل: ANY service logic
$providers = User::role('provider')
    ->whereHas('services', function($query) use ($serviceIds) {
        $query->whereIn('services.id', $serviceIds); // OR logic
    })->get();
```

**الإصلاح:**
```php
// بعد: ALL services logic
$providerQuery = User::role('provider')->where('is_active', true);
foreach ($serviceIds as $serviceId) {
    $providerQuery->whereHas('services', fn ($q) => $q
        ->where('services.id', $serviceId)
        ->where('provider_service.is_active', true)
    );
}
$providers = $providerQuery->get();
```

**لماذا؟**
- التايم لاين يجب أن يعرض فقط المزودين الذين يمكنهم تنفيذ **كل** الخدمات في الحجز
- يتوافق مع منطق القائمة المنسدلة بعد إصلاح #3
- تجنب عرض مزودين لا يستطيعون فعلياً تنفيذ الحجز

---

### 🔴 إصلاح 7 — نفس خطأ الضريبة في `CreateAppointment.php`

**الملف:** `CreateAppointment.php` — دالة `calculateTotalsFromServices()`

**المشكلة:**
```php
// قبل: نفس الخطأ — معاملة السعر كـ NET
$subtotal  = $servicesCollection->sum('price');
$taxAmount = $subtotal * ($taxRate / 100);
$data['total_amount'] = round($subtotal + $taxAmount, 2); // ❌
```

**الإصلاح:**
```php
// بعد: حساب عكسي صحيح من GROSS
$grossTotal = (float) $servicesCollection->sum('price');
$netTotal   = $grossTotal / (1 + $taxRate / 100);
$taxAmount  = $grossTotal - $netTotal;

$data['subtotal']     = round($netTotal,   2);
$data['tax_amount']   = round($taxAmount,  2);
$data['total_amount'] = round($grossTotal, 2); // ✅
```

**لماذا؟**
- هذه الدالة تُشغَّل عند حفظ الموعد في قاعدة البيانات
- بدون هذا الإصلاح كانت قيم `subtotal/tax_amount/total_amount` في `appointments` table مغلوطة
- الـ Invoice تُحسب لاحقاً بناءً على هذه القيم، لذا الخطأ كان يتسرب للفواتير

---

## الإضافات في الـ Imports

أضفنا ثلاثة imports جديدة في `AppointmentForm.php`:

```php
use App\Services\BookingValidationService; // للتحقق من توفر الوقت في الخطوة 2
use Filament\Notifications\Notification;  // لعرض رسائل الخطأ
use Filament\Support\Exceptions\Halt;     // لإيقاف انتقال الخطوة
```

---

## سلوك النظام بعد الإصلاحات

```
الخطوة 1: اختيار العميل
  ├── القائمة تعرض فقط: المستخدمون بدور 'customer' وis_active=true
  └── الزر "+" لإضافة عميل جديد: يحفظه تلقائياً بدور 'customer'

الخطوة 2: الخدمة + الموعد
  ├── اختر خدمة → يظهر التايم لاين مع مزودي تلك الخدمة فقط
  ├── اختر تاريخ → يتحدث التايم لاين
  ├── اضغط slot في التايم لاين → يحدد المزود والوقت تلقائياً
  └── اضغط "التالي" → تحقق كامل:
        ✅ خدمة مختارة؟
        ✅ تاريخ مختار؟
        ✅ مزود مختار؟
        ✅ وقت مختار؟
        ✅ الوقت متاح وضمن ساعات العمل؟
        ✅ لا تعارض مع مواعيد أخرى؟
        ✅ ضمن حدود الـ book_buffer؟

الخطوة 3: الدفع
  ├── الإجمالي يعرض: صافي + ضريبة + إجمالي (GROSS صحيح)
  └── الحفظ → تحقق نهائي في beforeCreate()
```

---

## مثال عملي على فرق حساب الضريبة

| | قبل الإصلاح ❌ | بعد الإصلاح ✅ |
|--|--|--|
| سعر الخدمة في DB | €100 (GROSS) | €100 (GROSS) |
| subtotal (net) | €100 (خاطئ — هذا GROSS!) | €84.03 |
| tax_amount (19%) | €19 | €15.97 |
| total_amount | €119 ❌ | €100 ✅ |
| الفاتورة تعرض | €119 للعميل (مبالغة!) | €100 للعميل (صحيح) |
