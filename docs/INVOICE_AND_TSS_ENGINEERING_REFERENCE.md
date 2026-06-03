# وثيقة هندسية شاملة: نظام الفواتير وتكامل Fiskaly TSE

> **الهدف:** وثيقة مرجعية كاملة تُغني أي مطور جديد أو AI Agent عن قراءة جميع الملفات بشكل منفرد. تشرح هذا القسم من مستوى المتطلبات التجارية وصولاً إلى آخر سطر في الكود.

---

## الفهرس

1. [نظرة عامة — ما هذا النظام ولماذا وُجد؟](#1)
2. [المعمارية العامة — Architecture Overview](#2)
3. [خريطة الملفات الكاملة](#3)
4. [تدفق إنشاء الفاتورة — Request Flow](#4)
5. [تدفق البيانات — Data Flow](#5)
6. [تحليل الموديلات — Models](#6)
7. [تحليل قاعدة البيانات](#7)
8. [قواعد العمل — Business Logic](#8)
9. [تحليل طبقة الخدمات — Services](#9)
10. [نظام القوالب — Invoice Templates](#10)
11. [تكامل Fiskaly TSE الكامل](#11)
12. [نظام الطباعة — Print System](#12)
13. [حساب الضرائب — Tax Calculation](#13)
14. [التحقق والأمان — Validation & Security](#14)
15. [معالجة الأخطاء — Error Handling](#15)
16. [تحليل الأداء — Performance](#16)
17. [مخططات التسلسل — Sequence Diagrams](#17)
18. [أوامر Artisan الخاصة بـ Fiskaly](#18)
19. [دليل المطور — Developer Guide](#19)
20. [الملخص الهندسي النهائي](#20)
21. [المشاكل والمخاطر التفصيلية](#21)

---

<a name="1"></a>
## 1. نظرة عامة — ما هذا النظام ولماذا وُجد؟

### ما المشكلة التي يحلها؟

صالون حلاقة يعمل في ألمانيا. القانون الألماني (KassenSichV — قانون أمان أجهزة النقد الصادر 2020) يُلزم **كل فاتورة نقدية** بأن تكون موقَّعة رقمياً بجهاز TSE (Technical Security Environment) معتمد، لمنع التلاعب في السجلات الضريبية. عدم الامتثال يعني غرامات ضريبية ضخمة.

### الهدف التجاري (Business Goal)

| الهدف | التفصيل |
|-------|---------|
| **الامتثال الضريبي** | توقيع كل فاتورة بـ TSE قبل طباعتها — متطلب قانوني ألماني |
| **الشفافية** | رقم تسلسلي للفواتير بدون فجوات (`INV-2026-000001`) |
| **المحاسبة** | ربط كل مدفوعة بحجز وفاتورة وسجل طباعة |
| **حماية الإيرادات** | الفاتورة تُنشأ أولاً كـ Draft عند الحجز، وتُكتمل عند الدفع فقط |
| **التدقيق** | سجل كامل لكل طباعة مع معلومات الطابعة والمستخدم والوقت |

### لماذا النظام مُقسَّم إلى Draft ثم Paid؟

> التصميم يحل مشكلة حجز الأرقام التسلسلية. لو أُنشئت فاتورة برقم عند كل حجز وألغى العميل الحجز، ستبقى فجوات في الأرقام — وهذا يثير الشك الضريبي. الحل: رقم الفاتورة يُولَّد فقط عند الدفع الفعلي.

---

<a name="2"></a>
## 2. المعمارية العامة

```
┌─────────────────────────────────────────────────────────────────────┐
│                       طلب إنشاء حجز (Booking)                       │
└─────────────────────────────┬───────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    BookingService::createBooking()                   │
│   ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐   │
│   │  Appointment │  │   Services   │  │     Draft Invoice      │   │
│   │   (PENDING)  │  │  (AppSvc)   │  │  (No Number, DRAFT)    │   │
│   └──────────────┘  └──────────────┘  └────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘

                    ══════ وقت لاحق — العميل يدفع ══════

┌─────────────────────────────────────────────────────────────────────┐
│              InvoiceFinalizationService::finalizeDraftInvoice()      │
│                                                                      │
│   ┌──────────────────────────────────────────────────────────────┐  │
│   │  1. applyTSESignature() → Fiskaly API                       │  │
│   │  2. generateInvoiceNumber()  → INV-2026-000001              │  │
│   │  3. DRAFT → PAID                                            │  │
│   │  4. updateAppointmentStatus() → COMPLETED                   │  │
│   │  5. createPaymentRecord()                                   │  │
│   └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘

                    ══════ ما بعد الدفع — الطباعة ══════

┌─────────────────────────────────────────────────────────────────────┐
│                    PrintService::print()                             │
│                                                                      │
│   ┌──────────────────────────────────────────────────────────────┐  │
│   │  Template → TemplateBuilderService → HTML                   │  │
│   │  DynamicFieldResolver → Fiskaly Data → QR Code              │  │
│   │  PrintLog → IncrementPrintCount                              │  │
│   └──────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### مكونات النظام ومسؤولياتها

| المكون | الملف | المسؤولية |
|--------|-------|-----------|
| **BookingService** | `app/Services/BookingService.php` | إنشاء الحجز + Draft Invoice في transaction واحد |
| **InvoiceService** | `app/Services/InvoiceService.php` | إنشاء/إعادة بناء الفواتير وتحويل Draft→Paid |
| **InvoiceFinalizationService** | `app/Services/InvoiceFinalizationService.php` | الاستكمال الكامل: TSE + رقم + Payment record |
| **TaxCalculatorService** | `app/Services/TaxCalculatorService.php` | حساب الضرائب بدقة bcmath — الوحيد المُعتمد |
| **FiskalyService** | `app/Services/Fiskaly/FiskalyService.php` | بوابة دخول كل عمليات Fiskaly |
| **FiskalyClient** | `app/Services/Fiskaly/FiskalyClient.php` | HTTP Client للـ Fiskaly API مع Auth Cache |
| **TssService** | `app/Services/Fiskaly/TssService.php` | إدارة وحدة TSS (إنشاء/تهيئة/تصدير) |
| **TransactionService** | `app/Services/Fiskaly/TransactionService.php` | دورة حياة المعاملة (start→finish→signature) |
| **ClientService** | `app/Services/Fiskaly/ClientService.php` | إدارة Client (جهاز النقد) داخل TSS |
| **ReceiptService** | `app/Services/Fiskaly/ReceiptService.php` | توليد HTML/Thermal للإيصال مع بيانات TSE |
| **TemplateBuilderService** | `app/Services/InvoiceTemplate/TemplateBuilderService.php` | تحويل Template إلى HTML جاهز للطباعة |
| **DynamicFieldResolver** | `app/Services/InvoiceTemplate/DynamicFieldResolver.php` | حل القيم الديناميكية في القالب |
| **PrintService** | `app/Services/Print/PrintService.php` | طباعة الفاتورة + تتبع السجلات |
| **DocumentNumberGenerator** | `app/Services/DocumentNumberGenerator.php` | توليد الأرقام التسلسلية بأمان (lockForUpdate) |
| **PrintController** | `app/Http/Controllers/PrintController.php` | HTTP endpoints للطباعة (web + API) |

---

<a name="3"></a>
## 3. خريطة الملفات الكاملة

### الموديلات (Models)

```
app/Models/
├── Invoice.php              ← الفاتورة الرئيسية
├── InvoiceItem.php          ← بنود الفاتورة
├── InvoiceTemplate.php      ← قالب الفاتورة (ديزاين + إعدادات)
├── TemplateLine.php         ← سطر واحد في القالب
├── Payment.php              ← سجل الدفع (Polymorphic)
└── PrintLog.php             ← سجل كل عملية طباعة
```

### الخدمات (Services)

```
app/Services/
├── InvoiceService.php                    ← إنشاء + إعادة بناء + تحويل الفواتير
├── InvoiceFinalizationService.php        ← الاستكمال الكامل مع TSE + Payment
├── TaxCalculatorService.php              ← حساب الضرائب بـ bcmath
├── DocumentNumberGenerator.php          ← توليد أرقام INV-YYYY-XXXXXX
│
├── Fiskaly/
│   ├── FiskalyService.php               ← Facade رئيسي لكل عمليات Fiskaly
│   ├── FiskalyClient.php                ← HTTP Client + JWT Token Cache
│   ├── TssService.php                   ← CRUD لـ TSS + Admin Auth
│   ├── TransactionService.php           ← دورة حياة المعاملة (KassenSichV schema)
│   ├── ClientService.php                ← Client (نقطة البيع) داخل TSS
│   └── ReceiptService.php               ← HTML/Thermal receipt generator
│
├── InvoiceTemplate/
│   ├── TemplateBuilderService.php       ← المُجمِّع: Template → HTML
│   ├── DynamicFieldResolver.php         ← company.*, invoice.*, fiskaly.*
│   ├── LineTypeRegistry.php             ← (غير مستخدم مباشرة، السجل في config)
│   ├── PdfGeneratorService.php          ← (PDF generation placeholder)
│   ├── TemplatePrintService.php         ← (wrapper حول PrintService)
│   └── TemplateExportImportService.php  ← استيراد/تصدير القوالب
│
└── Print/
    └── PrintService.php                 ← بناء HTML + PrintLog + AutoPrint script
```

### الكنترولرات (Controllers)

```
app/Http/Controllers/
├── PrintController.php          ← GET /invoice/{id}/print + POST /api/invoice/{id}/print
├── InvoiceTemplateController.php← GET /invoice-template/{id}/preview
└── AppointmentPrintController.php ← (طباعة الحجز مباشرة)
```

### قاعدة البيانات (Migrations)

```
database/migrations/
├── 2025_10_25_180109_create_invoices_table.php
├── 2025_10_25_180533_create_invoice_items_table.php
├── 2025_10_25_181800_create_save_payment_methods_table.php
├── 2025_10_25_181959_create_payments_table.php
├── 2026_01_30_000001_create_invoice_templates_table.php
├── 2026_01_31_000002_create_template_lines_table.php
├── 2026_02_01_000001_create_printer_settings_table.php
├── 2026_02_01_000002_create_print_logs_table.php
└── create_fiskaly_tss_table.php         ← fiskaly_tss + fiskaly_clients + fiskaly_transactions + fiskaly_logs
```

### الإعدادات (Config)

```
config/
├── fiskaly.php                  ← API credentials, TSS/Client IDs, Tax rates, Receipt settings
├── invoice-line-types.php       ← تعريف كل أنواع الأسطر (text, tse_info, items_table...)
└── invoice-dynamic-fields.php   ← تعريف كل الحقول الديناميكية (company.*, fiskaly.*, invoice.*)
```

### Provider & Exception

```
app/Providers/FiskalyServiceProvider.php  ← Singleton DI لكل Fiskaly services
app/Exceptions/FiskalyException.php       ← Custom exception مع HTTP rendering
```

### Console Commands

```
app/Console/Commands/
├── FiskalySetup.php         ← php artisan fiskaly:setup   (الإعداد الأول)
├── FiskalyTest.php          ← php artisan fiskaly:test
├── FiskalyAuthTest.php      ← php artisan fiskaly:auth-test
├── FiskalyInitializeTss.php ← php artisan fiskaly:init-tss
├── FiskalyClientCreate.php  ← php artisan fiskaly:create-client
├── FiskalySetClient.php     ← php artisan fiskaly:set-client
├── FiskalyDeleteTss.php     ← php artisan fiskaly:delete-tss
├── FiskalyTssAdminAuth.php  ← php artisan fiskaly:admin-auth
└── FiskalyUnblockPin.php    ← php artisan fiskaly:unblock-pin
```

---

<a name="4"></a>
## 4. تدفق إنشاء الفاتورة — Request Flow التفصيلي

### المرحلة الأولى: إنشاء Draft عند الحجز

```
POST /api/bookings
       │
       ▼
BookingController@store
       │
       ▼
BookingService::createBooking()
       │
       ├─→ [VALIDATION] BookingValidationService
       │
       ├─→ [CALCULATION] calculateTotals()
       │      └── لكل خدمة: TaxCalculatorService::extractTax(gross, 19%)
       │          → net = gross / 1.19
       │          → tax = gross - net
       │          → reconcile: net + tax == gross ؟
       │
       └─→ [DB TRANSACTION]
              │
              ├── Appointment::create(status=PENDING, created_status=1)
              ├── AppointmentService::create() [per service]
              └── InvoiceService::createDtaftInvoiceFromAppointment()
                     │
                     ├── invoice_number = NULL      ← لا رقم بعد
                     ├── status = DRAFT (0)
                     ├── tax_rate من SalonSettings
                     └── InvoiceService::createInvoiceItems()
                            └── InvoiceItem::withoutEvents(function() { ... })
                                ← withoutEvents لمنع Observer من إعادة
                                  الحساب في كل سطر (N saves بدل 1)
```

### المرحلة الثانية: الدفع واستكمال الفاتورة

```
[Admin Panel] — الأدمن يضغط "Finalize" أو "Pay"
       │
       ▼
InvoiceFinalizationService::finalizeDraftInvoice(
    $invoice,
    paymentType = '2' أو '3',   ← PaymentStatus::PAID_ONSTIE_CASH أو CARD
    amountPaid,
    notes
)
       │
       ├─→ [VALIDATE] invoice->status === DRAFT ؟ وإلا → throw InvalidArgumentException
       ├─→ [VALIDATE] invoice->appointment موجود ؟
       │
       ├─→ [DB TRANSACTION]
       │      │
       │      ├── 1. applyTSESignature() → ترجع placeholder حالياً (TODO: Fiskaly)
       │      │       └── أو createPlaceholderTSE() إذا applyTse = false
       │      │
       │      ├── 2. Invoice::generateInvoiceNumber()
       │      │       └── DocumentNumberGenerator::generate('invoices','invoice_number','INV')
       │      │           → DB LOCK → آخر رقم في السنة + 1 → INV-2026-000001
       │      │
       │      ├── 3. invoice->update([
       │      │       invoice_number, status=PAID, invoice_data[tse_data, finalized_at...]
       │      │   ])
       │      │
       │      ├── 4. updateAppointmentStatus()
       │      │       └── كل الـ linkedGroup (parent + children) → COMPLETED + payment_status
       │      │
       │      └── 5. createPaymentRecord()
       │              └── Payment::create([paymentable=Invoice, type=full/partial...])
       │
       └─→ [RETURN] $invoice->fresh(['appointment','customer','items','payments'])
```

### المرحلة الثالثة: التوقيع الرقمي Fiskaly (الحالة الكاملة المستقبلية)

```
FiskalyService::signInvoice($invoice)
       │
       ├─→ isAvailable() ؟
       │      ├── YES → TransactionService::createFromInvoice($invoice)
       │      └── NO  → processOfflineInvoice() [offline mode]
       │
       └─→ TransactionService::createFromInvoice()
              │
              ├── 1. start(tssId, clientId, transactionId=UUID)
              │       └── PUT /tss/{id}/tx/{txId}?tx_revision=1
              │           → state: 'ACTIVE'
              │
              ├── 2. finish(tssId, transactionId, schema)
              │       └── PUT /tss/{id}/tx/{txId}?tx_revision=2
              │           → state: 'FINISHED'
              │           → signature: { value, algorithm, counter, public_key }
              │           → qr_code_data
              │
              ├── 3. storeTransactionData() → جدول fiskaly_transactions
              │
              └── 4. invoice->update(['segnture' => signature.value])
                   FiskalyService::storeTransactionData() → invoice_data JSON
```

---

<a name="5"></a>
## 5. تدفق البيانات — Data Flow

### مسار المبالغ من الحجز إلى الفاتورة

```
خدمة A: price = 50.00 EUR (GROSS — شامل الضريبة)
خدمة B: price = 30.00 EUR (GROSS)
─────────────────────────────────────────────────────
BookingService::calculateTotals()
  للخدمة A:
    net  = 50.00 / 1.19 = 42.016... → rounded → 42.02
    tax  = 50.00 - 42.02 = 7.98
    (تحقق: 42.02 + 7.98 = 50.00 ✓)
  
  للخدمة B:
    net  = 30.00 / 1.19 = 25.21
    tax  = 30.00 - 25.21 = 4.79
    (تحقق: 25.21 + 4.79 = 30.00 ✓)
  
  المجموع:
    subtotal = 42.02 + 25.21 = 67.23
    tax_sum  = 7.98 + 4.79   = 12.77
    total    = 80.00
    
    تحقق نهائي: 67.23 + 12.77 = 80.00 ✓
    (إذا كان هناك فرق بسبب التقريب، يُضاف للضريبة)

─────────────────────────────────────────────────────
Appointment يُخزَّن:
  subtotal     = 67.23
  tax_amount   = 12.77
  total_amount = 80.00

─────────────────────────────────────────────────────
Draft Invoice (عند الحجز):
  invoice_number = NULL
  status = 0 (DRAFT)
  subtotal / tax_amount / total_amount ← مأخوذة من Appointment

─────────────────────────────────────────────────────
InvoiceItems (عند الحجز):
  Item 1: description="Service A", unit_price=42.02, tax_amount=7.98, total_amount=50.00
  Item 2: description="Service B", unit_price=25.21, tax_amount=4.79, total_amount=30.00

─────────────────────────────────────────────────────
عند الدفع (Finalization):
  invoice_number = "INV-2026-000001"
  status = 2 (PAID)
  invoice_data = {
    tse_data: {...},
    finalized_at: "2026-05-29T...",
    payment_type: "2",
    amount_paid: 80.00,
    finalized_by: "Admin Name"
  }
  
  Payment record:
    amount = 80.00
    subtotal = 67.23 (reverse calculated)
    tax_amount = 12.77
    status = PAID_ONSTIE_CASH (2)
    type = 'full'
    paymentable → Invoice
    payment_metadata = {
      invoice_number: "INV-2026-000001",
      tse_transaction_number: null,  ← حتى يُطبَّق TSE
      ...
    }
```

### مسار بيانات Fiskaly داخل invoice_data JSON

```json
{
  "fiskaly_transaction_id":     "uuid-v4",
  "fiskaly_transaction_number": 42,
  "fiskaly_signature": {
    "value":      "base64-encoded-signature...",
    "algorithm":  "ecdsa-plain-SHA256",
    "counter":    123,
    "public_key": "base64-public-key..."
  },
  "fiskaly_qr_code":       "base64-qr-data-for-receipt",
  "fiskaly_tss_serial":    "d0a4be4774b2d78...",
  "fiskaly_client_serial": "SALON-POS-001",
  "fiskaly_time_start":    1748476800,
  "fiskaly_time_end":      1748476801,
  "fiskaly_status":        "signed",
  
  "tse_data": {
    "tse_enabled":           false,
    "tse_provider":          "fiskaly",
    "transaction_number":    null,
    "certified_timestamp":   "2026-05-29T...",
    "signature_data":        null,
    "tse_serial_number":     null,
    "signature_algorithm":   "ecdsa-plain-SHA256",
    ...
  },
  
  "finalized_at":         "2026-05-29T...",
  "finalized_by":         "Admin Name",
  "payment_type":         "2",
  "amount_paid":          80.00,
  "finalization_method":  "api",
  
  "aggregated":           true,
  "appointment_ids":      [1, 2],
  "last_rebuilt_at":      "2026-05-29T..."
}
```

---

<a name="6"></a>
## 6. تحليل الموديلات — Models

### `Invoice` — الفاتورة الرئيسية

**الملف:** [app/Models/Invoice.php](app/Models/Invoice.php)

**الجدول:** `invoices`

| الحقل | النوع | الغرض |
|-------|-------|-------|
| `appointment_id` | FK→appointments (nullable) | الحجز الأصلي |
| `customer_id` | FK→users (nullable) | العميل (null للزوار) |
| `invoice_number` | string (nullable) | `INV-2026-000001` — null للـ DRAFT |
| `subtotal` | decimal(8,2) | المبلغ الصافي (قبل الضريبة) |
| `tax_amount` | decimal(8,2) | مبلغ الضريبة |
| `tax_rate` | decimal(5,2) | نسبة الضريبة (19.00) |
| `total_amount` | decimal(8,2) | الإجمالي الكامل (شامل الضريبة) |
| `status` | tinyint | DRAFT(0), PAID(2), إلخ — مُحوَّل إلى InvoiceStatus enum |
| `invoice_data` | json | بيانات الدفع + TSE + Fiskaly (مشروح في القسم 5) |
| `segnture` | string (nullable) | التوقيع الرقمي من TSE — **تنبيه: خطأ إملائي في الاسم `segnture` بدل `signature`** |
| `signature_missing_reason` | string (nullable) | سبب غياب التوقيع |
| `print_count` | int | عدد مرات الطباعة |
| `first_printed_at` | timestamp | وقت أول طباعة |
| `last_printed_at` | timestamp | وقت آخر طباعة |

**العلاقات:**

```
invoice
  ├── belongsTo Appointment
  ├── belongsTo User (customer)
  ├── hasMany InvoiceItem
  ├── hasOne Payment  (أحدث دفعة)
  ├── morphMany Payment  (كل الدفعات)
  ├── hasMany PrintLog
  └── hasOne PrintLog (lastPrintLog → latestOfMany)
```

**الدوال المهمة:**

```php
// التحقق إذا طُبعت
$invoice->isPrinted(): bool

// ما هو الرقم الذي سيظهر في الطباعة القادمة
$invoice->getNextPrintNumber(): int   // print_count + 1

// تسمية النسخة: '' / '(COPY)' / '(COPY 2)'
$invoice->getCopyLabel(): string

// زيادة عداد الطباعة + تحديث التوقيتات
$invoice->incrementPrintCount(): void

// جلب القالب الافتراضي
$invoice->getTemplateOrDefault(): InvoiceTemplate

// جلب كل الحجوزات المرتبطة (parent + children للفواتير المجمَّعة)
$invoice->getCoveredAppointments(): Collection

// هل هي فاتورة مجمَّعة (تغطي أكثر من حجز)؟
$invoice->isAggregated(): bool
```

**Boot Logic:** لا يوجد — جميع العمليات في Service Layer.

---

### `InvoiceItem` — بنود الفاتورة

**الملف:** [app/Models/InvoiceItem.php](app/Models/InvoiceItem.php)

**الجدول:** `invoice_items`

| الحقل | النوع | الغرض |
|-------|-------|-------|
| `invoice_id` | FK→invoices | الفاتورة الأم |
| `description` | text | اسم الخدمة عند وقت الفاتورة |
| `quantity` | smallint | الكمية (عادةً 1) |
| `unit_price` | decimal | سعر الوحدة الصافي (بعد استخراج الضريبة) |
| `tax_rate` | decimal | نسبة الضريبة لهذا البند |
| `tax_amount` | decimal | مبلغ الضريبة لهذا البند |
| `total_amount` | decimal | الإجمالي الكامل للبند (gross) |
| `itemable_id/type` | morphs | ارتباط polymorphic بـ Service |

**الـ Observer (Boot) — تحذير مهم:**

```php
protected static function boot()
{
    // عند الحفظ: تُعيد حساب total_amount من unit_price و tax_rate
    static::saving(fn($item) => $item->calculateTotal());
    
    // عند الحفظ الناجح: تُعيد حساب إجماليات الفاتورة الأم
    static::saved(fn($item) => $item->invoice->calculateTotals());
    
    // عند الحذف: نفس الشيء
    static::deleted(fn($item) => $item->invoice->calculateTotals());
}
```

**⚠️ مشكلة بنيوية:** هذا Observer يُطلق `invoice->calculateTotals()` عند كل `save()`. عند إنشاء 5 بنود = 5 حسابات + 5 استعلامات `UPDATE invoices`. الحل الصحيح الموجود هو `InvoiceItem::withoutEvents()` في InvoiceService.

**`calculateTotal()` — حسابات البند (خطأ!)**

```php
// هذا الكود يستخدم float arithmetic وليس bcmath!
public function calculateTotal(): void
{
    $subtotal = $this->quantity * $this->unit_price;
    $this->tax_amount = $subtotal * ($this->tax_rate / 100); // ← Float multiplication!
    $this->total_amount = $subtotal + $this->tax_amount;
}
```

**ملاحظة:** هذه الدالة تُشكّل خطراً محاسبياً لأنها تُضيف الضريبة بدلاً من استخراجها، وتستخدم float. لكن في الواقع لا تُستدعى مباشرة عند إنشاء البنود لأن `withoutEvents` يُعطّل observer.

---

### `InvoiceTemplate` — قالب الفاتورة

**الملف:** [app/Models/InvoiceTemplate.php](app/Models/InvoiceTemplate.php)

| الحقل | النوع | الغرض |
|-------|-------|-------|
| `name` | string | اسم القالب |
| `is_default` | boolean | القالب الافتراضي (واحد فقط) |
| `is_active` | boolean | نشط/غير نشط |
| `paper_size` | string | A4, 80mm, إلخ |
| `paper_width` | int | العرض بالملم |
| `font_family` | string | خط الطباعة |
| `font_size` | int | حجم الخط |
| `global_styles` | json | الألوان والتباعد والحدود |
| `company_info` | json | اسم الشركة، العنوان، رقم الضريبة، شعار |
| `metadata` | json | بيانات إضافية |
| `static_body_html` | text | HTML ثابت اختياري في جسم الفاتورة |

**Boot Logic:**
- عند الإنشاء: تملأ `company_info` من SalonSettings تلقائياً
- عند الحفظ: إذا تم `is_default=true` → تُصفِّر `is_default` عن كل الآخرين

**SoftDeletes:** موجود — يُمكن التعافي من الحذف.

---

### `TemplateLine` — سطر في القالب

**الملف:** [app/Models/TemplateLine.php](app/Models/TemplateLine.php)

| الحقل | النوع | الغرض |
|-------|-------|-------|
| `template_id` | FK | القالب الأب |
| `section` | string | header / body / footer |
| `type` | string | text, tse_info, items_table... (من config/invoice-line-types.php) |
| `order` | int | الترتيب داخل القسم (0-based) |
| `is_enabled` | boolean | إظهار/إخفاء السطر |
| `properties` | json | إعدادات خاصة بنوع السطر |

**Boot Logic:**
- عند الإنشاء: يُحسب `order` تلقائياً (max + 1)
- يُعبِّئ `properties` من الإعدادات الافتراضية
- عند الحذف: يُعيد ترتيب الأسطر المتبقية

**أنواع الأسطر (line types) — من config/invoice-line-types.php:**

| النوع | الوصف | blade_view |
|-------|-------|-----------|
| `text` | سطر نص (ثابت أو ديناميكي) | `invoices.line-types.text` |
| `separator` | خط فاصل | `invoices.line-types.separator` |
| `spacer` | مسافة فارغة | `invoices.line-types.spacer` |
| `image` | شعار أو صورة | `invoices.line-types.image` |
| `two_column` | تسمية: قيمة | `invoices.line-types.two-column` |
| `invoice_number` | رقم الفاتورة | `invoices.line-types.invoice-number` |
| `invoice_date` | تاريخ ووقت الفاتورة | `invoices.line-types.invoice-date` |
| `customer_info` | بيانات العميل | `invoices.line-types.customer-info` |
| `items_table` | جدول الخدمات | `invoices.line-types.items-table` |
| `totals_summary` | ملخص المبالغ والضريبة | `invoices.line-types.totals-summary` |
| `payment_info` | طريقة الدفع | `invoices.line-types.payment-info` |
| `qr_code` | رمز QR | `invoices.line-types.qr-code` |
| `barcode` | باركود | `invoices.line-types.barcode` |
| **`tse_info`** | **بيانات TSE/Fiskaly** | `invoices.line-types.tse-info` |
| `colors_used` | الألوان المستخدمة | `invoices.line-types.colors-used` |
| `thank_you_message` | رسالة شكر | `invoices.line-types.thank-you-message` |

---

### `Payment` — سجل الدفع

**الملف:** [app/Models/Payment.php](app/Models/Payment.php)

```
payments
  ├── payment_method_id → payment_methods (nullable — TODO: ربط)
  ├── payment_number    → PAY-20260529-XXXXXX
  ├── amount            → المبلغ المدفوع
  ├── subtotal          → الصافي (محسوب عكسياً)
  ├── tax_amount        → الضريبة
  ├── status            → PaymentStatus enum
  ├── type              → full / partial / deposit / refund
  ├── paymentable_id    ─┐ Polymorphic
  ├── paymentable_type  ─┘ → Invoice أو Appointment
  └── payment_metadata  → JSON: invoice_number, tse_transaction_number, collected_by
```

---

### `PrintLog` — سجل الطباعة

| الحقل | النوع | الغرض |
|-------|-------|-------|
| `invoice_id` | FK | الفاتورة المطبوعة |
| `template_id` | FK | القالب المستخدم |
| `printer_id` | FK | الطابعة المستخدمة |
| `user_id` | FK | من طبع |
| `print_number` | int | رقم هذه الطباعة (1=أصل، 2=نسخة...) |
| `copies` | int | عدد النسخ |
| `print_type` | string | original / copy |
| `status` | string | pending / printing / success / failed |
| `error_message` | text | في حالة الفشل |
| `started_at` | timestamp | |
| `completed_at` | timestamp | |
| `duration_ms` | int | مدة الطباعة بالميلي ثانية |
| `print_data` | json | IP، user agent، أسماء القالب والطابعة |

---

<a name="7"></a>
## 7. تحليل قاعدة البيانات

### مخطط العلاقات (ERD نصي)

```
appointments
    │ (1)
    └──→ (1) invoices
                │ (1)
                ├──→ (N) invoice_items
                │           └── [polymorphic → services]
                │
                ├──→ (N) payments [morphMany]
                │
                ├──→ (N) print_logs
                │
                └── invoice_data [JSON]
                       └── fiskaly_transaction_id → fiskaly_transactions.transaction_id

users (customer)
    └──→ (N) invoices

fiskaly_tss
    └──→ (N) fiskaly_clients
                └──→ (N) fiskaly_transactions
                              └──→ (1) invoices [invoice_id nullable]

invoice_templates
    └──→ (N) template_lines
    └──→ (N) print_logs
    └──→ (N) printer_settings [via print_logs.printer_id]
```

### الجداول وهيكلها

#### `invoices`
```sql
id                  BIGINT PK
appointment_id      BIGINT FK → appointments (nullable)
customer_id         BIGINT FK → users (nullable)
invoice_number      VARCHAR nullable  -- NULL حتى يتم الدفع
subtotal            DECIMAL(8,2)
tax_amount          DECIMAL(8,2) nullable
tax_rate            DECIMAL(5,2) nullable
total_amount        DECIMAL(8,2)
status              TINYINT       -- 0=DRAFT, 2=PAID, ...
notes               VARCHAR nullable
invoice_data        JSON nullable
segnture            VARCHAR nullable  -- خطأ إملائي: signature
signature_missing_reason VARCHAR nullable
print_count         INT DEFAULT 0
first_printed_at    TIMESTAMP nullable
last_printed_at     TIMESTAMP nullable
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**⚠️ ملاحظة مهمة:** لا يوجد index على `invoice_number` رغم استخدامه في البحث والعرض. يجب إضافته.

#### `invoice_items`
```sql
id                  BIGINT PK
invoice_id          BIGINT FK → invoices NOT NULL
description         TEXT nullable
quantity            SMALLINT
unit_price          DECIMAL(8,2)  -- الصافي (net)
tax_amount          DECIMAL(8,2) nullable
tax_rate            DECIMAL(5,2) nullable
total_amount        DECIMAL(8,2) -- الإجمالي (gross)
itemable_id         BIGINT
itemable_type       VARCHAR       -- App\Models\Service
created_at, updated_at
```

#### `fiskaly_tss`
```sql
id                  BIGINT PK
tss_id              VARCHAR UNIQUE  -- UUID من Fiskaly
puk                 TEXT nullable   -- ENCRYPTED (Laravel encrypt())
admin_pin           TEXT nullable
serial_number       VARCHAR nullable
certificate         TEXT nullable   -- X.509 certificate
state               VARCHAR DEFAULT 'INITIALIZED'  -- INITIALIZED/DISABLED/DEFECTIVE
description         VARCHAR nullable
metadata            JSON nullable   -- الاستجابة الكاملة من Fiskaly
created_at, updated_at, deleted_at (SoftDeletes)

INDEX: tss_id, state
```

#### `fiskaly_clients`
```sql
id                  BIGINT PK
client_id           VARCHAR UNIQUE  -- UUID
tss_id              VARCHAR FK → fiskaly_tss.tss_id (CASCADE)
serial_number       VARCHAR         -- 'SALON-POS-001'
metadata            JSON nullable
created_at, updated_at, deleted_at

INDEX: client_id, tss_id
```

#### `fiskaly_transactions`
```sql
id                   BIGINT PK
transaction_id       VARCHAR UNIQUE  -- UUID
tss_id               VARCHAR FK → fiskaly_tss.tss_id
client_id            VARCHAR nullable
invoice_id           BIGINT nullable FK → invoices (SET NULL on delete)
transaction_number   VARCHAR nullable  -- الرقم التسلسلي من Fiskaly
state                VARCHAR  -- ACTIVE / FINISHED / CANCELLED
time_start           TIMESTAMP nullable
time_end             TIMESTAMP nullable
signature            JSON nullable  -- { value, algorithm, counter, public_key }
qr_code_data         TEXT nullable
tss_serial_number    VARCHAR nullable
client_serial_number VARCHAR nullable
schema_data          JSON nullable   -- البيانات المُرسَلة لـ KassenSichV
metadata             JSON nullable
created_at, updated_at, deleted_at

INDEX: transaction_id, tss_id, client_id, invoice_id, state, time_start, time_end
```

#### `fiskaly_logs`
```sql
id               BIGINT PK
level            VARCHAR  -- info/warning/error
action           VARCHAR  -- authenticate/create_tss/start_transaction
message          TEXT
context          JSON nullable
tss_id           VARCHAR nullable
transaction_id   VARCHAR nullable
created_at       TIMESTAMP  -- no updated_at (log-only)

INDEX: level, action, tss_id, transaction_id, created_at
```

---

<a name="8"></a>
## 8. قواعد العمل — Business Logic

### قاعدة 1: الأسعار دائماً GROSS (شامل الضريبة)

```
✓ صحيح:   service.price = 50.00 EUR (يشمل 19% ضريبة)
✗ خطأ:     service.price = 42.02 EUR (صافي بدون ضريبة)

السبب: قوانين الأسعار الألمانية تلزم عرض الأسعار شاملة الضريبة للمستهلك.
الضريبة تُستخرج عكسياً: net = gross / (1 + rate/100)
```

### قاعدة 2: لا رقم فاتورة بدون دفع

```
DRAFT invoice → invoice_number = NULL ← لا رقم
         ↓
     العميل يدفع
         ↓
PAID invoice → invoice_number = "INV-2026-000001" ← رقم تسلسلي بدون فجوات
```

**لماذا؟** المحاسبة الألمانية تتطلب أرقاماً تسلسلية متتالية بدون فجوات. لو أنشأنا الرقم عند الحجز وألغى العميل، سيكون هناك فجوة. الحل: الرقم يُنشأ فقط عند الدفع الفعلي.

### قاعدة 3: قفل قاعدة البيانات عند توليد الرقم

```php
// DocumentNumberGenerator.php
DB::transaction(function() {
    $lastRecord = DB::table('invoices')
        ->whereYear('created_at', $year)
        ->orderByDesc('id')
        ->lockForUpdate()   // ← SELECT ... FOR UPDATE
        ->first();
    // ...
});
```

**لماذا `lockForUpdate`؟** في بيئة متعددة المستخدمين (concurrent requests)، بدون قفل قد يولّد طلبان متزامنان نفس الرقم.

### قاعدة 4: الفاتورة المجمَّعة (Aggregated Invoice)

```
حجز رئيسي (parent) + حجوزات مرتبطة (children)
       ↓
فاتورة واحدة فقط على الـ parent
       ↓
rebuildAggregatedInvoice() يجمع خدمات الكل في بنود واحدة
       ↓
عند الدفع: كل الحجوزات تتحول COMPLETED معاً
```

### قاعدة 5: لا يمكن إنشاء فاتورة لحجز child

```php
// InvoiceService.php
if ($appointment->is_child_booking) {
    throw new \InvalidArgumentException(
        'Cannot create an invoice for a child appointment.'
    );
}
```

### قاعدة 6: لا يمكن إعادة بناء فاتورة ليست DRAFT

```php
if ($invoice->status !== InvoiceStatus::DRAFT) {
    throw new \InvalidArgumentException(
        'Cannot rebuild a non-draft invoice (status=PAID).'
    );
}
```

### قاعدة 7: Offline Mode للـ TSE

عندما لا يمكن الوصول لـ Fiskaly:
- إذا `fiskaly.offline_mode.enabled = true` → الفاتورة تُعالَج بدون توقيع
- تُخزَّن `fiskaly_status = 'offline'` في `invoice_data`
- الإيصال يظهر: "Sicherungseinrichtung ausgefallen" (جهاز الأمان معطل)
- هذا **مسموح** قانونياً بشكل مؤقت مع الإبلاغ لاحقاً

### قاعدة 8: نسخ الطباعة (COPY)

```
أول طباعة → print_count = 1 → getCopyLabel() = ''  (لا label)
ثاني طباعة → print_count = 2 → getCopyLabel() = ' (COPY)'
ثالث طباعة → print_count = 3 → getCopyLabel() = ' (COPY 2)'
```

---

<a name="9"></a>
## 9. تحليل طبقة الخدمات — Services

### `InvoiceService`

**الملف:** [app/Services/InvoiceService.php](app/Services/InvoiceService.php)

#### `createDraftInvoice(Appointment $appointment): Invoice`

```
الغرض: إنشاء فاتورة مسودة عند الحجز

المدخلات: Appointment (يجب أن يكون parent أو standalone)
الإخراج: Invoice (status=DRAFT, invoice_number=null)

الخطوات:
1. جلب tax_rate من get_setting('tax_rate', 19)
2. Invoice::create() بالأرقام من الـ appointment
   - appointment_id, customer_id
   - subtotal, tax_amount, tax_rate, total_amount (من appointment)
   - status = DRAFT
   - notes = 'Invoice draft - awaiting payment'

الاستثناءات:
- لا تتحقق من صحة البيانات — تفترض صحتها من BookingService
```

#### `createInvoiceItems(Invoice $invoice, Appointment $appointment): void`

```
الغرض: بناء بنود الفاتورة من خدمات الحجز

المميز: يستخدم InvoiceItem::withoutEvents() ←
  لمنع التشغيل N مرة لـ observer عند كل save()
  (N = عدد الخدمات، كل خدمة تُحفظ بدون triggering calculateTotals())

لكل خدمة في appointment->services:
1. unit_price ← service.pivot.price (GROSS)
2. TaxCalculatorService::extractTax(unit_price, tax_rate)
   → { net, tax, gross }
3. InvoiceItem::create({
     unit_price = net,      ← الصافي
     tax_amount = tax,
     total_amount = gross,
     description = service_name (snapshot)
   })
```

#### `rebuildAggregatedInvoice(Appointment $parent): Invoice`

```
الغرض: إعادة بناء فاتورة الـ parent لتشمل خدمات كل الـ children

متى يُستدعى؟
- بعد إضافة خدمة/حجز مرتبط عبر AppointmentLinkingService
- قبل الـ finalization (دفاعياً)

الخطوات:
1. التحقق: parent ليس child_booking
2. جلب draft invoice (أو إنشاؤها)
3. التحقق: status = DRAFT
4. invoice->items()->delete() ← مسح البنود القديمة
5. linkedGroup() → جمع parent + كل children
   - ORDER BY COALESCE(parent_appointment_id, id) ASC ← parent أولاً
   - ORDER BY start_time ASC
6. لكل appointment → لكل service → InvoiceItem::create()
   - description = "{service_name} — by {provider_name}"
7. bcmath totals: subtotal/tax/total
8. Rounding reconciliation: net + tax == total ؟
9. invoice->update(subtotals + audit metadata)
```

#### `finalizeDraftInvoice(Invoice, paymentType, amountPaid, notes): Invoice`

```
⚠️ تحذير: هذه الدالة موجودة في كلا:
  - InvoiceService.php (نسخة مبسطة)
  - InvoiceFinalizationService.php (النسخة الكاملة مع TSE + Payment)

يجب استخدام InvoiceFinalizationService دائماً!

الفرق:
  InvoiceService::finalizeDraftInvoice → لا تُنشئ Payment record
  InvoiceFinalizationService::finalizeDraftInvoice → تُنشئ Payment record + TSE
```

---

### `InvoiceFinalizationService`

**الملف:** [app/Services/InvoiceFinalizationService.php](app/Services/InvoiceFinalizationService.php)

#### `finalizeDraftInvoice(Invoice, paymentType, amountPaid, notes, applyTse): Invoice`

```
الخطوة 1: التحقق
  - invoice->status === DRAFT
  - invoice->appointment موجود

الخطوة 2: DB Transaction يبدأ

الخطوة 3: TSE Signature
  if $applyTse:
    tseData = applyTSESignature($invoice, $paymentType, $amountPaid)
    ← حالياً: إرجاع placeholder بـ tse_enabled=false
  else:
    tseData = createPlaceholderTSE()

الخطوة 4: توليد رقم الفاتورة
  invoiceNumber = Invoice::generateInvoiceNumber()
  ← DocumentNumberGenerator::generate('invoices','invoice_number','INV')

الخطوة 5: تحديث الفاتورة
  invoice->update([
    invoice_number = invoiceNumber,
    status = PAID,
    invoice_data = merge(existing + tse_data + finalized_at + ...)
  ])

الخطوة 6: تحديث الحجوزات
  updateAppointmentStatus($appointment, $paymentType, PAID)
  → كل linkedGroup → status=COMPLETED, payment_status=from($paymentType)

الخطوة 7: إنشاء Payment Record
  createPaymentRecord($invoice, $paymentType, $amountPaid, $tseData)
  → Payment::create([paymentable=Invoice, type=full/partial, ...])

الخطوة 8: DB Commit

الإرجاع: invoice->fresh(['appointment','customer','items','payments'])
```

---

### `TaxCalculatorService`

**الملف:** [app/Services/TaxCalculatorService.php](app/Services/TaxCalculatorService.php)

**المبدأ الأساسي:** كل الحسابات بـ `bcmath` — صفر float arithmetic.

#### `extractTax(gross, taxRate, precision=2): array`

```
المدخل: gross = "50.00", taxRate = "19"
العملية:
  factor = 1 + (19 / 100) = 1.19  [bcmath]
  net_high = 50.00 / 1.19 = 42.0168...  [bcmath, precision=2]
  tax_high = 50.00 - 42.0168... = 7.9832...

  net = bcRound(net_high, 2) = "42.02"
  tax = bcRound(tax_high, 2) = "7.98"
  gross = bcRound("50.00", 2) = "50.00"

Reconciliation:
  sum = 42.02 + 7.98 = 50.00
  diff = 50.00 - 50.00 = 0.00 ✓ (لا تعديل)

المخرج: { net: "42.02", tax: "7.98", gross: "50.00" }
```

#### ضمان المطابقة الرياضية

```
اشتراط: net + tax = gross (دائماً، بدون استثناء)

الحالة الإشكالية:
  gross = 1.00, taxRate = 19%
  net_theoretical = 1.00/1.19 = 0.840336...
  net_rounded = 0.84
  tax_rounded = 0.16
  check: 0.84 + 0.16 = 1.00 ✓ (صدفة في هذه الحالة)

الحالة الإشكالية الحقيقية:
  gross = 1.01, taxRate = 19%
  net = 0.84, tax = 0.16 → sum = 1.00 ≠ 1.01 → diff = 0.01
  لأن net >= tax: net = net + 0.01 = 0.85
  تحقق: 0.85 + 0.16 = 1.01 ✓
```

---

<a name="10"></a>
## 10. نظام القوالب — Invoice Templates

### التسلسل الهرمي

```
InvoiceTemplate (الأب)
  └── TemplateLine (الأسطر)
        ├── section: header
        │     └── [lines ordered by 'order']
        ├── section: body
        │     └── [lines ordered by 'order']
        └── section: footer
              └── [lines ordered by 'order']
```

### كيف يُبنى HTML الفاتورة

```
PrintService::buildPrintHtml()
    └── TemplateBuilderService::build($invoice, $template)
            │
            ├── loadInvoiceRelationships()
            │     ← customer, items.itemable, payment, appointment.customer
            │        appointment.colorRecords.color
            │
            ├── DynamicFieldResolver = new DynamicFieldResolver($invoice, $template)
            │
            ├── generateStyles() → CSS مبني من template.global_styles
            │
            └── View::make('invoices.template-builder', {...})->render()
                    │
                    └── لكل Line في header/body/footer:
                          └── builder->renderLine($line)
                                └── View::make($line->getBladeView(), {...})->render()
                                      ← invoices.line-types.text
                                      ← invoices.line-types.items-table
                                      ← invoices.line-types.tse-info
                                      ← ...
```

### DynamicFieldResolver — ربط الحقول الديناميكية

```php
// القيم المتاحة في القوالب (namespace → resolver)
company.*     → template->company_info JSON
invoice.*     → $invoice attributes + methods
customer.*    → $invoice->customer أو $invoice->appointment->customer_*
payment.*     → $invoice->payment
fiskaly.*     → $invoice->invoice_data['fiskaly_*']
appointment.* → $invoice->appointment
system.*      → now() بصيغ مختلفة
employee.*    → $invoice->appointment->employee

// مثال: fiskaly.tss_serial
resolveFiskalyField('fiskaly.tss_serial')
  → $fiskalyData['fiskaly_tss_serial'] ?? ''
  ← من invoice_data JSON
```

---

<a name="11"></a>
## 11. تكامل Fiskaly TSE الكامل

### ما هو Fiskaly؟

Fiskaly هو مزوّد خدمة Cloud TSE (Technical Security Environment) ألماني. يتيح توقيع معاملات نقاط البيع رقمياً بدون حاجة لجهاز TSE مادي (مثل Epson أو Swissbit). يستخدم ECDSA للتوقيع الرقمي.

**API:** `https://kassensichv.fiskaly.com/api/v2`

### هيكل Fiskaly

```
Organization (تُنشأ من Dashboard)
    └── TSS - Technical Security System (1 per Salon)
              └── Client - Cash Register (1+ per TSS)
                        └── Transaction (1 per Invoice/Sale)
                                   └── Signature + QR Code + Serial
```

### دورة حياة TSS (الإعداد لمرة واحدة)

```
1. إنشاء TSS
   PUT /tss/{uuid}
   Body: { description: "Main TSS for Salon" }
   Response: { tss_id, puk, state: "UNINITIALIZED", serial_number, certificate }
   ⚠️ PUK يُعرض مرة واحدة فقط — يجب حفظه!

2. تهيئة TSS
   PATCH /tss/{tss_id}
   Body: { state: "INITIALIZED" }
   → TSS أصبح جاهزاً

3. مصادقة Admin
   POST /tss/{tss_id}/admin/auth
   Body: { admin_pin: "" }  ← PIN افتراضي = فارغ للـ TSS الجديد

4. إنشاء Client
   PUT /tss/{tss_id}/client/{client_uuid}
   Body: { serial_number: "SALON-POS-001" }
   Response: { client_id, serial_number }
```

### دورة حياة المعاملة (لكل فاتورة)

```
1. بدء المعاملة
   PUT /tss/{tss_id}/tx/{tx_uuid}?tx_revision=1
   Body: { state: "ACTIVE", client_id: "{client_id}" }
   Response: { number, state: "ACTIVE", time_start }

2. إنهاء المعاملة والحصول على التوقيع
   PUT /tss/{tss_id}/tx/{tx_uuid}?tx_revision=2
   Body: {
     state: "FINISHED",
     client_id: "{client_id}",
     schema: {
       standard_v1: {
         receipt: {
           receipt_type: "RECEIPT",
           amounts_per_vat_rate: [
             { vat_rate: "19", amount: "80.00" }
           ],
           amounts_per_payment_type: [
             { payment_type: "CASH", amount: "80.00", currency_code: "EUR" }
           ]
         }
       }
     }
   }
   Response: {
     number: 42,
     time_start: 1748476800,
     time_end: 1748476801,
     signature: { value, algorithm, counter, public_key },
     qr_code_data: "base64...",
     tss_serial_number: "d0a4be...",
     client_serial_number: "SALON-POS-001"
   }
```

### KassenSichV Schema — ما يُرسَل لـ Fiskaly

```php
// buildTransactionSchema() في TransactionService.php
[
    'standard_v1' => [
        'receipt' => [
            'receipt_type' => 'RECEIPT',
            'amounts_per_vat_rate' => [
                ['vat_rate' => '19', 'amount' => '80.00']
                // يجمع gross amounts حسب نسبة الضريبة
            ],
            'amounts_per_payment_type' => [
                ['payment_type' => 'CASH', 'amount' => '80.00', 'currency_code' => 'EUR']
                // CASH أو NON_CASH
            ]
        ]
    ]
]
```

### الإعداد عبر Artisan Commands

```bash
# الإعداد الأول من الصفر
php artisan fiskaly:setup

# الاختبار
php artisan fiskaly:test

# إنشاء TSS يدوياً
php artisan fiskaly:init-tss

# إنشاء Client
php artisan fiskaly:create-client

# مصادقة Admin
php artisan fiskaly:admin-auth --tss-id={uuid} --pin=""

# فك حجب PIN مع PUK
php artisan fiskaly:unblock-pin --tss-id={uuid} --puk={puk} --new-pin={pin}
```

### إعدادات `.env` المطلوبة

```dotenv
FISKALY_API_KEY=your_api_key
FISKALY_API_SECRET=your_api_secret
FISKALY_ENVIRONMENT=test          # أو production
FISKALY_BASE_URL=https://kassensichv.fiskaly.com/api/v2
FISKALY_TSS_ID=uuid-of-tss       # بعد fiskaly:setup
FISKALY_CLIENT_ID=uuid-of-client # بعد fiskaly:setup
FISKALY_TSS_PUK=the-puk-value    # يحفظ تلقائياً — احتفظ بنسخة آمنة!
FISKALY_TSS_ADMIN_PIN=           # PIN الافتراضي فارغ
FISKALY_CLIENT_SERIAL=SALON-POS-001
FISKALY_LOGGING_ENABLED=true
FISKALY_LOG_CHANNEL=daily
FISKALY_LOG_LEVEL=info
```

### حالة التكامل الحالية

| المكون | الحالة | ملاحظة |
|--------|--------|---------|
| `FiskalyClient` | ✅ مكتمل | JWT + Cache + Retry |
| `TssService` | ✅ مكتمل | CRUD + Admin Auth + DB Storage |
| `ClientService` | ✅ مكتمل | Create/Update/DB Storage |
| `TransactionService` | ✅ مكتمل | Start + Finish + KassenSichV Schema |
| `FiskalyService::signInvoice()` | ✅ مكتمل | يعمل إذا كانت الإعدادات موجودة |
| `InvoiceFinalizationService::applyTSESignature()` | ⚠️ Placeholder | لا تستدعي FiskalyService بعد! |
| عرض TSE في الفاتورة | ✅ مكتمل | tse-info line type |
| تصدير DSFinV-K | ✅ API موجود | `TssService::export()` |

**الخطوة المفقودة الحرجة:**

```php
// InvoiceFinalizationService.php السطر 39
// هذا الكود يُرجع placeholder، لا يتصل بـ Fiskaly!
private function applyTSESignature(Invoice $invoice, ...) {
    return [
        'tse_enabled' => false,  // ← دائماً false!
        ...
    ];
}

// الكود الصحيح يجب أن يكون:
private function applyTSESignature(Invoice $invoice, ...) {
    $fiskalyService = app(FiskalyService::class);
    return $fiskalyService->signInvoice($invoice);
}
```

---

<a name="12"></a>
## 12. نظام الطباعة — Print System

### تدفق الطباعة الكامل

```
Admin يفتح /invoice/{id}/print في المتصفح
       │
       ▼
PrintController::print(Request $request, Invoice $invoice)
       │
       ├── جلب printer (من ?printer_id أو getDefault())
       ├── جلب template (من ?template_id أو invoice->getTemplateOrDefault())
       ├── copies = request->get('copies', 1)
       │
       └── PrintService::print($invoice, $printer, $template, $copies)
              │
              ├── [CHECK] printer موجود وفعّال؟
              ├── [CHECK] template موجود؟
              │
              ├── PrintLog::create() → status=pending
              ├── printLog->markAsStarted()
              │
              ├── printNumber = invoice->getNextPrintNumber()
              ├── copyLabel = invoice->getCopyLabel()
              │
              ├── buildPrintHtml($invoice, $template, $printer, $copyLabel)
              │       └── TemplateBuilderService::build($invoice, $template)
              │           + injectCopyLabel(html, "(COPY)")
              │           + addAutoPrintScript(html, printer)
              │               └── window.print() تلقائياً بعد 500ms
              │
              ├── invoice->incrementPrintCount()
              │       └── print_count++
              │           first_printed_at = now() (إذا أول مرة)
              │           last_printed_at = now()
              │
              ├── printLog->markAsSuccess()
              │       └── status=success, completed_at=now(), duration_ms
              │
              └── Return: { success, html, print_log_id, print_number, copy_label, printer }

Controller → response($result['html'])->header('Content-Type', 'text/html')
Browser يستقبل HTML + يفتح print dialog تلقائياً
```

### نقاط الـ API للطباعة

| المسار | الطريقة | الغرض |
|--------|---------|-------|
| `GET /invoice/{id}/print` | Web browser | طباعة مباشرة |
| `POST /api/invoice/{id}/print` | API JSON | طباعة عبر API |
| `GET /invoices/print-batch` | Web | طباعة دفعية |
| `POST /api/invoices/print-batch` | API | طباعة دفعية |
| `GET /api/invoice/{id}/print-url` | API | الحصول على URL الطباعة |
| `GET /api/print/statistics` | API | إحصائيات الطباعة |
| `GET /api/print/logs` | API | سجلات الطباعة |
| `POST /api/printer/{id}/test` | API | اختبار الطابعة |

---

<a name="13"></a>
## 13. حساب الضرائب — Tax Calculation

### المبادئ الأساسية

```
1. المبالغ في DB → دائماً GROSS (شامل الضريبة)
2. الضريبة تُستخرج عكسياً، لا تُضاف
3. bcmath فقط — ممنوع استخدام PHP float للمال
4. net + tax = gross (مضمونة رياضياً)
```

### صيغة الاستخراج العكسي

```
الصيغة: net = gross / (1 + rate/100)
مثال:   net = 50.00 / (1 + 0.19) = 50.00 / 1.19 = 42.0168...
        tax = 50.00 - 42.0168... = 7.9832...
        rounded: net = 42.02, tax = 7.98
        تحقق: 42.02 + 7.98 = 50.00 ✓
```

### أماكن حساب الضرائب في الكود

| المكان | الطريقة | الملاحظة |
|--------|---------|---------|
| `BookingService::calculateTotals()` | bcmath manual | لكل خدمة، ثم مصالحة |
| `TaxCalculatorService::extractTax()` | bcmath مع bcRound | الأكثر دقة |
| `InvoiceFinalizationService::calculateReverseTax()` | bcmath | للـ Payment record |
| `InvoiceService::calculateReverseTax()` | float (!) | خطر — يُستخدم فقط في createInvoiceFromAppointment() |
| `InvoiceItem::calculateTotal()` | float × float | خطر — لكن observer مُعطَّل |
| `Invoice::calculateTotals()` | bcadd + bcmul | لكن بدقة 4 للضريبة |

---

<a name="14"></a>
## 14. التحقق والأمان

### نقاط الحماية

```
1. Web Routes → auth:sanctum middleware
   GET /invoice/{id}/print → مطلوب تسجيل دخول
   
2. Admin Routes → FilamentUser::canAccessPanel()
   → يتحقق من role = 'admin'
   
3. InvoiceFinalizationService → يتحقق من:
   - status === DRAFT (منع Double-finalization)
   - appointment موجود
   
4. InvoiceService::validateInvoiceCreation() → يتحقق من:
   - لا فاتورة PAID موجودة مسبقاً
   - الحجز يحتوي خدمات
   - الحجز ليس ملغياً
   
5. TransactionService → تشغيل فقط إذا:
   - FISKALY_TSS_ID + FISKALY_CLIENT_ID موجودان
   
6. FiskalyClient → JWT token مخزَّن في Cache
   - تجديد تلقائي عند انتهاء الصلاحية (401 → re-authenticate)
```

### المخاطر الأمنية الموجودة

| المخاطرة | الملف | الخطورة |
|---------|-------|---------|
| PUK مخزَّن في `.env` | `TssService.php:83` | عالي — يجب vault/encrypted storage |
| Admin PIN مخزَّن بدون تشفير في `.env` | `config/fiskaly.php` | عالي |
| `updateEnvFile()` يكتب في `.env` مباشرة | `TssService.php`, `ClientService.php` | متوسط — race condition محتمل |
| لا authorization checks في PrintController | `PrintController.php` | متوسط — أي مستخدم يمكنه طباعة |
| `TssService::storeTssData` يحفظ full API response في metadata | `TssService.php:95` | منخفض — معلومات زائدة |

---

<a name="15"></a>
## 15. معالجة الأخطاء

### `FiskalyException`

```php
class FiskalyException extends Exception
{
    // يُرجع JSON response للـ API
    public function render($request): JsonResponse

    // يُسجِّل في Log::error()
    public function report(): void
}
```

### استراتيجية إعادة المحاولة في FiskalyClient

```
طلب HTTP فاشل
    ├── 401 Unauthorized → re-authenticate (مرة واحدة)
    ├── 500+ Server Error → retry مع delay (max 3 مرات)
    ├── ConnectionException → retry مع delay (max 3 مرات)
    └── Offline Mode (fallback)
```

### معالجة Offline Mode

```
Fiskaly غير متاح
    ↓
offline_mode.enabled = true ؟
    ├── YES → processOfflineInvoice()
    │         → invoice_data[fiskaly_status = 'offline']
    │         → يُكمل الفاتورة بدون توقيع
    │         → الإيصال يظهر "Sicherungseinrichtung ausgefallen"
    └── NO  → throw FiskalyException → 500 Error
```

### معالجة أخطاء الطباعة

```
PrintService::print() يلتقط كل Exception
    ├── printLog->markAsFailed($error)
    └── return ['success' => false, 'error' => $error->getMessage()]
```

### معالجة Observer Cascades

```
InvoiceItem::saved → invoice->calculateTotals()
    ← لو حدث خطأ هنا، يتشكل nested exception
    ← الحماية: withoutEvents() عند الإنشاء الجماعي
```

---

<a name="16"></a>
## 16. تحليل الأداء

### المشاكل الحالية وحلولها

#### مشكلة 1: N+1 في TemplateBuilderService

```php
// المشكلة: renderLine() يُحمَّل لكل سطر
foreach ($template->lines as $line) {
    $this->renderLine($line); // ← potentially queries inside
}

// الحل الموجود جزئياً:
$this->invoice->load(['customer', 'items.itemable', 'payment', ...])
// لكن ليس على جميع الـ relations المحتملة
```

#### مشكلة 2: Observer يُشغِّل N حسابات عند الإنشاء الجماعي

```php
// الحل الموجود في الكود (صحيح):
InvoiceItem::withoutEvents(function() {
    // إنشاء جميع البنود هنا
});
// ثم invoice->update() مرة واحدة يدوياً
```

#### مشكلة 3: Fiskaly JWT Token — Cache Key ثابت

```php
// الحالي في FiskalyClient.php
$cachedToken = Cache::get('fiskaly_jwt_token');
// لو استخدمت أكثر من organization/TSS → conflict!
// الحل: Cache::get('fiskaly_jwt_token_' . $tssId)
```

#### مشكلة 4: DocumentNumberGenerator يحتوي ORDER BY id بدلاً من invoice_number

```php
// الحالي — مشكلة محتملة:
->orderByDesc('id')  // يفترض أن آخر id = آخر invoice_number

// الأصح:
->orderByDesc($column)  // ORDER BY invoice_number DESC
```

#### مشكلة 5: Invoice::getCoveredAppointments() تستعلم في كل مرة

```php
// يُستدعى في TemplateBuilderService + PrintController
// بدون caching → استعلام DB في كل render
```

### توصيات الأداء

1. **أضف index على `invoices.invoice_number`** — يُستخدم في البحث والعرض
2. **أضف eager loading** لـ `template->lines` + `invoice->items.itemable` في query واحد
3. **استخدم Database Queue** لعمليات Fiskaly بدلاً من synchronous HTTP
4. **Cache QR Code** المُولَّد لنفس الفاتورة (لا يتغير بعد التوقيع)

---

<a name="17"></a>
## 17. مخططات التسلسل — Sequence Diagrams

### السيناريو الكامل: من الحجز إلى الفاتورة المطبوعة

```
Customer    BookingController   BookingService   InvoiceService    Fiskaly
    │               │                 │                │              │
    │── POST /api/bookings ──────────→│                │              │
    │               │── createBooking()─────────────→  │              │
    │               │                 │── calculateTotals()           │
    │               │                 │   (bcmath, gross→net+tax)     │
    │               │                 │                │              │
    │               │                 │── DB::beginTransaction()      │
    │               │                 │── Appointment::create()       │
    │               │                 │── AppointmentService::create()│
    │               │                 │── createDtaftInvoiceFromAppointment()─→
    │               │                 │                │              │
    │               │                 │   (Draft, invoice_number=null)│
    │               │                 │── createInvoiceItems()────────→
    │               │                 │   (withoutEvents, extractTax) │
    │               │                 │── DB::commit()               │
    │               │←────────────────│                │              │
    │←─ 201 Appointment+Invoice ──────│                │              │
    │               │                 │                │              │
   ...              │                 │                │              │
    │               │                 │                │              │
Admin           FilamentAdmin    InvoiceFinalizationService    Fiskaly API
    │               │                 │                              │
    │── Click Pay ─→│                 │                              │
    │               │── finalizeDraftInvoice()─────────────────────→ │
    │               │                 │── applyTSESignature()        │
    │               │                 │   (placeholder حالياً)       │
    │               │                 │── generateInvoiceNumber()    │
    │               │                 │   → DB::lockForUpdate()      │
    │               │                 │   → INV-2026-000001          │
    │               │                 │── invoice->update(PAID)      │
    │               │                 │── updateLinkedAppointments() │
    │               │                 │── createPaymentRecord()      │
    │               │                 │── DB::commit()              │
    │←── Invoice PAID ───────────────│                              │
    │               │                 │                              │
    │── Click Print →│                 │                              │
    │               │── PrintService::print()                        │
    │               │   TemplateBuilderService::build()              │
    │               │   DynamicFieldResolver (fiskaly.tss_serial...) │
    │               │   invoice->incrementPrintCount()               │
    │               │   PrintLog::create(success)                    │
    │← HTML + AutoPrint Script ──────│                              │
    │── window.print() ────────────→ [Printer Dialog]              │
```

### سيناريو Fiskaly التوقيع الكامل

```
Admin               FiskalyService      FiskalyClient      Fiskaly API
  │                      │                   │                   │
  │── signInvoice() ────→│                   │                   │
  │                      │── isAvailable() ──│── GET /health ────→│
  │                      │                   │←─ 200 ────────────│
  │                      │── TransactionService::createFromInvoice()
  │                      │                   │                   │
  │                      │              start()                  │
  │                      │                   │── PUT /tss/{id}/tx/{uuid}?tx_revision=1 →│
  │                      │                   │                   │
  │                      │                   │← { number, time_start, state:ACTIVE } ─│
  │                      │              finish()                 │
  │                      │                   │── PUT /tss/{id}/tx/{uuid}?tx_revision=2 →│
  │                      │                   │   { state:FINISHED, schema:{...} }      │
  │                      │                   │                   │
  │                      │                   │← { signature, qr_code_data, tss_serial } │
  │                      │── storeTransactionData()→ fiskaly_transactions             │
  │                      │── invoice->update(['segnture' => sig.value])              │
  │← { success, transaction } ────────────────────────────────────────               │
```

---

<a name="18"></a>
## 18. أوامر Artisan الخاصة بـ Fiskaly

### `php artisan fiskaly:setup`

```
الغرض: الإعداد الكامل من الصفر (مرة واحدة فقط)
الخطوات:
  1. التحقق من API credentials
  2. FiskalyService::initialize()
     ├── authenticate()
     ├── TssService::create() → PUK!
     ├── TssService::initialize()
     ├── TssService::authenticateAdmin()
     └── ClientService::createOrUpdate()
  3. حفظ TSS_ID + CLIENT_ID في .env

⚠️ يطبع PUK في الـ console — يجب حفظه فوراً!
```

### `php artisan fiskaly:test`

```
الغرض: اختبار الاتصال والإعدادات
يتحقق من:
  - API credentials
  - TSS state = INITIALIZED
  - Client accessible
  - إجراء test transaction
```

### `php artisan fiskaly:unblock-pin --tss-id={id} --puk={puk} --new-pin={pin}`

```
الغرض: فك حجب admin PIN بعد 3 محاولات فاشلة
يستدعي: TssService::unblockWithPuk(tssId, puk, newPin)
```

---

<a name="19"></a>
## 19. دليل المطور — Developer Guide

### كيف تُنشئ فاتورة جديدة برمجياً؟

```php
// 1. الحصول على الحجز
$appointment = Appointment::with(['services.pivot', 'customer'])->find($id);

// 2. التأكد من عدم وجود فاتورة PAID
$invoiceService = app(InvoiceService::class);
$invoiceService->validateInvoiceCreation($appointment); // throws on error

// 3. إنشاء Draft (إذا لم تكن موجودة)
if (!$appointment->invoice) {
    // يُستدعى عادةً من BookingService تلقائياً
}

// 4. إعادة بناء الفاتورة (إذا أُضيفت خدمات جديدة)
$invoiceService->rebuildAggregatedInvoice($appointment);

// 5. استكمال الفاتورة عند الدفع
$finalizationService = app(InvoiceFinalizationService::class);
$invoice = $finalizationService->finalizeDraftInvoice(
    $appointment->invoice,
    (string) PaymentStatus::PAID_ONSTIE_CASH->value, // '2'
    $amountPaid,
    $notes,
    $applyTse = true
);

// 6. توقيع رقمي منفصل (اختياري — إذا لم يُطبَّق في finalize)
$fiskalyService = app(FiskalyService::class);
$fiskalyService->signInvoice($invoice);
```

### كيف تُضيف نوع سطر جديداً للقالب؟

```
1. أضف تعريف النوع في config/invoice-line-types.php
   'my_custom_type' => [
       'label' => 'My Custom Line',
       'blade_view' => 'invoices.line-types.my-custom',
       'sections' => ['body'],
       'properties' => ['my_prop' => 'default']
   ]

2. أنشئ Blade view في resources/views/invoices/line-types/my-custom.blade.php
   @props: $line, $invoice, $template, $properties, $builder

3. الفاتورة ستعرضه تلقائياً عند إضافته للقالب
```

### كيف تُضيف حقل ديناميكي جديداً؟

```
1. أضفه في config/invoice-dynamic-fields.php
   'myns.myfield' => [
       'label' => 'My Field',
       'category' => 'Custom',
       'example' => 'example value'
   ]

2. أضف resolver في DynamicFieldResolver.php
   str_starts_with($field, 'myns.') => $this->resolveMyNsField($field)

3. أضف الدالة:
   protected function resolveMyNsField(string $field): string { ... }
```

### كيف تختبر Fiskaly بدون تأثير على Production؟

```
1. استخدم FISKALY_ENVIRONMENT=test في .env
2. أنشئ organization منفصلة في dashboard.fiskaly.com
3. نفِّذ: php artisan fiskaly:setup --test
4. اختبر: php artisan fiskaly:test
5. جميع transactions في بيئة test لا تؤثر على الإنتاج
```

### كيف تتعامل مع فاتورة لمجموعة حجوزات مرتبطة؟

```php
// الحجز الرئيسي
$parent = Appointment::find($parentId);

// الفاتورة تعيش دائماً على الـ parent
// children لا تملك فواتير منفصلة

// إعادة بناء بعد إضافة child
$invoiceService->rebuildAggregatedInvoice($parent);
// ← يجمع خدمات parent + كل children في بنود واحدة

// عند الطباعة
$invoice->getCoveredAppointments()
// ← يُرجع parent + كل children مرتبين زمنياً
```

### أين تضع الكود الجديد؟

| نوع التغيير | المكان الصحيح |
|-------------|--------------|
| قاعدة عمل جديدة للفاتورة | `InvoiceService.php` أو `InvoiceFinalizationService.php` |
| حساب ضريبي جديد | `TaxCalculatorService.php` — استخدم bcmath دائماً |
| نوع سطر قالب جديد | `config/invoice-line-types.php` + Blade view |
| حقل ديناميكي جديد | `config/invoice-dynamic-fields.php` + `DynamicFieldResolver.php` |
| عملية Fiskaly جديدة | `FiskalyService.php` (facade) + الـ service المناسب |
| منطق TSS admin | `TssService.php` |
| منطق transaction | `TransactionService.php` |
| تغيير في الطباعة | `PrintService.php` + `TemplateBuilderService.php` |
| Artisan command لـ Fiskaly | `app/Console/Commands/Fiskaly*.php` |

---

<a name="20"></a>
## 20. الملخص الهندسي النهائي

هذا النظام يُنفِّذ دورة حياة فاتورة متكاملة لصالون حلاقة ألماني تحكمها ثلاثة متطلبات:

**1. الامتثال القانوني (KassenSichV):** كل فاتورة نقدية مدفوعة يجب أن تُوقَّع رقمياً بـ TSE. البنية الحالية جاهزة لهذا عبر `FiskalyService::signInvoice()` و `TransactionService::createFromInvoice()` — الوصلة المفقودة هي **استدعاء** هذه الدوال من `InvoiceFinalizationService::applyTSESignature()` (حالياً placeholder).

**2. الدقة المحاسبية (bcmath):** النظام يستخدم `TaxCalculatorService` مع `bcmath` لضمان `net + tax = gross` رياضياً بدون أخطاء تقريب float. يُطبَّق هذا في `BookingService` و `InvoiceService::rebuildAggregatedInvoice()`.

**3. القوالب المرنة:** نظام template قابل للتخصيص الكامل يدعم 16 نوع سطر مختلف، ديناميكي الحقول، يشمل بيانات TSE/Fiskaly كـ `tse_info` line type. القالب يُصدِّر HTML مباشراً مع script لطباعة تلقائية عبر المتصفح.

**الفجوة الوحيدة الحالية:** `applyTSESignature()` في `InvoiceFinalizationService` تُرجع placeholder ولا تتصل بـ `FiskalyService`. ربطهما يُكمل الامتثال الكامل لـ KassenSichV.

---

<a name="21"></a>
## 21. المشاكل والمخاطر التفصيلية

---

### المشكلة 1: `applyTSESignature()` لا تتصل بـ Fiskaly

**التصنيف:** حرج — يُبطل الامتثال القانوني  
**الملف:** [app/Services/InvoiceFinalizationService.php:114](app/Services/InvoiceFinalizationService.php#L114)

**كيف تتشكل:**
```php
// الكود الحالي يُرجع دائماً:
return [
    'tse_enabled' => false,   // ← دائماً false!
    'tse_provider' => 'fiskaly',
    'transaction_number' => null,
    'signature_data' => null,
    // ...
];
// FiskalyService::signInvoice() موجودة ومكتملة لكن لا تُستدعى!
```

**الخطر:** كل فاتورة تُطبَّق عليها `finalizeDraftInvoice()` ستكون بدون توقيع TSE. هذا يُخالف KassenSichV ويُعرِّض الصالون لغرامات ضريبية.

**الإصلاح:**
```php
// في InvoiceFinalizationService.php السطر 114
private function applyTSESignature(
    Invoice $invoice,
    string $paymentType,
    float $amountPaid
): array {
    // ربط بـ FiskalyService الحقيقي
    try {
        $fiskalyService = app(FiskalyService::class);
        $result = $fiskalyService->signInvoice($invoice);
        
        return [
            'tse_enabled' => true,
            'transaction_number' => $result['transaction']['number'] ?? null,
            'certified_timestamp' => now()->toISOString(),
            'signature_data' => $result['transaction']['signature'] ?? null,
            'tse_serial_number' => $result['transaction']['tss_serial_number'] ?? null,
        ];
    } catch (FiskalyException $e) {
        // Offline fallback
        if (config('fiskaly.offline_mode.enabled')) {
            return $this->createPlaceholderTSE();
        }
        throw $e;
    }
}
```

---

### المشكلة 2: `InvoiceItem::calculateTotal()` يستخدم Float وليس bcmath

**التصنيف:** عالي — خطر محاسبي  
**الملف:** [app/Models/InvoiceItem.php:78](app/Models/InvoiceItem.php#L78)

**كيف تتشكل:**
```php
// الكود الخاطئ:
$subtotal = $this->quantity * $this->unit_price;     // Float multiplication!
$this->tax_amount = $subtotal * ($this->tax_rate / 100); // يُضيف ضريبة بدلاً من استخراجها!
$this->total_amount = $subtotal + $this->tax_amount;

// المشكلة المزدوجة:
// 1. يُضيف الضريبة (forward) بدلاً من استخراجها (reverse) — خطأ مفاهيمي
// 2. يستخدم PHP float multiplication
```

**مثال على الخطأ:**
```
unit_price = 42.02 (net), tax_rate = 19%
calculateTotal() يحسب:
  subtotal = 42.02 (quantity=1)
  tax_amount = 42.02 * 0.19 = 7.9838  ← float error!
  total = 42.02 + 7.9838 = 49.9938   ← ليس 50.00!
```

**لماذا لا يُسبِّب مشكلة حالياً؟** لأن `InvoiceItem::withoutEvents()` يُعطِّل هذا الـ Observer عند إنشاء البنود من InvoiceService. لكن لو أُنشئ أي InvoiceItem خارج `withoutEvents`، ستحدث المشكلة.

**الإصلاح:**
```php
// استخدم TaxCalculatorService بدلاً من float arithmetic
public function calculateTotal(): void
{
    $tax = app(TaxCalculatorService::class);
    $result = $tax->addTax(
        (string) ($this->quantity * $this->unit_price),
        (string) $this->tax_rate
    );
    
    $this->tax_amount = $result['tax'];
    $this->total_amount = $result['gross'];
}
```

---

### المشكلة 3: تكرار منطق `finalizeDraftInvoice` في خدمتين

**التصنيف:** متوسط — confusion والكود المكرر  
**الملفات:** [InvoiceService.php:554](app/Services/InvoiceService.php#L554) و [InvoiceFinalizationService.php:17](app/Services/InvoiceFinalizationService.php#L17)

**كيف تتشكل:**
```
InvoiceService::finalizeDraftInvoice() ← نسخة مبسطة: لا Payment record، لا TSE حقيقي
InvoiceFinalizationService::finalizeDraftInvoice() ← النسخة الكاملة: TSE + Payment + LinkedAppointments

مطور جديد يستدعي الأولى ظناً أنها الكاملة
→ الفاتورة تُكتمل بدون سجل دفع وبدون TSE
```

**الإصلاح:**
```php
// في InvoiceService، أعِد التوجيه للنسخة الكاملة أو احذف المكررة:
/**
 * @deprecated Use InvoiceFinalizationService::finalizeDraftInvoice() instead
 */
public function finalizeDraftInvoice(...): Invoice
{
    return app(InvoiceFinalizationService::class)->finalizeDraftInvoice(...);
}
```

---

### المشكلة 4: خطأ إملائي `segnture` بدل `signature` في DB

**التصنيف:** منخفض-متوسط — يُصعِّب الصيانة  
**الملف:** [database/migrations/2025_10_25_180109_create_invoices_table.php:32](database/migrations/2025_10_25_180109_create_invoices_table.php#L32)

**كيف تتشكل:**
```sql
$table->string('segnture')->nullable();  -- خطأ إملائي!
-- بدلاً من: signature
```

**الأثر:**
```php
// في الكود موجود في أماكن متعددة:
$invoice->update(['segnture' => $value]);      // FiskalyService.php:139
                                                // TransactionService.php:414
```

**الإصلاح:** يتطلب migration لإعادة تسمية العمود:
```php
Schema::table('invoices', function (Blueprint $table) {
    $table->renameColumn('segnture', 'signature');
});
// + تحديث كل مكان يستخدم 'segnture' في الكود
```

---

### المشكلة 5: `TssService::updateEnvFile()` غير آمن للتزامن

**التصنيف:** متوسط — Race Condition  
**الملف:** [app/Services/Fiskaly/TssService.php:269](app/Services/Fiskaly/TssService.php#L269)

**كيف تتشكل:**
```php
$content = file_get_contents($path);  // قراءة
// ... تعديل
file_put_contents($path, $content);   // كتابة

// إذا طلبان متزامنان:
// Request 1 يقرأ .env
// Request 2 يقرأ .env
// Request 1 يكتب
// Request 2 يكتب فوق Request 1 (يفقد تعديلاته)
```

**الإصلاح:**
```php
// استخدم lock أو atomic file write
$lockFile = storage_path('fiskaly.lock');
$lock = fopen($lockFile, 'w');
flock($lock, LOCK_EX);
// ... القراءة والكتابة
flock($lock, LOCK_UN);
fclose($lock);
```

---

### المشكلة 6: لا index على `invoices.invoice_number`

**التصنيف:** أداء — استعلامات بطيئة  
**الملف:** [database/migrations/2025_10_25_180109_create_invoices_table.php](database/migrations/2025_10_25_180109_create_invoices_table.php)

**كيف تتشكل:**
```
DocumentNumberGenerator يبحث عن آخر invoice_number بـ:
  ->whereYear('created_at', $year)
  ->orderByDesc('id')
  
والفاتورة تُعرض وتُبحث عبر invoice_number.
بدون index: Full Table Scan على 10,000+ سجل.
```

**الإصلاح:**
```php
// Migration جديد:
Schema::table('invoices', function (Blueprint $table) {
    $table->index('invoice_number');
    $table->index(['created_at', 'id']); // للـ DocumentNumberGenerator
});
```

---

### المشكلة 7: `buildVatRates()` في TransactionService يستخدم كائنات InvoiceItem مباشرة

**التصنيف:** متوسط — Tight Coupling + Type Error  
**الملف:** [app/Services/Fiskaly/TransactionService.php:262](app/Services/Fiskaly/TransactionService.php#L262)

**كيف تتشكل:**
```php
// في createFromInvoice(), يُبنى items array بشكل خاطئ:
$items = [
    'name' => 'Invoice #' . $invoice->invoice_number,
    'amount' => (float) $invoice->total_amount,    // ← item واحد فقط للكل!
    'vat_rate' => (float) $rate,
];

// ثم يُمرَّر لـ buildVatRates() التي تتوقع array من items:
protected function buildVatRates(array $items): array {
    foreach ($items as $item) {
        $rate = $item->tax_rate; // ← $items ليست array من Objects!
    }
}
```

**الخطر:** KassenSichV يتطلب تفصيل الضريبة per-item. الكود الحالي يُرسل مبلغاً إجمالياً واحداً بدلاً من تفصيل كل خدمة.

**الإصلاح:**
```php
// في createFromInvoice()، مرر invoice->items بشكل صحيح:
$data = [
    'receipt_type' => 'RECEIPT',
    'items' => $invoice->items,  // Collection من InvoiceItem objects
    'payments' => $paymentsArray,
    'client_id' => $clientId,
];
```

---

### المشكلة 8: `Invoice::calculateTotals()` يستخدم forward tax (يُضيف) بدل reverse

**التصنيف:** عالي — خطأ محاسبي  
**الملف:** [app/Models/Invoice.php:166](app/Models/Invoice.php#L166)

**كيف تتشكل:**
```php
public function calculateTotals(): void
{
    // يجمع subtotals من items (unit_price * quantity = صافي)
    $subtotal = items->sum('subtotal'); // صحيح: sum of net amounts
    
    // ثم يحسب الضريبة FORWARD:
    $taxAmount = bcmul($subtotal, (string)$this->tax_rate, 4);
    $taxAmount = bcdiv($taxAmount, '100', 2);
    // يُضيف: total = subtotal + tax
    
    // هذا صحيح إذا unit_price = NET
    // لكنه يُسبِّب double-counting إذا استُدعى بعد تعديل يدوي
}
```

**الخطر:** هذه الدالة تُستدعى من `InvoiceItem Observer` عند كل save. إذا كانت `unit_price` في Item هي الـ NET (كما يجب)، فالحساب صحيح. لكن أي تغيير في البيانات قد يُسبِّب عدم تطابق.

---

### المشكلة 9: PUK مُخزَّن في `.env` بنص عادي

**التصنيف:** أمني — عالي  
**الملف:** [app/Services/Fiskaly/TssService.php:83](app/Services/Fiskaly/TssService.php#L83) + `.env`

**كيف تتشكل:**
```
FISKALY_TSS_PUK=the-actual-puk-value  ← في .env بنص عادي
```

**الخطر:** 
- أي مطور يملك `.env` يملك PUK
- PUK يُستخدَم لإعادة تعيين admin PIN ولو سُرق → إمكانية حذف TSS أو تعطيله

**الإصلاح:**
```php
// استخدم Laravel Vault أو خدمة مثل AWS Secrets Manager
// أو على الأقل: تشفير PUK في .env
FISKALY_TSS_PUK=base64:encrypted_value

// في TssService:
$puk = decrypt(config('fiskaly.tss.puk'));
```

---

### المشكلة 10: لا Authorization في PrintController

**التصنيف:** أمني — متوسط  
**الملف:** [app/Http/Controllers/PrintController.php](app/Http/Controllers/PrintController.php)

**كيف تتشكل:**
```php
// PrintController لا يتحقق من هوية المستخدم أو صلاحياته
public function print(Request $request, Invoice $invoice) {
    // أي مستخدم مُسجَّل يمكنه طباعة أي فاتورة!
    // لا: $this->authorize('print', $invoice)
}
```

**الإصلاح:**
```php
// أضف Policy:
public function print(Request $request, Invoice $invoice) {
    $this->authorize('print', $invoice);
    // أو: abort_unless(auth()->user()->hasRole('admin'), 403);
    // ...
}
```

---

### المشكلة 11: `DocumentNumberGenerator` يبحث بـ `orderByDesc('id')` بدل `orderByDesc(invoice_number)`

**التصنيف:** منخفض — موثوقية  
**الملف:** [app/Services/DocumentNumberGenerator.php:20](app/Services/DocumentNumberGenerator.php#L20)

**كيف تتشكل:**
```php
$lastRecord = DB::table($table)
    ->whereYear('created_at', $year)
    ->orderByDesc('id')        // ← يفترض آخر id = آخر رقم
    ->lockForUpdate()
    ->first();

// المشكلة: لو حُذفت سجلات أو استُخدمت soft deletes أو جاء بالترتيب الخاطئ
// آخر id قد لا يكون آخر invoice_number

// المثال:
// id=10: INV-2026-000005
// id=12: INV-2026-000006 (حُذف id=11)
// id=13: ← generator سيعطي INV-2026-000007 ✓
// لكن:
// id=10: INV-2026-000005
// id=9:  INV-2026-000006 (أُعيد إدراج بـ id أصغر)
// id=13: ← generator يُعطي INV-2026-000007 لكن extracts number من id=13 → 0 → 1!
```

**الإصلاح:**
```php
// استخدم العمود الصحيح:
preg_match('/(\d+)$/', $lastRecord->$column, $matches);
$lastNumber = (int) ($matches[1] ?? 0);
// هذا في الواقع صحيح — يستخرج الرقم من invoice_number وليس من id
// المشكلة أن orderBy('id') قد لا يُعطي آخر invoice_number في حالات edge cases

// الأفضل:
->orderByRaw("CAST(REGEXP_SUBSTR($column, '[0-9]+$') AS UNSIGNED) DESC")
```

---

### جدول ملخص المخاطر

| # | المشكلة | الخطورة | يؤثر على | الإصلاح |
|---|---------|---------|---------|---------|
| 1 | `applyTSESignature()` placeholder | حرج | الامتثال القانوني | ربط FiskalyService |
| 2 | Float في `InvoiceItem::calculateTotal()` | عالي | دقة الحسابات | استخدام TaxCalculatorService |
| 3 | تكرار `finalizeDraftInvoice` | متوسط | الصيانة | Deprecate أحدهما |
| 4 | خطأ إملائي `segnture` | منخفض | الصيانة | Migration rename |
| 5 | Race condition في `updateEnvFile` | متوسط | البيانات | File lock |
| 6 | لا index على `invoice_number` | أداء | سرعة | Migration index |
| 7 | `buildVatRates()` receives wrong type | متوسط | KassenSichV schema | إصلاح payload |
| 8 | `calculateTotals()` forward tax | عالي | دقة الحسابات | مراجعة السياق |
| 9 | PUK في `.env` بنص عادي | أمني عالي | الأمان | تشفير/Vault |
| 10 | لا Authorization في PrintController | أمني | الصلاحيات | Gate/Policy |
| 11 | orderByDesc(id) في DocumentNumberGenerator | منخفض | موثوقية | orderByDesc(column) |

---

## ملحق: متغيرات البيئة المطلوبة لتشغيل النظام كاملاً

```dotenv
# ===== قاعدة البيانات =====
DATABASE_URL=postgresql://...

# ===== إعدادات الصالون =====
# (مخزَّنة في جدول salon_settings)
# tax_rate = 19
# company_name = "Your Salon"
# company_address = "Musterstraße 1, 10115 Berlin"
# company_tax_number = "DE123456789"

# ===== Fiskaly TSS =====
FISKALY_API_KEY=your_fiskaly_api_key
FISKALY_API_SECRET=your_fiskaly_api_secret
FISKALY_ENVIRONMENT=test          # test أو production
FISKALY_BASE_URL=https://kassensichv.fiskaly.com/api/v2

# بعد fiskaly:setup:
FISKALY_TSS_ID=uuid-of-your-tss
FISKALY_CLIENT_ID=uuid-of-your-client
FISKALY_TSS_PUK=the-puk-received-at-creation  # احفظ هذا بأمان!
FISKALY_TSS_ADMIN_PIN=your-admin-pin          # فارغ للافتراضي
FISKALY_CLIENT_SERIAL=SALON-POS-001

# Fiskaly Logging
FISKALY_LOGGING_ENABLED=true
FISKALY_LOG_CHANNEL=daily
FISKALY_LOG_LEVEL=info

# Receipt Info
FISKALY_BUSINESS_NAME="Beauty Salon Berlin"
FISKALY_BUSINESS_ADDRESS="Musterstraße 1, 10115 Berlin"
FISKALY_TAX_NUMBER="123/456/78901"
FISKALY_VAT_NUMBER="DE123456789"
```

---

*هذه الوثيقة تعكس حالة الكود كما في 2026-05-29. أي مطور أو AI Agent يقرأ هذه الوثيقة يجب أن يتحقق من التغييرات اللاحقة عبر `git log --since="2026-05-29" -- app/Services/Fiskaly/ app/Services/InvoiceFinalizationService.php`*
