<div lang="ar" dir="rtl">

# إشعارات الإيميل عند الحجز — Booking Email Notifications

> **الهدف:** عند إنشاء حجز، يصل **إيميل تأكيد للزبون** بتفاصيل موعده، و**إيميل إشعار لإيميل الشركة** ببيانات الحجز.
> **ثنائي اللغة:** الشرح بالعربية، وأسماء الكود بالإنجليزية.

---

## 1. ملخّص القرارات المتّفق عليها

| القرار | الخيار المعتمد |
|--------|----------------|
| **إيميل الزبون — متى يُرسل؟** | فقط لحجوزات **الأونلاين (API)** — `booking_source = online` |
| **إيميل الشركة — متى يُرسل؟** | **لكل حجز** بغضّ النظر عن المصدر |
| **مصدر إيميل الشركة** | إعداد `company_email` ومع غيابه fallback إلى `MAIL_FROM_ADDRESS` |
| **آلية الإرسال** | عبر **الطابور (Queued / ShouldQueue)** — لا يُبطّئ الاستجابة ولا يكسر الحجز |
| **محتوى إيميل الزبون** | HTML أنيق بتفاصيل الحجز، بدون مرفقات |
| **اللغة** | إيميل الزبون بلغة الزبون (`user.locale`) مع fallback؛ إيميل الشركة باللغة الافتراضية |

---

## 2. أين تم الربط (نقطة الدخول الموحّدة)

كل مسارات الحجز (API / Web / StaffDashboard) تمرّ عبر دالة واحدة:
[`BookingService::createBooking()`](../app/Services/BookingService.php#L38).

لذلك تم الربط **مرة واحدة هناك**، **بعد** نجاح الـ `DB::transaction` (أي بعد الـ commit) — فلا تُرسَل إيميلات لحجز فشل وتراجع:

```php
// app/Services/BookingService.php
$appointment = DB::transaction(function () use (...) {
    // ... إنشاء Appointment + AppointmentServices + Draft Invoice
    return $appointment->load(['services', 'customer', 'provider', 'services_record']);
});

// 7. إرسال الإيميلات — بعد الـ commit، عبر الطابور، ومحميّة بالكامل
app(BookingMailService::class)->sendForNewBooking($appointment);

return $appointment;
```

---

## 3. الملفات المُضافة

### 3.1 الخدمة المنسّقة — `app/Services/BookingMailService.php`
العقل المدبّر. مسؤوليتها الوحيدة: تحديد المستلمين واللغة وإطلاق الـ Mailables.

- `sendForNewBooking(Appointment $appointment)` — نقطة الدخول. تحمّل العلاقات الناقصة، تقرأ `company_name` و`currency_symbol`، ثم تستدعي الإرسالين.
- `sendCustomerConfirmation()` — يُرسل **فقط** إذا كان `booking_source === BookingSource::ONLINE` **و** يوجد `customer_email`.
- `sendCompanyNotification()` — يُرسل دائماً؛ المستلم = `get_setting('company_email')` أو `config('mail.from.address')`.
- `customerLocale()` — لغة الزبون: `customer->locale` ثم اللغة الافتراضية.
- `companyLocale()` — اللغة الافتراضية: `get_setting('default_language')` ثم `config('app.locale')`.

> **الحماية:** كامل الجسم داخل `try/catch (\Throwable)` مع `Log::error`. أي خطأ في الإيميل **لا** يصعد إلى عملية الحجز.

### 3.2 الـ Mailables (كلاهما `implements ShouldQueue`)
- [`app/Mail/BookingConfirmationMail.php`](../app/Mail/BookingConfirmationMail.php) — إيميل الزبون. عنوان `booking_email.subject_customer`، عرض `emails.booking.confirmation`.
- [`app/Mail/BookingNotificationMail.php`](../app/Mail/BookingNotificationMail.php) — إيميل الشركة. عنوان `booking_email.subject_company`، عرض `emails.booking.notification`.

كلاهما يستقبل `Appointment $appointment, string $companyName, string $currency, string $locale`، ويضبط `$this->locale = $locale` ليُترجَم الإيميل للّغة الصحيحة. اتُّبع نفس نمط [`SendOtpMail`](../app/Mail/SendOtpMail.php) الموجود مسبقاً.

### 3.3 قوالب الـ Blade
- [`resources/views/emails/booking/confirmation.blade.php`](../resources/views/emails/booking/confirmation.blade.php) — إيميل الزبون (تحية + مقدمة + التفاصيل + شكر).
- [`resources/views/emails/booking/notification.blade.php`](../resources/views/emails/booking/notification.blade.php) — إيميل الشركة (مقدمة + **بلوك بيانات الزبون** name/email/phone/source + التفاصيل).
- [`resources/views/emails/booking/partials/details.blade.php`](../resources/views/emails/booking/partials/details.blade.php) — **partial مشترك**: رقم الحجز، التاريخ، الوقت، المدة، المزوّد، طريقة الدفع، الملاحظات، جدول الخدمات، ثم Subtotal/Tax/Total.

القوالب تدعم **RTL تلقائياً** عند `app()->getLocale() === 'ar'` (سمة `dir` ومحاذاة النص).

### 3.4 ملفات اللغة (مفاتيح الترجمة)
- [`lang/en/booking_email.php`](../lang/en/booking_email.php)
- [`lang/ar/booking_email.php`](../lang/ar/booking_email.php)
- [`lang/de/booking_email.php`](../lang/de/booking_email.php)

تحتوي كل المفاتيح (العناوين، التحية، رؤوس الجدول، الإجماليات، الفوتر...).

---

## 4. الملفات المُعدّلة

### `app/Services/BookingService.php`
1. تحويل `return DB::transaction(...)` إلى `$appointment = DB::transaction(...)`.
2. إضافة استدعاء `BookingMailService::sendForNewBooking($appointment)` بعد الـ commit ثم `return $appointment;`.
3. **إصلاح Bug كامن (مهم):** كان `$bookingData` يُستخدَم داخل الـ closure في السطر:
   ```php
   'booking_source' => $bookingData['booking_source'] ?? 'in_person',
   ```
   لكنه **لم يكن ضمن `use(...)`** الخاصة بالـ closure، فكان `$bookingData` غير معرّف داخلها، وبالتالي **كل** الحجوزات كانت تُحفظ `in_person` حتى القادمة من الـ API بقيمة `online`. تمت إضافة `$bookingData` إلى `use(...)` فأصبح `booking_source` يُحفَظ بشكل صحيح. **هذا الإصلاح شرط أساسي** لأن إرسال إيميل الزبون مبنيّ على `booking_source === online`.

---

## 5. مخطط التدفق بعد التعديل

```
createBooking()  ← (API=online | Web/Staff=in_person)
      │
      ├─ التحقق + الحساب
      │
      ├─ DB::transaction:
      │     Appointment::create (booking_source محفوظ صحيحاً الآن)
      │     AppointmentService::create × N
      │     Draft Invoice
      │   (commit)
      │
      └─ BookingMailService::sendForNewBooking($appointment)
            │
            ├─ إيميل الزبون؟  → فقط إذا booking_source = online + يوجد إيميل
            │     Mail::to(customer_email)->queue(BookingConfirmationMail) [بلغة الزبون]
            │
            └─ إيميل الشركة؟ → دائماً
                  recipient = company_email ?: MAIL_FROM_ADDRESS
                  Mail::to(recipient)->queue(BookingNotificationMail) [اللغة الافتراضية]
                          │
                          ▼
                  [queue:work] ← يلتقط الـ Jobs ويرسل عبر SMTP
```

---

## 6. متطلبات التشغيل (مهم جداً)

1. **تشغيل عامل الطابور** — لأن الإرسال مؤجّل عبر `QUEUE_CONNECTION=database`:
   ```bash
   php artisan queue:work
   ```
   بدون عامل طابور يعمل، تبقى رسائل الإيميل في جدول `jobs` ولا تُرسَل.

2. **ضبط إيميل الشركة (اختياري):** من إعدادات الصالون `company_email`. إن تُرك فارغاً، يُرسَل تلقائياً إلى `MAIL_FROM_ADDRESS` من `.env`.

3. **إعدادات SMTP جاهزة** في `.env` (`MAIL_MAILER=smtp`, Gmail). لا تغيير مطلوب.

4. **(اختياري) إعدادات إضافية مدعومة عبر `get_setting`** مع قيم افتراضية آمنة:
   - `currency_symbol` (افتراضي `€`)
   - `default_language` (افتراضي `config('app.locale')`)
   - `company_name` (افتراضي `config('app.name')`)

---

## 7. كيفية الاختبار

### أ) اختبار سريع للتصيير (بدون إرسال)
```bash
php artisan tinker --execute="
\$a = App\Models\Appointment::with(['services_record','provider','customer'])->latest('id')->first();
echo (new App\Mail\BookingConfirmationMail(\$a,'Test Salon','€','ar'))->render();
"
```

### ب) اختبار الإرسال الفعلي
1. شغّل `php artisan queue:work` في طرفية.
2. أنشئ حجزاً عبر `POST /api/bookings` (سيكون `online`) → يصل إيميلان (الزبون + الشركة).
3. أنشئ حجزاً من StaffDashboard (`in_person`) → يصل إيميل الشركة فقط.
4. راجع `storage/logs/laravel.log` عند أي مشكلة (كل الأخطاء مُسجّلة دون كسر الحجز).

---

## 8. ملاحظات تصميمية ونقاط قوة

- ✅ **نقطة ربط واحدة** تغطي كل المسارات (API/Web/Staff) دون تكرار.
- ✅ **بعد الـ commit فقط** — لا إيميلات لحجوزات متراجعة.
- ✅ **Queued + Guarded** — لا يبطّئ الاستجابة، ولا يكسر الحجز عند فشل SMTP.
- ✅ **متعدد اللغات + RTL** — يتبع نظام الترجمة الموجود (en/ar/de).
- ✅ **partial مشترك** لتفاصيل الحجز — صيانة أسهل.
- ✅ **إصلاح جانبي** لقيمة `booking_source` التي كانت لا تُحفَظ.

### نقاط قابلة للتوسعة لاحقاً
- توسيع نطاق إيميل الزبون ليشمل مصادر أخرى = تعديل شرط واحد في `sendCustomerConfirmation()`.
- إضافة مرفق فاتورة PDF = الاقتداء بـ [`PdfGeneratorService`](../app/Services/InvoiceTemplate/PdfGeneratorService.php#L95) وإضافة `->attachData(...)` في الـ Mailable.
- إشعار عند الإلغاء/التعديل = إضافة دوال مماثلة في `BookingMailService`.

</div>
