# نموذج العمل — Business Model

> **الملفات:** `Agent.md:28` (Key Design Decisions)، `app/Services/InvoiceService.php:1`، `app/Services/InvoiceFinalizationService.php:1`، `app/Services/TaxCalculatorService.php:1`

---

## 1. الفكرة التجارية

صالون تجميل (Beauty Salon) يقدم خدمات: قص شعر، صبغة، مانيكير، بشرة... العميل يحجز موعدًا مع مزود محدد في وقت محدد، يأتي للصالون، يُقدم الخدمة، يدفع، يستلم فاتورة.

النظام **ليس** متجرًا إلكترونيًا ولا منصة دفع. هو **نظام حجز + كاشير + فوترة**.

---

## 2. التسعير — GROSS (شامل الضريبة)

### 2.1 القرار

> **كل الأسعار المخزنة في قاعدة البيانات شاملة الضريبة (GROSS). الضريبة تُستخرج عكسيًا، لا تُضاف.**

```php
// Service.price = 50.00 EUR — هذا شامل 19% ضريبة
// ليس: 50 + 9.50 = 59.50
// بل:  50 = 42.02 (صافي) + 7.98 (ضريبة)
```

**أين يُطبق:**
- `services.price` و `services.discount_price` — `database/migrations/2025_10_10_134548_create_services_table.php`
- `appointment_services.price` — `database/migrations/2025_10_11_130100_create_appointment_services_table.php`
- `appointments.total_amount` — `app/Models/Appointment.php:135`
- `invoices.total_amount` — `app/Models/Invoice.php:30`
- `invoice_items.total_amount` — `app/Models/InvoiceItem.php:15`

### 2.2 لماذا GROSS؟

1. **القانون الألماني:** الأسعار المعروضة للعميل يجب أن تكون شاملة الضريبة (Preisangabenverordnung).
2. **البساطة للعميل:** يرى 50 يورو ويدفع 50 يورو — لا مفاجآت.
3. **التوافق مع الفاتورة:** الفاتورة المطبوعة يجب أن تُظهر الصافي والضريبة والإجمالي بشكل متسق.

### 2.3 كيف تُحسب الضريبة؟

حساب عكسي (Reverse Calculation) عبر `TaxCalculatorService::extractTax()` — `app/Services/TaxCalculatorService.php:25`:

```
net = gross / (1 + rate/100)   // بدقة داخلية max(10, precision+8)
tax = gross - net
// تقريب net و tax إلى منزلتين، ثم تسوية الفرق على الضريبة
```

التفصيل الكامل في [`04-services/tax-calculator.md`](../04-services/tax-calculator.md) و [`Agent.md:488`](../Agent.md).

> **تحذير:** لا تستخدم `float` أبدًا لحساب المال. استخدم `bcmath` عبر `TaxCalculatorService` فقط — `Agent.md:969`.

---

## 3. الدفع — نقدي في المحل فقط

### 3.1 القرار

> **لا يوجد دفع أونلاين. كل الحجوزات تُدفع نقدًا (cash) أو بطاقة (card) عند الكاشير بعد تقديم الخدمة.**

| الحقل | القيمة عند الحجز | القيمة بعد الدفع |
|-------|------------------|------------------|
| `appointments.payment_method` | `"cash"` (نية فقط) | `"cash"` أو `"card"` (فعلي) |
| `appointments.payment_status` | `PENDING (0)` | `PAID_ONSTIE_CASH (2)` أو `PAID_ONSTIE_CARD (3)` |
| `appointments.status` | `PENDING (0)` | `COMPLETED (1)` |
| `invoices.status` | `DRAFT (0)` بلا رقم | `PAID (2)` مع رقم `INV-2026-000001` |
| `payments` | لا يوجد | صف واحد مرتبط بـ `PaymentMethod` |

**الكود:**
- إنشاء الحجز: `app/Services/BookingService.php:58` — `payment_status` دائمًا `PENDING`، `created_status` دائمًا `1`
- الدفع: `app/Services/InvoiceFinalizationService.php:1` — العملية الوحيدة التي تُنشئ `Payment` وتُرقم الفاتورة

### 3.2 لماذا هذا التصميم؟

1. **الواقع التشغيلي:** صالون صغير — العميل يدفع بعد أن يرى النتيجة.
2. **تجنب تعقيدات الدفع الأونلاين:** لا حاجة لـ Stripe/PayPal، لا استرداد أونلاين، لا `created_status=0`.
3. **التقارير صادقة:** الإيرادات تُحسب فقط من الفواتير المدفوعة فعليًا، لا من الحجوزات التي قد لا يحضرها العميل.

### 3.3 ماذا عن `payment_method = "online"`؟

الحقل لا يزال يقبل `"online"` في `BookingCreateRequest` لأسباب تاريخية، لكنه **لا يغير أي سلوك**: `BookingService.php:51` يعلق بوضوح:

```php
// There is no online payment in this system — every booking is settled in
// cash at the shop — so a booking is ALWAYS created confirmed...
$isConfirmed = $bookingData['is_confirmed'] ?? true; // دائمًا true
```

---

## 4. الفوترة على مرحلتين (Two-Stage Invoicing)

```
الحجز (Booking)                          الدفع (Payment at counter)
─────────────                            ──────────────────────────
Appointment +                            InvoiceFinalizationService
  Invoice (DRAFT)                          → رقم متسلسل
  invoice_number = NULL                    → status = PAID
  status = DRAFT                           → Payment (cash/card)
  لا يستهلك رقمًا                          → Appointment → COMPLETED
  لا يُعتبر مستندًا صادرًا                 → يُعتبر مستندًا صادرًا
```

**لماذا مرحلتان؟**

- **المسودة (DRAFT)** ليست فاتورة قانونية — لا رقم لها، لا تُطبع كأصل، لا تُرسل للضرائب. هي مجرد حجز حسابي لتجميع البنود.
- **الفاتورة المدفوعة (PAID)** هي المستند القانوني — لها رقم متسلسل بلا فجوات (`DocumentNumberGenerator` داخل transaction)، تُطبع، تُحفظ.
- الفجوات في الترقيم **ممنوعة** قانونيًا في ألمانيا — لذلك الرقم يُحجز داخل `DB::transaction` فإذا فشلت العملية يُسترجع الرقم.

التفصيل في [`08-invoicing-printing/invoice-lifecycle.md`](../08-invoicing-printing/invoice-lifecycle.md).

---

## 5. الحجز — مؤكد فورًا ويحجب الوقت

```php
// BookingService.php:120
$createdStatus = $isConfirmed ? 1 : 0; // دائمًا 1 حاليًا
// Appointment::scopeBlocksProviderTime() — Agent.md:29
// created_status=1 AND status IN (PENDING, COMPLETED) → يحجب الوقت
```

- `created_status = 1` يعني الحجز **مؤكد** ويحجب وقت المزود في التقويم والتوفر.
- `created_status = 0` كان مخصصًا لحجوزات غير مؤكدة (تنتظر دفع عربون أونلاين) — **لم يعد يُستخدم**. العمود باقٍ في الـ Schema كـ flag لمستقبل قد يحتاج دفع عربون.
- القيمة الافتراضية في DB صارت `1` — `database/migrations/2026_09_07_100000_confirm_all_appointments_by_default.php`.

---

## 6. الضيوف (Guest Booking)

- `appointments.customer_id` nullable — `database/migrations/2025_10_10_145021_create_appointments_table.php`
- إذا `null` → يُستخدم `customer_name/email/phone` المخزنة مباشرة على `appointments`
- الـ Accessors في `app/Models/Appointment.php:120` تتعامل مع الحالتين:

```php
getCustomerNameAttribute() // يرجع full_name للحساب أو customer_name للضيف أو 'Guest'
getCustomerEmailAttribute()
has_customer_account // bool
```

- فحص "العميل الحر" (`assertCustomerIsFree`) يطابق بالهاتف أيضًا عبر `App\Support\PhoneNumber::key()` (آخر 9 أرقام) — `docs/BOOKING_FLOW.md:697`.

---

## 7. الفروع (Branches)

- النموذج `Branch` موجود (`app/Models/Branch.php`) وحقل `branch_id` على `User` و `SalonSetting`.
- **حاليًا:** فرع واحد فقط — `BranchSeeder` ينشئ Main Branch.
- **جاهز للتوسع:** كل استعلامات التوفر تقبل `branch_id` اختياريًا (`AvailabilityController.php:206`).

---

## 8. اللغات

- 3 لغات: `en` (افتراضي تقني)، `ar`، `de` — `LanguageSeeder`
- نظام ترجمة مخصص: `ServiceTranslation` + `ServiceCategoryTranslation` + `ReasonLeaveTranslation`
- `Service::getNameIn($locale)` يرجع الترجمة المناسبة
- الـ API يحدد اللغة بـ `?lang=ar` أو `Accept-Language` — `API.md:128`
- Filament Language Switcher في اللوحة

---

*التالي: [`user-roles.md`](user-roles.md) — الأدوار والصلاحيات*
