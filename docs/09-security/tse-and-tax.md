# الامتثال الضريبي — TSE & Tax

> **الملفات:** `app/Services/Fiskaly/` (6 ملفات — معطلة)، `app/Services/TaxCalculatorService.php:1`، `config/fiskaly.php:1`، `Agent.md:945`

---

## 1. TSE — معطل عمداً

| البند | الحالة |
|-------|--------|
| `app/Services/Fiskaly/` (TssService, FiskalyClient, ...) | موجود لكن خارج مسار الدفع |
| `InvoiceFinalizationService` | يسجل `tse_enabled=false` في `invoice_data`، لا Fiskaly call |
| `Invoice.segnture` | null |
| `config/fiskaly.php` | `api_key` موجود لكن لا يُستخدم |
| إعادة التفعيل | مشروع تكامل منفصل + مراجعة قانونية — ليس `FISKALY_API_KEY` فقط |

> `Agent.md:945` يكرر: "Re-enabling TSE later requires a separate reviewed integration".

## 2. GROSS + bcmath

- كل الأسعار GROSS — الضريبة تُستخرج عكسيًا عبر `TaxCalculatorService`.
- `bcmath` لكل حسابات المال — لا `float`، لا `bcscale()`.
- `tax_rate` من `SalonSetting` (`get_setting('tax_rate','19')`) — ألمانيا 19%.

## 3. الفاتورة القانونية

- DRAFT (بلا رقم) ≠ مستند قانوني.
- PAID (مع `INV-...`) = مستند قانوني — يُطبع ويُحفظ.
- الترقيم بلا فجوات — `DocumentNumberGenerator` داخل transaction.

---

*التالي: [`10-operations/README.md`](../10-operations/README.md)*
