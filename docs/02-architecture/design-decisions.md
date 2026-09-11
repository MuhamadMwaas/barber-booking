# القرارات التصميمية — Design Decisions

> **الملفات:** `Agent.md:28` (Key Design Decisions)، `app/Services/TaxCalculatorService.php:1`، `app/Services/BookingLockService.php:1`، `app/Models/Appointment.php:1`

---

## 1. الأسعار GROSS (شاملة الضريبة)

**القرار:** كل الأسعار في DB شاملة الضريبة. الضريبة تُستخرج عكسيًا.

**البديل المرفوض:** تخزين Net + حساب Gross عند العرض (`net * (1+rate)`).

**لماذا GROSS؟**

| السبب | التفصيل |
|-------|---------|
| القانون الألماني | Preisangabenverordnung — السعر المعروض يجب أن يكون شامل الضريبة |
| البساطة | العميل يرى 50 ويدفع 50 — لا حسابات إضافية في الواجهة |
| الفاتورة | الفاتورة المطبوعة تُظهر net/tax/gross بشكل متسق من نفس المصدر |

**الثمن:** حساب عكسي أكثر تعقيدًا (`gross / (1+rate/100)`) + الحاجة لـ `bcmath` بدقة عالية — انظر `04-services/tax-calculator.md`.

---

## 2. bcmath لكل حسابات المال

**القرار:** كل حسابات المال بـ `bcmath` لا `float`.

**لماذا؟**

```php
// float — خطأ
0.1 + 0.2 === 0.3  // false — 0.30000000000000004
50.00 / 1.19       // 42.016806... — اقتطاع خاطئ بدقة 2 يعطي 42.01 بدل 42.02

// bcmath — صحيح
bcdiv('50.00', '1.19', 10) // "42.0168067226" — ثم تقريب صحيح إلى 42.02
```

**القاعدة الصارمة:**

> ⚠️ لا تستدع `bcscale()` أبدًا — `Agent.md:509`. هي حالة global تغير دقة كل استدعاءات `bcmath` اللاحقة في نفس الطلب. مرّر الدقة صراحة كمعامل ثالث في كل استدعاء.

**أين يُطبق:**
- `TaxCalculatorService.php:40` — `bcdiv`, `bcmul`, `bcadd`, `bcsub` بدقة `max(10, precision+8)`
- `BookingService.php:310` — `calculateTotals` يفوّض لـ `TaxCalculatorService`
- `Invoice.php:80` — `calculateTotals` من `invoice_items`
- `InvoiceItem.php:30` — `booted: saving` يحسب `total_amount`

---

## 3. فوترة على مرحلتين — DRAFT → PAID

**القرار:** فاتورة مسودة عند الحجز (بلا رقم) → فاتورة مدفوعة عند الدفع (مع رقم متسلسل).

**البديل المرفوض:** إنشاء فاتورة مدفوعة مباشرة عند الحجز، أو تأخير إنشاء الفاتورة حتى الدفع.

**لماذا مرحلتان؟**

| المرحلة | الحالة | الرقم | متى | هل هي مستند قانوني؟ |
|---------|--------|-------|-----|---------------------|
| الحجز | `DRAFT (0)` | `NULL` | `BookingService.php:166` داخل transaction الحجز | لا — مجرد تجميع بنود |
| الدفع | `PAID (2)` | `INV-2026-000001` | `InvoiceFinalizationService.php:1` داخل transaction الدفع | نعم — تُطبع وتُحفظ |

**لماذا لا رقم للمسودة؟**

- المسودة ليست مستندًا صادرًا — لا يجب أن تستهلك رقمًا من التسلسل القانوني.
- الفجوات في الترقيم ممنوعة قانونيًا في ألمانيا — لو أُنشئ رقم ثم فشل الحجز، تبقى فجوة.
- الحل: الرقم يُحجز داخل `DB::transaction` في `DocumentNumberGenerator.php:1` — إذا فشلت العملية يُسترجع الرقم.

---

## 4. `created_status = 1` دائمًا

**القرار:** كل حجز يُنشأ مؤكدًا (`created_status=1`) ويحجب وقته فورًا.

**الخلفية:**

- `created_status` كان يميز بين حجز مؤكد (1) وغير مؤكد (0 = ينتظر دفع عربون أونلاين).
- بما أنه **لا يوجد دفع أونلاين**، كل الحجوزات تُدفع نقدًا في المحل، فكلها مؤكدة.
- العمود باقٍ في الـ Schema كـ flag لمستقبل قد يحتاج دفع عربون — `Agent.md:29`.

**أين يُستخدم:**

```php
// app/Models/Appointment.php:150 — Scope الوحيد الذي يحدد "المزود مشغول"
scopeBlocksProviderTime($query) {
    return $query->where('created_status', 1)
                 ->whereIn('status', [PENDING, COMPLETED]);
}
// يُستخدم في مكانين فقط — وهما يتفقان دائمًا:
// - ServiceAvailabilityService.php:80 (حساب التوفر)
// - BookingValidationService.php:120 (التحقق عند الحجز)
// كان اختلافهما هو Bug BOOK-01
```

**القاعدة:** لا تعِد كتابة الشرط inline — استخدم الـ Scope دائمًا.

---

## 5. قفل على `users` لا على `appointments`

**القرار:** منع الحجز المزدوج عبر `SELECT FOR UPDATE` على صفوف `users` (المزودين + العميل)، لا على `appointments`.

**لماذا لا على `appointments`؟**

- الحالة المحمية هي **غياب** موعد — لا يمكن قفل صفوف غير موجودة.
- صف المزود في `users` موجود دائمًا — يمكن قفله.

**التنفيذ — `app/Services/BookingLockService.php:1`:**

```php
public function lockUsers(array $userIds): void {
    $ids = array_unique(array_filter($userIds));
    sort($ids); // ترتيب تصاعدي لمنع deadlock
    User::whereIn('id', $ids)->lockForUpdate()->get();
}
```

- `sort($ids)` يضمن أن طلبين متزامنين يقفلان بنفس الترتيب — لا deadlock.
- يُستدعى **داخل** `DB::transaction` و **قبل** أي فحص — `BookingService.php:105`.

**الفهرس المساعد:**

```sql
-- database/migrations/2026_09_07_120000_add_conflict_lookup_index_to_appointments.php
CREATE INDEX appointments_conflict_lookup_idx
ON appointments (provider_id, appointment_date, created_status, status);
```

يجعل فحص التعارض سريعًا حتى مع آلاف المواعيد.

التفصيل في [`06-booking-flow/concurrency.md`](../06-booking-flow/concurrency.md).

---

## 6. `TaxCalculatorService` — التنفيذ الوحيد للضريبة

**القرار:** كل طبقات النظام التي تحول بين GROSS و NET تمر عبر `TaxCalculatorService` وحده.

**المشكلة التي حلها (MON-01):**

- كان `BookingService::calculateTotals()` يحسب بدقة داخلية 6، و `TaxCalculatorService::extractTax()` بدقة 2.
- `bcdiv` تقتطع لا تقرّب — `50/1.19` بدقة 2 = `42.01` (خطأ)، بدقة 10 ثم تقريب = `42.02` (صحيح).
- النتيجة: نفس المعاملة (50 يورو) لها ضريبتان: `appointments.tax_amount = 7.98` و `invoices.tax_amount = 7.99` — العميل يرى رقمين مختلفين.

**الحل:**

```php
// TaxCalculatorService.php:30 — الدقة الداخلية دائمًا max(10, precision+8)
$internalPrecision = max(10, $precision + 8);
$factor = bcadd('1', bcdiv($taxRate, '100', $internalPrecision), $internalPrecision);
$net = bcdiv($gross, $factor, $internalPrecision);
// ثم تقريب مرة واحدة إلى precision المطلوبة
```

كل الطبقات الآن تفوّض إليه — `BookingService.php:310`، `InvoiceService.php:80`، `Invoice.php:90`.

---

## 7. `PaymentStatus::PENDING` عند الحجز — لا يُعلّم مدفوعًا أبدًا

**القرار:** `payment_status` يبقى `PENDING (0)` عند إنشاء الحجز، حتى لو `payment_method = "cash"`.

**المشكلة التي حلها (BOOK-03):**

- كان `BookingService` يعلّم حجوزات `cash` كـ `PAID_ONSTIE_CASH` فورًا — قبل أن يحضر العميل.
- النتيجة: تقارير الإيرادات تضم أموالًا لم تُقبض بعد.

**الحل — `BookingService.php:58`:**

```php
// Never mark money as received at booking time.
$markAsPaid = $bookingData['mark_as_paid'] ?? false; // دائمًا false من API
$paymentStatus = $markAsPaid ? PAID_ONSTIE_CASH : PENDING; // دائمًا PENDING
```

المال يُسجل فقط في `InvoiceFinalizationService::finalizeAppointmentPayment()` عند الكاشير.

---

## 8. طبقة التوفر والحجز — نفس تعريف "المزود مشغول"

**القرار:** التوفر (`ServiceAvailabilityService`) والحجز (`BookingValidationService`) يستخدمان نفس الـ Scopes:

```php
Appointment::blocksProviderTime()  // created_status=1 + status IN (PENDING, COMPLETED)
         ->overlapping($start, $end) // start < newEnd AND end > newStart (نصف مفتوح)
```

**لماذا؟**

- لو اختلف التعريف، قد يُعرض slot متاح ثم يُرفض عند الحجز (أو العكس).
- كان هذا Bug `BOOK-01` — التوفر يفحص `status=0` فقط، والحجز يفحص `status IN (0,1)` — فجوة تسمح بالحجز المزدوج.

---

## 9. `DocumentNumberGenerator` داخل Transaction فقط

**القرار:** `DocumentNumberGenerator::next('invoice')` يجب أن يُستدعى داخل `DB::transaction` — وإلا يرمي استثناءً.

**لماذا؟**

- يستخدم `SELECT FOR UPDATE` على `document_counters` — يحتاج transaction ليحجز الرقم.
- لو استُدعي خارج transaction، الرقم قد يُستهلك ثم تفشل العملية بعده — فجوة في التسلسل.

```php
// app/Services/DocumentNumberGenerator.php:25
if (!DB::transactionLevel()) {
    throw new LogicException('DocumentNumberGenerator must be called inside a transaction');
}
```

---

## 10. TSE معطّل عمداً

**القرار:** كود Fiskaly/TSE موجود (`app/Services/Fiskaly/` — 6 ملفات) لكنه **خارج مسار الدفع**.

**لماذا؟**

- النظام في مرحلة غير إنتاجية — التوقيع الضريبي الألماني (TSE) يتطلب تكاملًا مراجعًا واختبارًا قانونيًا.
- `InvoiceFinalizationService` يسجل `tse_enabled=false` في `invoice_data` ولا يستدعي Fiskaly.

> إعادة التفعيل تتطلب مشروع تكامل منفصل، ليس مجرد تغيير `FISKALY_API_KEY` في `.env` — `Agent.md:945`.

---

*التالي: [`03-data-models/README.md`](../03-data-models/README.md)*
