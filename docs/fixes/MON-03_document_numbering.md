<div lang="ar" dir="rtl">

# MON-03 — إصلاح تكرار أرقام الفواتير وفرض الفرادة

> **التاريخ:** 10 سبتمبر 2026
> **الثغرة:** `MON-03` + `DB-01` في [`SECURITY_AUDIT_2026-08-29.md`](../../SECURITY_AUDIT_2026-08-29.md) — 🔴 Critical (مانع إطلاق)
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بتنفيذ فعلي على بيانات مكسورة**
> **الاختبارات:** 20 اختباراً جديداً · **خط الأساس 10 فشلاً ← 10** · 429 ناجحاً ← **448** · صفر تراجعات
>
> **تحديث MON-05 — 2026-09-10:** الأمثلة التي تذكر
> `InvoicePaymentService` أو `createInvoiceFromAppointment()` أدناه توثّق الحالة
> التاريخية وقت اكتشاف عيوب الترقيم. الخدمتان حُذفتا، والكاتب الوحيد حالياً هو
> `InvoiceFinalizationService::finalizeAppointmentPayment()`، وفيه القفل والحرس
> والترقيم وإنشاء Payment ضمن transaction واحدة.

---

## فهرس المحتويات

1. [التصحيح الأهم لتقرير التدقيق](#1-التصحيح-الأهم-لتقرير-التدقيق)
2. [العطل الأول — حتمي بنسبة 100٪](#2-العطل-الأول--حتمي-بنسبة-100)
3. [العطل الثاني — القفل لا يحجز شيئاً](#3-العطل-الثاني--القفل-لا-يحجز-شيئاً)
4. [العطل الثالث — TOCTOU على حرس الحالة](#4-العطل-الثالث--toctou-على-حرس-الحالة)
5. [العطل الرابع — لا قيد فريد](#5-العطل-الرابع--لا-قيد-فريد-على-أي-رقم-مستند)
6. [أربعة أعطال لم يذكرها التقرير](#6-أربعة-أعطال-لم-يذكرها-التقرير)
7. [تصحيحات أخرى للتقرير](#7-تصحيحات-أخرى-للتقرير)
8. [الإصلاح — كل تعديل بالكود](#8-الإصلاح--كل-تعديل-بالكود)
9. [القرارات التصميمية](#9-القرارات-التصميمية)
10. [البيانات القديمة و GoBD](#10-البيانات-القديمة-و-gobd)
11. [التحقق العملي](#11-التحقق-العملي)
12. [قواعد يجب عدم كسرها](#12-قواعد-يجب-عدم-كسرها)

---

## 1. التصحيح الأهم لتقرير التدقيق

> ### 🔴 التقرير وصف MON-03 كـ **حالة تسابق تحتاج ضغطة مزدوجة**. الواقع أخطر بمرتبة كاملة.
>
> نصّ التقرير: «موظف يضغط زر تحصيل مرتين لأن الاتصال بطيء… طلبان متزامنان يقرآن DRAFT».
>
> الحقيقة: **كل فاتورة في النظام كانت تحصل على `INV-2026-000001`.** حتمياً، تسلسلياً، بلا أي تزامن، ولا ضغطة مزدوجة، ولا حمولة. زبون واحد في يوم واحد ⟶ كل فواتيره تحمل الرقم نفسه.
>
> والمفارقة أن سيناريو التقرير هو **الأصعب** تحقيقاً: زر الدفع في اللوحة محميّ فعلاً بـ `wire:loading.attr="disabled"`. أما العطل الحقيقي فكان يحدث في **كل** معاملة.

---

## 2. العطل الأول — حتمي بنسبة 100٪

### الكود المعطوب

```php
// DocumentNumberGenerator::generate()
$lastRecord = DB::table($table)
    ->whereYear('created_at', $year)
    ->orderByDesc('id')          // ← أحدث صف، لا أعلى رقم
    ->lockForUpdate()
    ->first();

$lastNumber = 0;
if ($lastRecord) {
    preg_match('/(\d+)$/', $lastRecord->$column, $matches);   // ← على NULL!
    $lastNumber = (int) ($matches[1] ?? 0);
}
$nextNumber = $lastNumber + 1;
```

### السلسلة السببية

```
كل حجز يُنشئ فاتورة مسودة  →  invoice_number = NULL
                                      │
                    أحدث صف في invoices هو دائماً مسودة
                                      │
                    orderByDesc('id') يجلبها هي
                                      │
                    preg_match('/(\d+)$/', NULL) لا يجد شيئاً
                                      │
                    lastNumber = 0  →  nextNumber = 1
                                      │
                          INV-2026-000001 ... دائماً
```

### الإثبات — نُفِّذ على الكود، تسلسلياً، بلا تزامن

```
"generated in sequence" => [
    "INV-2026-000001"
    "INV-2026-000001"
    "INV-2026-000001"
    "INV-2026-000001"
    "INV-2026-000001"
]
```

وتشخيص السبب بالضبط:

```
"newest row id"             => 2
"newest row invoice_number" => null            ← مسودة
"preg_match on it yields"   => "(no match)"
"lastNumber becomes"        => 0
"generated NOW"             => "INV-2026-000001"
"but MAX real number is"    => "INV-2026-000007"   ← تُتجاهَل تماماً
```

**لاحظ السطر الأخير:** كان يوجد `INV-2026-000007` في الجدول، والمولّد أصدر `000001` رغم ذلك. أي أن المشكلة ليست «التسلسل يتأخر» بل **المسلسل لا يُقرأ أصلاً**.

---

## 3. العطل الثاني — القفل لا يحجز شيئاً

```php
public static function generate(...): string {
    return DB::transaction(function () use (...) {     // ← معاملة خاصة بها
        $lastRecord = ...->lockForUpdate()->first();
        // ...
        return sprintf('%s-%s-%06d', ...);             // ← يُرجع نصاً فقط
    });                                                // ← COMMIT: القفل يُحرَّر هنا
}
// المستدعي يكتب الرقم لاحقاً، في معاملة أخرى.
```

نمط «**اقرأ، حرِّر القفل، ثم اكتب**» — مكسور بنيوياً. القفل ينتهي قبل أن يُستهلك الرقم، فلا يمنع أحداً من قراءة نفس القيمة.

### وأسوأ: حين لا يوجد صف، لا يوجد قفل

```
"rows this year" => 0
"two sequential calls with no rows at all" => [
    "INV-2026-000001",
    "INV-2026-000001"     ← لم يُكتب شيء بينهما
]
```

`lockForUpdate()` **لا يستطيع قفل صفوف غير موجودة**. وهذا هو بالضبط الدرس المستفاد في `BOOK-02`: لا يمكن قفل **غياب**. هناك حُلّت بقفل صف المزوّد في `users`؛ وهنا تُحَل بصفّ عدّادٍ موجودٍ دائماً.

---

## 4. العطل الثالث — TOCTOU على حرس الحالة

```php
if ($invoice->status !== InvoiceStatus::DRAFT) {   // (1) قراءة — خارج المعاملة، بلا قفل
    throw new \InvalidArgumentException(...);
}
DB::beginTransaction();                            // (2) المعاملة تبدأ بعد الفحص
```

نفس نمط `BOOK-02` بالحرف.

### النمط الصحيح الذي أثبت اتجاه الإصلاح وقتها

```php
// InvoicePaymentService::createFromInvoice() — مثال تاريخي؛ الخدمة حُذفت في MON-05
return DB::transaction(function () use (...) {
    $invoice = Invoice::query()->whereKey($invoice->id)
        ->lockForUpdate()->firstOrFail();      // ✅ قفل ثم إعادة قراءة
    if (!$invoice->status->isPayable()) {      // ✅ الحرس بعد القفل
        throw new InvalidArgumentException('Invoice is not payable.');
    }
```

بعد MON-05 نُقل هذا النمط إلى `finalizeAppointmentPayment()` وصار مستخدماً من
StaffDashboard وكل adapters في Filament.

---

## 5. العطل الرابع — لا قيد فريد على أي رقم مستند

جردت كل ملفات الـ migrations. القيود الفريدة موجودة على `languages.code` و`users.email` و`invoice_templates.name` و`sliders.key` و`cms_pages.slug`… و**لا شيء** على أي رقم مالي:

| الجدول | العمود | الخطر |
|---|---|---|
| `invoices` | `invoice_number` | خرق §14 UStG — الفاتورة غير قابلة للتعريف |
| `invoices` | `appointment_id` | موعد واحد بفاتورتين |
| `payments` | `payment_number` | سجلا دفع لنفس المعاملة = إيراد وهمي |
| `appointments` | `number` | أرقام مواعيد مكررة |
| `provider_service` | `(provider_id, service_id)` | **سعر غير محدد** |

---

## 6. أربعة أعطال لم يذكرها التقرير

### 6.1 `validateInvoiceCreation` تفحص `PAID` فقط ⟶ فاتورتان لموعد واحد

```php
if ($appointment->invoice()->where('status', InvoiceStatus::PAID)->exists()) {
    throw new \Exception('هذا الحجز لديه فاتورة مدفوعة مسبقاً');
}
```

المسودة **لا تُحسب** — وهي موجودة دائماً. فمسار `Filament › Providers › Appointments` يفعل:

```php
$invoiceService->validateInvoiceCreation($record);                // يمرّ
$invoice = $invoiceService->createInvoiceFromAppointment(...);    // يُدرج صفاً جديداً
```

كان الموعد يحمل **فاتورتين**: المسودة المهجورة والمدفوعة الجديدة. حُذف هذا المسار
في MON-05، والكاتب الموحد يعيد استعمال المسودة الموجودة؛ كما يحمي قيد
`invoices.appointment_id` من تكرارها على مستوى قاعدة البيانات.

وحالات `PENDING` و`PARTIALLY_PAID` و`REFUNDED` كلها مستندات صدرت وتحمل أرقاماً، ولم تكن تُحسب أيضاً.

### 6.2 حرس «الموعد ملغى» كود ميت

```php
if ($appointment->status->value === 'admin_cancelled' || $appointment->status->value === 'user_cancelled') {
```

`AppointmentStatus` **enum مدعوم بـ `int`**:

```php
enum AppointmentStatus: int
{
    case ADMIN_CANCELLED = -2;
    case USER_CANCELLED  = -1;
```

فـ `->value` يساوي `-2`، ولا يساوي النص `'admin_cancelled'` **أبداً**. الشرط لا يتحقق مطلقاً — **يمكن إصدار فاتورة لموعد ملغى.**

### 6.3 `payment_number` أضعف من `appointments.number`

```php
$random = strtoupper(substr(uniqid(), -6));   // Payment — بلا حلقة إعادة
```

مقابل:

```php
do {                                          // Appointment — فيه حلقة إعادة
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $number = "{$prefix}-{$date}-{$random}";
} while (Appointment::where('number', $number)->exists());
```

`uniqid()` مبنيّ على الميكروثانية، فنداءان في نفس الميكروثانية يُنتجان القيمة نفسها — **وبلا حلقة إعادة وبلا قيد فريد**، لا شيء يلتقط الاصطدام.

### 6.4 خطر تاريخي: نداء TSE شبكي داخل `DB::transaction()`

TSE متوقف حالياً بقرار صريح، و`finalizeAppointmentPayment()` لا يجري أي نداء
شبكي. عند مشروع التفعيل المستقبلي يجب إعادة تصميم حدود المعاملة بعناية حتى لا
يبقى قفل العدّاد أو الفاتورة محتجزاً أثناء رحلة خارجية طويلة.

---

## 7. تصحيحات أخرى للتقرير

### 7.1 الإصلاح المقترح للعدّاد يحمل **نفس عطل** الكود الحالي

```php
// مقترح التقرير
public function next(string $type, ...): string {
    return DB::transaction(function () use (...) {      // ← معاملة خاصة به مرة أخرى!
        $counter = DB::table('document_counters')->where('type', $type)
            ->lockForUpdate()->first();
        // ...
        return $prefix . '-' . str_pad(...);
    });                                                  // ← COMMIT، القفل يُحرَّر
}
```

`DB::transaction()` داخل `next()` **يُثبِّت ويُحرِّر القفل قبل أن يستهلك المستدعي الرقم**. ولو ارتدّت معاملة المستدعي، بقي العدّاد مرتفعاً ⟶ **فجوة**.

يعمل بالصدفة إن كان المستدعي داخل معاملة أصلاً (Laravel يحوّل الداخلية إلى savepoint) — **والاعتماد على تلك الصدفة هو بالضبط ما أنتج العطل الحالي.** لذلك الإصلاح المُنفَّذ **يرفض** النداء خارج معاملة بـ `RuntimeException` بدل أن يعتمد على الحظ.

### 7.2 `unique(['provider_id', 'start_time'])` **سيكسر ميزة قائمة**

التقرير يقترحه في `DB-01` لعلاج `BOOK-02`. لكن صلاحية `force_booking` **تسمح بالتداخل المتعمد** (مانيكير أثناء تفاعل الصبغة) وهو قرار تصميمي موثَّق. القيد سيرفضه. **لم يُضَف.** منع الحجز المزدوج غير المقصود يقع في `BookingLockService` + `assertNoConflictingAppointment()`، لا في فهرس.

### 7.3 دقة قانونية

التقرير يقول «GoBD يشترط التسلسل **بلا فجوات**». الأدق: §14 UStG يشترط رقماً **متسلسلاً يُمنح مرة واحدة** (`einmalig vergeben`) — أي أن **الفرادة إلزامية**، والفجوات يجب أن تكون **قابلة للتفسير** لا أنها قاتلة تلقائياً. نُفِّذ الترقيم بلا فجوات لأنه الموقف الأكثر أماناً ورخيص التحقيق، لكن الإلزامي هو الفرادة.

---

## 8. الإصلاح — كل تعديل بالكود

### 8.1 جدول جديد: `document_counters`

**الملف:** [`2026_09_10_120000_create_document_counters_table.php`](../../database/migrations/2026_09_10_120000_create_document_counters_table.php)

```php
Schema::create('document_counters', function (Blueprint $table) {
    $table->string('series', 32)->primary();     // 'invoice' | 'payment'
    $table->string('period', 16);                // '2026' — السنة التي يعدّها
    $table->unsignedBigInteger('current')->default(0);
    $table->timestamps();
});
```

**قرارات في هذا التصميم:**

| القرار | السبب |
|---|---|
| `series` مفتاح أساسي نصّي | صفٌّ واحد لكل مسلسل، فلا حاجة لـ `id` |
| الصفوف **تُبذَر في الـ migration** | `lockForUpdate()` لا يقفل صفاً غير موجود. لا فرع «أنشئه إن لم يوجد» في المسار الساخن يمكن أن يفلت بلا قفل |
| `period` عمود مستقل | تصفير السنة يحدث **داخل القفل**، فلا يمكن أن يُصفّره اثنان |
| لا `AUTO_INCREMENT` | قيمته **لا ترتد** مع المعاملة ⟶ فجوات |

والبذر يشتق القيمة الابتدائية من **أعلى رقم موجود فعلاً**:

```php
'current' => $this->highestSuffix('invoices', 'invoice_number', $year),
```

لو بدأنا من صفر لأصدرنا أرقاماً تصطدم بفواتير مطبوعة مسبقاً.

---

### 8.2 `app/Services/DocumentNumberGenerator.php` — أُعيدت كتابته

#### أ) الحرس الذي يجعل العطل مستحيلاً لا مجرد غير محتمل

```php
if (DB::transactionLevel() === 0) {
    throw new RuntimeException(
        "DocumentNumberGenerator::next('{$series}') must be called inside a "
        . 'transaction so the number is reserved and consumed atomically. '
        . 'Wrap the caller in DB::transaction().'
    );
}
```

#### ب) القفل على صفّ موجود دائماً

```php
// قبل — يمسح جدول المستندات ويستنتج
$lastRecord = DB::table($table)->whereYear('created_at', $year)
    ->orderByDesc('id')->lockForUpdate()->first();
preg_match('/(\d+)$/', $lastRecord->$column, $m);

// بعد — يقرأ عدّاداً
$counter = DB::table('document_counters')
    ->where('series', $series)
    ->lockForUpdate()
    ->first();

if (! $counter) {
    throw new RuntimeException(
        "Missing document counter row for series '{$series}'. Run the migrations — "
        . 'the row is seeded there, never created on the hot path, because '
        . 'lockForUpdate() cannot lock a row that does not exist.'
    );
}
```

**لا مسح للجدول إطلاقاً.** فلا صفٌّ مشوَّه أو `NULL` يستطيع إعادة المسلسل إلى الصفر.

#### ج) تصفير السنة داخل القفل

```php
$current = $counter->period === $year ? (int) $counter->current : 0;
$next    = $current + 1;

DB::table('document_counters')->where('series', $series)->update([
    'period'  => $year,
    'current' => $next,
    'updated_at' => now(),
]);

return sprintf('%s-%s-%0' . $padding . 'd', $prefix, $year, $next);
```

#### د) التوقيع القديم محفوظ

```php
/** @deprecated استخدم next() مباشرةً بأسماء المسلسلات. */
public static function generate(string $table, string $column, string $prefix): string
{
    $series = match ($table) {
        'invoices' => 'invoice',
        'payments' => 'payment',
        default    => $table,
    };
    Log::debug('DocumentNumberGenerator::generate() is deprecated; call next() instead.', [...]);
    return self::next($series, $prefix);
}
```

و`peek($series)` جديدة للتشخيص — تقرأ ولا تستهلك ولا تقفل.

---

### 8.3 `app/Models/Invoice.php` و `Payment.php`

```php
// قبل
public static function generateInvoiceNumber(): string
{
    $prefix = 'INV';
    return DocumentNumberGenerator::generate('invoices', 'invoice_number', $prefix);
}

// بعد
public static function generateInvoiceNumber(): string
{
    return DocumentNumberGenerator::next('invoice', 'INV');
}
```

```php
// قبل — uniqid() بلا حلقة إعادة وبلا قيد
public static function generatePaymentNumber(): string
{
    $prefix = 'PAY';
    $date = now()->format('Ymd');
    $random = strtoupper(substr(uniqid(), -6));
    return "{$prefix}-{$date}-{$random}";
}

// بعد
public static function generatePaymentNumber(): string
{
    return \App\Services\DocumentNumberGenerator::next('payment', 'PAY');
}
```

**تغيير الصيغة:** `PAY-20260910-A1B2C3` ⟶ `PAY-2026-000001`.

#### و`Payment::refund()` صارت داخل معاملة

```php
// قبل — بلا معاملة، فالمولّد سيرفض النداء الآن
public function refund(?float $amount = null): self
{
    $refund = self::create([... 'payment_number' => self::generatePaymentNumber() ...]);
    if ($refundAmount < $this->amount) { $this->update([...]); } else { $this->update([...]); }
    return $refund;
}

// بعد
return DB::transaction(function () use ($refundAmount) {
    $refund = self::create([...]);
    // ... تحديث حالة الأصل ...
    return $refund;
});
```

وهذا تحسين صحّة بحد ذاته: الاسترجاع وتحديث حالة الأصل تغييران يجب أن يقعا معاً، وإلا بقي سجل استرجاع بلا أصلٍ معلَّم — أو العكس.

---

### 8.4 `app/Exceptions/InvoiceAlreadyFinalizedException.php` — جديد

```php
class InvoiceAlreadyFinalizedException extends RuntimeException
{
    public function __construct(
        public readonly Invoice $invoice,
        ?string $message = null,
    ) { ... }
}
```

**لماذا استثناء خاص وليس `InvalidArgumentException` عامّاً؟** لأن هذه ليست حالة خطأ من الموظف: إنها **الطلب الثاني من ضغطة مزدوجة** أو إعادة محاولة بعد انقطاع شبكة وصل فيها الطلب الأول فعلاً. النقود قُبضت والفاتورة صدرت، والشيء الصحيح أن يُقال للموظف «مُنهاة، رقمها كذا» ويُعاد الطبع — لا أن تُعرض رسالة فشل مُفزعة عن معاملة **نجحت**. فهو يحمل الفاتورة معه ليتمكن المستدعي من ذلك.

---

### 8.5 `app/Services/InvoiceFinalizationService.php` — قفل صفّي

```php
// قبل
if ($invoice->status !== InvoiceStatus::DRAFT) { throw ...; }   // خارج المعاملة
if (!$invoice->appointment) { throw ...; }
DB::beginTransaction();
try {
    $tseData = ...;
    $invoiceNumber = Invoice::generateInvoiceNumber();
    // ...
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // ...
}

// بعد
return DB::transaction(function () use (...) {
    // أعِد القراءة تحت القفل
    $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

    // الحرس **بعد** القفل
    if ($invoice->status !== InvoiceStatus::DRAFT) {
        throw new InvoiceAlreadyFinalizedException($invoice);
    }
    if (! $invoice->appointment) {
        throw new \InvalidArgumentException('الفاتورة غير مرتبطة بحجز');
    }

    try {
        $tseData = ...;                                 // TSE أولاً
        $invoiceNumber = Invoice::generateInvoiceNumber(); // ثم حجز الرقم
        // ...
    } catch (InvoiceAlreadyFinalizedException $e) {
        throw $e;                                        // ليست فشلاً — تمرّ كما هي
    } catch (\Throwable $e) {
        // لا rollBack() يدوياً داخل DB::transaction() — الارتداد تلقائي
        Log::error('Failed to finalize invoice', ['exception' => $e::class, ...]);
        throw new \RuntimeException('فشل في إتمام الفاتورة: ' . $e->getMessage(), 0, $e);
    }
});
```

**ترتيب TSE قبل الرقم مقصود:** العدّاد مقفول من لحظة `generateInvoiceNumber()` حتى `COMMIT`، فلا نريد رحلة شبكية داخل تلك النافذة.

**و`catch (\Exception)` صار `catch (\Throwable)`:** `TypeError` يرث `Error` لا `Exception` (نفس درس `BOOK-09`).

---

### 8.6 `app/Livewire/StaffDashboard.php` — الضغطة المزدوجة تُعاد طبعها لا تُفشَل

```php
// قبل
} catch (\Exception $e) {
    Log::error('Payment error: ' . $e->getMessage(), [...]);
    $this->dispatch('notify', type: 'error', message: $e->getMessage());
}

// بعد
} catch (\App\Exceptions\InvoiceAlreadyFinalizedException $e) {
    // الطلب الثاني من ضغطة مزدوجة، أو إعادة محاولة بعد انقطاع وصل فيه الأول.
    // النقود قُبضت والفاتورة صدرت — الموظف لا يجوز أن يرى فشلاً لمعاملة نجحت.
    Log::info('Duplicate finalization suppressed', [
        'invoice_id' => $e->invoice->id,
        'invoice_number' => $e->invoice->invoice_number,
    ]);

    $this->closePaymentModal();
    $this->dispatch('notify', type: 'success', message: __('dashboard.payment_modal.success'));
    $this->dispatch('printInvoice', invoiceId: $e->invoice->id);
} catch (\Throwable $e) {
    Log::error('Payment error: ' . $e->getMessage(), ['exception' => $e::class, ...]);
    $this->dispatch('notify', type: 'error', message: $e->getMessage());
}
```

---

### 8.7 `app/Services/InvoiceService.php` — فاتورة واحدة لكل موعد

#### أ) `createInvoice()` يرفع المسودة بدل أن يُدرج صفاً ثانياً

```php
// قبل — INSERT دائماً
return Invoice::create([
    'appointment_id' => $appointment->id,
    'invoice_number' => Invoice::generateInvoiceNumber(),
    // ...
]);

// بعد
$existing = Invoice::query()
    ->where('appointment_id', $appointment->id)
    ->lockForUpdate()
    ->first();

if ($existing) {
    if ($existing->status !== InvoiceStatus::DRAFT) {
        throw new \App\Exceptions\InvoiceAlreadyFinalizedException($existing);
    }

    $existing->update($payload + [
        'invoice_number' => $existing->invoice_number ?: Invoice::generateInvoiceNumber(),
    ]);

    return $existing->refresh();
}

return Invoice::create($payload + [
    'appointment_id' => $appointment->id,
    'invoice_number' => Invoice::generateInvoiceNumber(),
]);
```

القفل ضروري: طلبان متزامنان قد يجدان المسودة نفسها ويرفعانها.

#### ب) `validateInvoiceCreation()` — العطلان الإضافيان

```php
// قبل
if ($appointment->invoice()->where('status', InvoiceStatus::PAID)->exists()) {
    throw new \Exception('هذا الحجز لديه فاتورة مدفوعة مسبقاً');
}
// ...
if ($appointment->status->value === 'admin_cancelled' || $appointment->status->value === 'user_cancelled') {
    throw new \Exception('لا يمكن إنشاء فاتورة لحجز ملغي');
}

// بعد
if ($appointment->invoice()->where('status', '!=', InvoiceStatus::DRAFT)->exists()) {
    throw new \Exception('هذا الحجز لديه فاتورة مُنهاة مسبقاً');
}
// ...
if (in_array($appointment->status, [
    AppointmentStatus::ADMIN_CANCELLED,
    AppointmentStatus::USER_CANCELLED,
    AppointmentStatus::NO_SHOW,
], true)) {
    throw new \Exception('لا يمكن إنشاء فاتورة لحجز ملغي');
}
```

المقارنة على **حالات الـ enum نفسها** لا على `->value`، فلا يمكن أن تُخفق بسبب نوع القيمة. و`NO_SHOW` أُضيفت: من لم يحضر لا تُصدر له فاتورة.

---

### 8.8 `app/Services/ServiceAvailabilityService.php` — تعارض السعر

```php
// قبل — لا يفلتر is_active
$pivot = DB::table('provider_service')
    ->where('provider_id', $provider->id)
    ->where('service_id', $service->id)
    ->first();

// بعد — يطابق getEffectivePrice() بالضبط
$pivot = DB::table('provider_service')
    ->where('provider_id', $provider->id)
    ->where('service_id', $service->id)
    ->where('is_active', true)
    ->first();
```

صفٌّ pivot **معطَّل** كان يحدّد السعر **المعروض** بينما طبقة الحجز تتجاهله و**تُحصّل** السعر الأساسي — فالعميل يرى رقماً في التطبيق ويُطالَب بآخر عند الكاشير.

---

### 8.9 الـ Seeders — الأعطال التي كشفتها القيود

القيود الجديدة أظهرت عطلين حقيقيين في البذر:

#### أ) `ProviderServiceSeeder` — تكرار على تشغيل **نظيف** لا على إعادة تشغيل فقط

```php
// قبل — الحلقة الثانية
foreach ($services as $service) {
    $existingLink = DB::table('provider_service')
        ->where('service_id', $service->id)->where('is_active', true)->exists();

    if (!$existingLink) {
        $provider = $providers->random();          // ← قد يكون مرتبطاً أصلاً!
        DB::table('provider_service')->insert([...]);
    }
}
```

الحلقة الأولى تربط بعض الأزواج بـ `is_active = false` (باحتمال ~10٪). فخدمةٌ بلا رابط **نشط** تدخل الحلقة الثانية، وقد يختار `->random()` نفس المزوّد المرتبط بها معطَّلاً ⟶ **زوج مكرر على تشغيل نظيف**.

```php
// بعد
if ($existingLink) { continue; }

$linkedProviderIds = DB::table('provider_service')
    ->where('service_id', $service->id)->pluck('provider_id')->all();

$unlinked = $providers->whereNotIn('id', $linkedProviderIds);
$provider = $unlinked->isNotEmpty() ? $unlinked->random() : $providers->random();

DB::table('provider_service')->updateOrInsert(
    ['service_id' => $service->id, 'provider_id' => $provider->id],
    ['is_active' => true, ...]
);
```

وكل `insert` صار `updateOrInsert` مفتاحه الزوج — فالبذر صار **قابلاً لإعادة التشغيل** أيضاً (كان يفشل قبل ذلك).

#### ب) `AppointmentSeeder` — `uniqid()` مقابل قيد فريد

```php
// قبل
'number' => 'APT-' . strtoupper(uniqid()),

// بعد — يُحذف الحقل ويولّده الموديل
// `number` is deliberately omitted: Appointment::creating() fills it via
// BookingService::generateAppointmentNumber(), which retries on collision.
```

`Appointment::creating()` يستدعي المولّد ذا حلقة الإعادة، والصيغة صارت مطابقة لما تكتبه بيئة الإنتاج.

---

### 8.10 مايقريشن الإصلاح والقيود

**الملف:** [`2026_09_10_120100_repair_and_enforce_document_number_uniqueness.php`](../../database/migrations/2026_09_10_120100_repair_and_enforce_document_number_uniqueness.php)

**لماذا الإصلاح والقيد في مايقريشن واحدة؟** لا يمكن إضافة `UNIQUE` على عمود فيه تكرارات — ستفشل. وبين التنظيف وإضافة القيد توجد نافذة يمكن أن تُنتج فيها تكرارات جديدة. فالخطوتان معاً، بهذا الترتيب، في معاملة واحدة.

الخطوات السبع بالترتيب:

| # | الخطوة | ما تفعله |
|---|---|---|
| 1 | `repairInvoiceNumbers()` | الأقدم يُبقي رقمه؛ البقية تأخذ أرقاماً حرّة من نهاية المسلسل، **مع توثيق** |
| 2 | `repairDuplicateInvoicesPerAppointment()` | تُحفظ الفاتورة المُنهاة وتُحذف المسودات المهجورة |
| 3 | `repairSimpleNumberColumn('payments', …)` | ترقيم تكرارات أرقام الدفع |
| 4 | `repairSimpleNumberColumn('appointments', …)` | ترقيم تكرارات أرقام المواعيد |
| 5 | `deduplicateProviderService()` | يُحفظ الصف **النشط الأقدم** |
| 6 | `reseedCounters()` | يُعاد ضبط العدّادات على أعلى رقم **بعد** الإصلاح |
| 7 | `addConstraints()` | القيود الخمسة |

#### التوثيق هو ما يجعل الترقيم مقبولاً محاسبياً

```php
$data['number_correction'] = [
    'previous_number' => $row->invoice_number,
    'new_number'      => $newNumber,
    'corrected_at'    => now()->toISOString(),
    'reason'          => 'MON-03: DocumentNumberGenerator read the newest invoice row '
        . '(ORDER BY id DESC) instead of the highest number. Draft invoices carry a NULL '
        . 'invoice_number, so the suffix parsed as 0 and every invoice was issued as '
        . '<PREFIX>-<YEAR>-000001. Duplicates were renumbered to satisfy the uniqueness '
        . 'required by §14 UStG; the earliest invoice of each duplicate group kept its number.',
];
```

#### الحالة الوحيدة التي ترفض المايقريشن معالجتها

```php
if ($finalized->count() > 1) {
    throw new RuntimeException(sprintf(
        'Appointment #%s carries %d FINALIZED invoices (%s). This migration will not delete '
        . 'an issued document. Decide with your accountant which one stands, cancel the other '
        . '(status CANCELLED) or detach it, then re-run the migration.',
        ...
    ));
}
```

**حذف مستند صادر قرار المحاسب، لا قرار مايقريشن.** تفشل بصوت عالٍ وتشرح ما يجب فعله.

#### `down()` لا يُعيد كسر السجل

```php
public function down(): void
{
    // القيود فقط تُرفَع. الأرقام المُصحَّحة **لا تُعاد** إلى تكرارها:
    // إرجاعها يعني إعادة كسر السجل، ولا معنى محاسبياً لذلك.
```

---

### 8.11 `app/Console/Commands/DocumentNumberAudit.php` — جديد، للقراءة فقط

```bash
php artisan documents:number-audit
php artisan documents:number-audit --csv=storage/app/number-audit.csv
```

المخرَج على قاعدة مكسورة حقيقية:

```
  رقم الفاتورة — قيم مكررة ................................ 1 قيمة تغطي 4 صفاً
  رقم الدفع — قيم مكررة ................................... 1 قيمة تغطي 4 صفاً
  رقم الموعد — قيم مكررة .................................. 1 قيمة تغطي 4 صفاً
  مواعيد بأكثر من فاتورة ....................................................... 1
  أزواج provider_service مكررة ................................................. 1

  القيود الفريدة
  invoices_number_unique .................................................. مفقود
  invoices_appointment_unique ............................................. مفقود
  payments_number_unique .................................................. مفقود
  appointments_number_unique .............................................. مفقود
  provider_service_unique ................................................. مفقود

  العدّادات
  invoice ................................................ period 2026، آخر رقم 0
  payment ................................................ period 2026، آخر رقم 0

  +------------------+------------------------+---------------------+------------+
  | الجدول           | العمود                 | القيمة              | عدد الصفوف |
  +------------------+------------------------+---------------------+------------+
  | invoices         | invoice_number         | INV-2026-000001     | 4          |
  | payments         | payment_number         | PAY-20260910-ABC123 | 4          |
  | appointments     | number                 | APT-DUP-0001        | 4          |
  | invoices         | appointment_id         | 5                   | 2          |
  | provider_service | provider_id+service_id | 2+1                 | 2          |
  +------------------+------------------------+---------------------+------------+

  WARN  5 مشكلة فرادة. `php artisan migrate` سيُصلحها ويوثّق كل تغيير…
  لم يُعدَّل شيء.
```

> ⚠️ **لا `UPDATE` ولا `INSERT` ولا `DELETE`** — محروس باختبار يؤكد أن الصفوف لا تتغير بعد تشغيله. شغّله **قبل** الترحيل لترى ما ستمسّه، و**بعده** للتأكد أن الفرادة صارت مفروضة.

---

## 9. القرارات التصميمية

### 9.1 لماذا جدول عدّادات وليس `MAX(number) + 1`؟

`MAX + 1` مكسور تحت التزامن: قراءتان متزامنتان تريان نفس القيمة. وهو مكسور **أيضاً** بلا تزامن هنا، لأنه يعتمد على تفسير محتوى عمودٍ قد يكون `NULL` أو بصيغة قديمة. صفّ العدّاد **يحمل الرقم بوصفه رقماً**، لا نصّاً يُفسَّر.

### 9.2 لماذا لا `AUTO_INCREMENT`؟

قيمته **لا ترتد** مع المعاملة. فارتداد واحد ⟶ فجوة دائمة. صف العدّاد يرتد مع كل ما حوله.

### 9.3 لماذا داخل معاملة المستدعي وليس معاملة خاصة؟

هذا **جوهر** الإصلاح، وهو ما أخطأ فيه مقترح التقرير أيضاً. معاملة خاصة تُثبِّت وتُحرِّر القفل **قبل** أن يُستهلك الرقم:

| | معاملة خاصة | معاملة المستدعي ✅ |
|---|---|---|
| القفل يُحرَّر | قبل الكتابة | بعد الكتابة |
| ارتداد المستدعي | العدّاد يبقى مرتفعاً ⟶ **فجوة** | العدّاد يرتد ⟶ لا فجوة |
| طلبان متزامنان | يمكن أن يقرآ نفس القيمة | يتسلسلان |

**الثمن:** كل إنهاء فاتورة يتسلسل على صفٍّ واحد. غير مهم بحجم صالون (عشرات الفواتير يومياً)، وهو الاختيار الذي أكّدتَه.

### 9.4 لماذا `RuntimeException` عند النداء خارج معاملة وليس فتح معاملة تلقائياً؟

فتح معاملة تلقائياً يُعيد إنتاج العطل بصمت: الرقم يُحجز ويُحرَّر ثم يكتبه المستدعي في معاملة أخرى. **الرفض الصريح يجعل الشكل الخاطئ مستحيلاً لا مجرد غير محتمل.**

### 9.5 لماذا `INV-YYYY-NNNNNN` بتصفير سنوي؟

السنة **داخل الرقم**، فالتصفير لا يمكن أن يُنتج اصطداماً: `INV-2026-000001` و`INV-2027-000001` نصّان مختلفان. والتصفير السنوي ممارسة معتادة ومقبولة ألمانياً (`Zahlenreihe` لكل سنة). و`Agent.md` كان يقول `INV-XXXX` — **صُحِّح**، فالكود لم ينتج تلك الصيغة يوماً.

---

## 10. البيانات القديمة و GoBD

### التوتر مع قرار MON-01 — وحلّه

في [`MON-01`](MON-01_vat_calculation_unified.md) كان القرار **«لا نلمس الفواتير المُنهاة إطلاقاً»** — وكان صحيحاً: الصف كان متماسكاً داخلياً (سنت خاطئ، لكن الفاتورة **معرَّفة**).

هنا الوضع مختلف بنيوياً، لسببين:

1. **القيد الفريد لا يمكن إضافته أصلاً** ما دامت التكرارات موجودة. الـ migration ستفشل. لا خيار «اتركها وأضِف القيد».
2. **أرقامٌ متكررة تعني أن الفواتير غير قابلة للتعريف** — وهو بالضبط ما يشترطه §14 UStG (`einmalig vergeben`). فترك التكرار ليس «حفاظاً على السجل»، بل حفاظٌ على **سجلٍ مكسور**.

ومبدأ **Unveränderbarkeit** يمنع التعديل **الصامت**، لا التصحيح **الموثَّق** لخلل نظام. لذلك:

| القرار | التنفيذ |
|---|---|
| الأقدم يُبقي رقمه | النسخة المطبوعة بيد الزبون تبقى مطابقة |
| البقية تُرقَّم من نهاية المسلسل | أقل تغيير ممكن |
| كل تغيير مُوثَّق | `invoice_data.number_correction` + `Log::info` + مخرَج المايقريشن |
| المسودات المهجورة تُحذف | مسودة بلا رقم لم تصدر لأحد ولا قيمة محاسبية لها |
| **فاتورتان مُنهاتان لموعد** | **ترفض المايقريشن** وتطلب قرار المحاسب |

### TSE موقوف حالياً

`FISKALY_ENABLED=false` — لا توقيع TSE على أي فاتورة، فلا صفوف موقّعة تعارض الأرقام الجديدة. **لو كان TSE مفعّلاً لكان الترقيم أصعب بكثير**، لأن التوقيع يشمل رقم الفاتورة. هذا الإصلاح لا يمسّ طبقة TSE، وعندما تُفعَّل ستوقّع على أرقام فريدة.

---

## 11. التحقق العملي

الاختبارات وحدها لا تكفي لمايقريشن تُعدِّل بيانات محاسبية. بنيتُ قاعدة SQLite حقيقية، أسقطتُ القيود لمحاكاة الوضع قبل الإصلاح، وزرعتُ الكسر الحقيقي:

- 4 فواتير كلها `INV-2026-000001` + فاتورة سليمة `INV-2026-000099`
- 4 سجلات دفع كلها `PAY-20260910-ABC123`
- 4 مواعيد كلها `APT-DUP-0001`
- موعد يحمل مسودة **و** فاتورة مُنهاة
- زوج `provider_service` مكرر بسعرين مختلفين (**30 و 45**)

### مخرَج المايقريشن الفعلي

```
invoice_number: 1 duplicated value(s) covering 4 rows.
  invoice #2: INV-2026-000001 -> INV-2026-000100
  invoice #3: INV-2026-000001 -> INV-2026-000101
  invoice #4: INV-2026-000001 -> INV-2026-000102
invoice_number: 3 row(s) renumbered.
  appointment #5: dropped abandoned DRAFT invoice #5 (kept #6 INV-2026-000099)
invoices.appointment_id: 1 appointment(s) had extra invoices; 1 abandoned draft(s) removed.
  payments #2: PAY-20260910-ABC123 -> PAY-2026-000001
  payments #3: PAY-20260910-ABC123 -> PAY-2026-000002
  payments #4: PAY-20260910-ABC123 -> PAY-2026-000003
payments.payment_number: 1 duplicated value(s), 3 row(s) renumbered.
  appointments #2: APT-DUP-0001 -> APT-2026-000001
  appointments #3: APT-DUP-0001 -> APT-2026-000002
  appointments #4: APT-DUP-0001 -> APT-2026-000003
appointments.number: 1 duplicated value(s), 3 row(s) renumbered.
  provider 2 / service 1: kept row #1 (price 30), removed 1 duplicate(s)
provider_service: 1 duplicated pair(s), 1 row(s) removed.
counter "invoice" reseeded to 102 for period 2026.
counter "payment" reseeded to 3 for period 2026.
Unique constraints applied: …
```

**لاحظ:** الترقيم بدأ من `000100` لا `000002` — لأنه يتجاوز `INV-2026-000099` الموجودة. والأقدم أبقى `000001`.

### التحقق بعد الإصلاح

```
invoice #2 number: INV-2026-000100
audit trail: {
    "previous_number": "INV-2026-000001",
    "new_number": "INV-2026-000100",
    "corrected_at": "2026-09-10T18:40:01.031255Z",
    "reason": "MON-03: DocumentNumberGenerator read the newest invoice row …"
}

earliest kept its printed number: INV-2026-000001
next generated: INV-2026-000103
```

والتشخيص بعد الترحيل نظيف تماماً، والقيود الخمسة `مفروض`.

### توليد الأرقام على قاعدة حقيقية

```
invoice numbers: INV-2026-000001, INV-2026-000002, INV-2026-000003, INV-2026-000004
payment numbers: PAY-2026-000001, PAY-2026-000002, PAY-2026-000003
counter: {"series":"invoice","period":"2026","current":4}
outside txn: RuntimeException
```

### البذر مقابل القيود

كل الـ 25 seeder تمرّ. وإعادة تشغيل `ProviderServiceSeeder`:

```
pairs: 46   distinct: 46
```

### الاختبارات

| الملف | العدد | ماذا يحرس |
|---|---|---|
| [`DocumentNumberingTest.php`](../../tests/Feature/Money/DocumentNumberingTest.php) | 15 | التصاعد، عدم الارتباك بمسودة أحدث، الاستمرار بعد أرقام قائمة، لا فجوة عند الارتداد، تصفير السنة، القيود الخمسة، الضغطة المزدوجة تنتج **سجل دفع واحداً**، ترقيم الدفع، رفع المسودة، رفض الموعد الملغى |
| [`NumberAuditCommandTest.php`](../../tests/Feature/Money/NumberAuditCommandTest.php) | 3 | يكتشف التكرار (بإسقاط القيد مؤقتاً لمحاكاة بيانات قديمة)، ولا يعدّل شيئاً، ويفشل عند فاتورتين مُنهاتين |
| [`DocumentNumberGeneratorGuardTest.php`](../../tests/Unit/DocumentNumberGeneratorGuardTest.php) | 1 | رفض النداء خارج معاملة |

**والحرس له أسنان:** أُعيد الكود القديم بـ `git stash` ⟶ **9 من 15 تفشل**؛ ومع المُصلَح ⟶ **15 ناجحة**.

#### ⚠️ ملاحظتان صادقتان عن حدود هذه الاختبارات

1. **SQLite يتجاهل `SELECT … FOR UPDATE`.** فالاختبارات تتحقق من **المنطق** (أن الحرس يُعاد تقييمه بعد إعادة قراءة داخل المعاملة، وأن الأرقام تأتي من عدّاد لا من مسح جدول، وأن قاعدة البيانات نفسها ترفض التكرار). سلوك التزامن الحقيقي يعتمد على قفل MySQL ولا يمكن تأكيده هنا. هذه نفس القيد المعروف في [`booking-lock-contract`](../../docs/BOOKING_FLOW.md).

2. **حرس «خارج معاملة» لا يمكن اختباره في مجموعة الـ Feature:** `RefreshDatabase` يلفّ كل اختبار في معاملة، فـ `DB::transactionLevel()` يساوي 1 والحرس يصمت **بحق**. لذلك هو في `tests/Unit/` بصنف يمتد `Tests\TestCase` **بلا** `RefreshDatabase` — التطبيق مُشغَّل (ليعمل الـ facade) والقاعدة غير ملفوفة. والحرس أول سطر في `next()` فلا يحتاج مايقريشن.

### خط الأساس

```
قبل:  10 failed, 3 skipped, 429 passed
بعد:  10 failed, 3 skipped, 448 passed
```

العشرة الباقية هي نفس الفئات المعروفة غير المرتبطة: Fiskaly (5 — موقوف عمداً)، ProfileImageUpload (3)، DeleteAccount، ExampleTest. **صفر تراجعات.**

---

## 12. قواعد يجب عدم كسرها

### 🔴 1. أي رقم مستند متسلسل يأتي من `DocumentNumberGenerator::next()` وحده

لا `MAX(number) + 1`، ولا `ORDER BY id DESC`، ولا `uniqid()`، ولا `while (exists())`. المولّد يقرأ **عدّاداً**، ولا يفسّر محتوى عمودٍ قد يكون `NULL`.

### 🔴 2. الرقم يُحجز ويُستهلك في **معاملة واحدة** — معاملة المستدعي

قفلٌ يُحرَّر قبل الكتابة لا يحجز شيئاً. المولّد يرفض النداء خارج معاملة بـ `RuntimeException` — **لا تُسكت هذا الحرس، ولا تلفّ `next()` في `DB::transaction()` خاصة به.**

### 🔴 3. حرس الحالة يقع **بعد** القفل، داخل المعاملة

```php
DB::transaction(function () {
    $row = Model::whereKey($id)->lockForUpdate()->firstOrFail();  // 1. اقفل
    if ($row->status !== Expected) { throw ...; }                  // 2. ثم افحص
    // 3. ثم اكتب
});
```

الفحص خارج المعاملة **رفضٌ سريع فقط، لا ضمان** — نفس عقد `BOOK-02`.

### 🔴 4. لا يمكن قفل غياب

`lockForUpdate()` على استعلام لا يُرجع صفاً **لا يقفل شيئاً**. صف العدّاد يُبذَر في المايقريشن ولا يُنشأ في المسار الساخن، تماماً كما تُقفل صفوف `users` في `BookingLockService` بدل صفوف `appointments` غير الموجودة.

### 🔴 5. الحماية بطبقتين: تطبيقية **و** على مستوى قاعدة البيانات

القفل يمنع التسابق؛ والقيد الفريد يمنع **كل ما نسيناه**. مسار كتابة جديد ينسى القفل يخترق الطبقة الأولى — والثانية توقفه. أي جدول يحمل رقم مستند يجب أن يحمل `UNIQUE` عليه.

### 🔴 6. المقارنة على حالات الـ enum، لا على `->value`

```php
// ❌ كود ميت — enum مدعوم بـ int لا يساوي نصاً أبداً
if ($appointment->status->value === 'admin_cancelled') { … }

// ✅
if (in_array($appointment->status, [AppointmentStatus::ADMIN_CANCELLED, …], true)) { … }
```

### 🔴 7. الضغطة المزدوجة ليست خطأ الموظف

الطلب الثاني على معاملة نجحت يجب أن يُعاد طبعه لا أن يُفشَل. `InvoiceAlreadyFinalizedException` تحمل الفاتورة معها لهذا الغرض. و`wire:loading.attr="disabled"` حرسٌ **في المتصفح فقط** — لا يمنع تبويباً ثانياً ولا إعادة محاولة بعد انقطاع شبكة.

### 🔴 8. `provider_service` زوجٌ فريد

صفّان لنفس (مزوّد، خدمة) يعنيان **سعراً غير محدد**، لأن كلا الطبقتين تستعمل `->first()`. والطبقتان يجب أن تستعملا **نفس** المرشِّحات (`is_active` مضمَّن في كليهما الآن)، وإلا اختلف السعر المعروض عن المحصَّل.

---

## الملفات المتأثرة

| الملف | التغيير |
|---|---|
| [`app/Services/DocumentNumberGenerator.php`](../../app/Services/DocumentNumberGenerator.php) | أُعيدت كتابته — عدّاد مقفول + حرس المعاملة + `peek()` |
| [`app/Models/Invoice.php`](../../app/Models/Invoice.php) | `generateInvoiceNumber()` ⟶ العدّاد |
| [`app/Models/Payment.php`](../../app/Models/Payment.php) | `generatePaymentNumber()` ⟶ العدّاد · `refund()` صارت داخل معاملة |
| [`app/Services/InvoiceFinalizationService.php`](../../app/Services/InvoiceFinalizationService.php) | قفل صفّي + حرس بعد القفل + `catch (\Throwable)` |
| [`app/Services/InvoiceService.php`](../../app/Services/InvoiceService.php) | `createInvoice()` يرفع المسودة · `validateInvoiceCreation()` عطلان مُصلَحان |
| [`app/Services/ServiceAvailabilityService.php`](../../app/Services/ServiceAvailabilityService.php) | `is_active` في `getProviderServicePricing()` |
| [`app/Livewire/StaffDashboard.php`](../../app/Livewire/StaffDashboard.php) | الضغطة المزدوجة تُعاد طبعها |
| [`app/Exceptions/InvoiceAlreadyFinalizedException.php`](../../app/Exceptions/InvoiceAlreadyFinalizedException.php) | **جديد** |
| [`app/Console/Commands/DocumentNumberAudit.php`](../../app/Console/Commands/DocumentNumberAudit.php) | **جديد** — تشخيص قراءة فقط |
| [`…120000_create_document_counters_table.php`](../../database/migrations/2026_09_10_120000_create_document_counters_table.php) | **جديد** — الجدول + البذر من أعلى رقم موجود |
| [`…120100_repair_and_enforce_document_number_uniqueness.php`](../../database/migrations/2026_09_10_120100_repair_and_enforce_document_number_uniqueness.php) | **جديد** — إصلاح موثَّق + 5 قيود |
| [`database/seeders/ProviderServiceSeeder.php`](../../database/seeders/ProviderServiceSeeder.php) | تكرار على تشغيل نظيف + عدم قابلية إعادة التشغيل |
| [`database/seeders/AppointmentSeeder.php`](../../database/seeders/AppointmentSeeder.php) | `uniqid()` ⟶ مولّد الموديل |
| [`tests/Feature/Money/DocumentNumberingTest.php`](../../tests/Feature/Money/DocumentNumberingTest.php) | **جديد** — 15 اختباراً |
| [`tests/Feature/Money/NumberAuditCommandTest.php`](../../tests/Feature/Money/NumberAuditCommandTest.php) | **جديد** — 3 اختبارات |
| [`tests/Unit/DocumentNumberGeneratorGuardTest.php`](../../tests/Unit/DocumentNumberGeneratorGuardTest.php) | **جديد** — حرس المعاملة |

</div>
