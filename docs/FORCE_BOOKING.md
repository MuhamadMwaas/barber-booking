<div lang="ar" dir="rtl">

# الحجز القسري — Force Booking (تجاوز نافذة التوفّر)

> **التاريخ:** 2026-06-20
> **النطاق:** تمكين الطاقم الموثوق (admin / manager) من إنشاء حجز من **Staff Dashboard** يتجاوز **نافذة توفّر المزود** (يوم العمل، ساعات الدوام، الإجازة اليومية، الإجازة الساعية)، **دون** تعطيل فحص التعارض مع حجز فعّال ولا فحص "هل المزود يقدّم الخدمة؟"، ودون كسر أي مسار قائم (API / Web / Filament / legacy).
> **الشرح بالعربية — أسماء الكود/المتغيرات بالإنجليزية.**

---

## 1. الهدف بدقّة (المشكلة التي نحلّها)

طلب المالك: أحيانًا يحتاج تسجيل حجز في وقت "غير صالح" نظاميًا:

- مقدّم خدمة في **إجازة** (يومية أو ساعية) لكن زبون مميّز يريد الحجز معه.
- زبون مميّز يريد موعدًا **خارج أوقات الدوام**.
- **فتح المحل** لزبون في يوم لا يعمل فيه المزود (مثلًا الجمعة).

المطلوب: **زرّ واحد محكوم بصلاحية** "يتجاوز فحص الوقت"، بحيث:

| يجب أن يتجاوزه الزر | يجب أن يبقى صارمًا دائمًا |
|---|---|
| #1 يوم عمل المزود (`provider_scheduled_works`) | **#5 التعارض** مع حجز فعّال (المزود لازم يكون فاضيًا) |
| #2 ساعات الدوام | **`validateProviderOffersService`** (المزود لازم يقدّم الخدمة) |
| #3 الإجازة اليومية (Full-day time off) | الماضي + `book_buffer` (#6/#7) |
| #4 الإجازة الساعية (Hourly time off) | الحد اليومي للحجوزات + `max_booking_days` |

**القرارات المعتمدة (من المالك قبل التنفيذ):**

| القرار | المعتمد |
|--------|---------|
| ما يتجاوزه الزر | الفحوص #1–#4 فقط (نافذة التوفّر) — **لا شيء إضافي** |
| الواجهة | Toggle داخل مودال الحجز الحالي، يظهر فقط لمن يملك الصلاحية |
| الصلاحية | `StaffDashboard:force_booking` — لـ **admin / manager فقط** |
| التتبّع | عمود `is_override` (+ `override_reason` اختياري) |

---

## 2. مبدأ التصميم — نفس فلسفة `allow_same_day_past`

اعتمدنا حرفيًا نمط ميزة [السماح بالحجز في الماضي ضمن اليوم](SAME_DAY_PAST_BOOKING.md):

1. **flag بولياني صريح** اسمه `bypass_availability`، **افتراضيّه `false`**.
2. **نقطة قرار واحدة معزولة** تقرّر "هل نطبّق نافذة التوفّر؟" — فلا يتسرّب التساهل لأي قاعدة أخرى.
3. الـ flag يُحقَن **server-side فقط** عند حدود الطاقم، **بعد** التحقق من الصلاحية — العميل لا يستطيع رفعه عبر أي request.

**خريطة تدفّق الـ flag (المسار الوحيد المشمول):**

<div dir="ltr">

```
StaffDashboard::saveBookingFromAlpine()
    │  (1) dashDeny('create_booking')      ← الحارس القائم
    │  (2) إن كان التوغل مرفوعًا: dashDeny('force_booking')   ← حارس جديد
    │  bookingData['bypass_availability'] = true
    ▼
BookingService::createBooking($bookingData)
    │  $bypassAvailability = (bool) bookingData['bypass_availability']
    ▼
BookingService::validateAndPrepareServices(..., $bypassAvailability)
    ▼
BookingValidationService::validateTimeSlotAvailability(..., $bypassAvailability)
    │
    ├── if (! $bypassAvailability)  →  validateProviderScheduleWindow()   ← الفحوص #1–#4 (تُتجاوز)
    ├── (#5) conflict check                                               ← يعمل دائمًا
    └── validateNotInPast() (#6/#7)                                       ← يعمل دائمًا
```

</div>

---

## 3. تسلسل التعديلات بالتفصيل (ملف ملف، ولماذا، وكيف يخدم المهمة)

التعديلات **10 ملفات** مرتّبة من الطبقة الأدنى (قاعدة البيانات) إلى الأعلى (الواجهة) ثم الصلاحيات والترجمة. كل تعديل أدناه مشروح: *ما هو* و*في أي ملف* و*لماذا* و*كيف يخدم المهمة*. (القسم §3.10 إصلاح لاحق لجعل قائمة المزوّدين في المودال تحترم الـ force.)

---

### 3.1 Migration — `database/migrations/2026_06_21_000003_add_is_override_to_appointments_table.php`

**ما هو:** ملف هجرة جديد يضيف عمودين لجدول `appointments`:

```php
$table->boolean('is_override')->default(false)->after('created_status');
$table->string('override_reason')->nullable()->after('is_override');
```

**لماذا:** قرار التتبّع المعتمد هو تمييز الحجوزات القسرية بعمود حقيقي (لا ملاحظة نصّية)، لأنه:
- **قابل للاستعلام**: يُغذّي شارة ⚡ في التايملاين، ويمكن لاحقًا فلترته في الإحصائيات أو تقرير تدقيق ألماني.
- **سجل واضح**: `is_override` = "هل تم تجاوز نافذة التوفّر؟"، و`override_reason` = تبرير الموظف الاختياري.

**كيف يخدم المهمة:** يحوّل "الحجز القسري" من سلوك خفيّ إلى **حقيقة مُسجّلة ومُدقّقة**. القيمة الافتراضية `false` تعني أن كل صف قائم وكل مسار غير-قسري يبقى "عاديًا" تلقائيًا ⇒ صفر أثر جانبي.

**التحقق بعد التنفيذ:** `php artisan migrate` ⇒ العمودان موجودان في `appointments`.

---

### 3.2 Model — `app/Models/Appointment.php`

**ما هو:** تعديلان صغيران:

1. إضافة العمودين إلى `$fillable`:
   ```php
   'is_override',
   'override_reason',
   ```
2. تحويل `is_override` إلى boolean في `$casts`:
   ```php
   'is_override' => 'boolean',
   ```

**لماذا:**
- `$fillable`: بدونها، `Appointment::create([... 'is_override' => true ...])` **سيتجاهل** العمود بصمت (حماية mass-assignment في Laravel)، فلن يُحفظ التمييز إطلاقًا.
- الـ cast: يضمن أن القراءة من القاعدة (`1`/`0`) تعود boolean نظيفًا في PHP و Blade (`@if ($apt['is_override'])`).

**كيف يخدم المهمة:** يجعل الكتابة والقراءة للعمود الجديد تعمل بشكل صحيح عبر Eloquent — حلقة وصل ضرورية بين الهجرة والخدمة.

---

### 3.3 قلب التعديل — `app/Services/BookingValidationService.php`

هذا الملف هو **مركز المنطق**. تعديلان:

**(أ) توقيع `validateTimeSlotAvailability()`** — أُضيف باراميتر اختياري أخير:

<div dir="ltr">

```php
public function validateTimeSlotAvailability(
    User $provider, Service $service,
    Carbon $startTime, Carbon $endTime,
    bool $allowSameDayPast = false,
    bool $bypassAvailability = false   // ← جديد، افتراضي false
): void

```
</div>

**(ب) عزل الفحوص #1–#4 في دالة خاصّة + جعلها مشروطة:**

كانت الفحوص الأربعة (يوم العمل، ساعات الدوام، الإجازة اليومية، الإجازة الساعية) مكتوبة inline داخل الدالة. نقلناها كما هي **بدون أي تغيير منطقي** إلى دالة خاصّة جديدة:

```php
private function validateProviderScheduleWindow(User $provider, Carbon $startTime, Carbon $endTime): void
{
    // ... الفحوص #1، #2، #3، #4 حرفيًا كما كانت ...
}
```

ثم صار نداؤها مشروطًا داخل `validateTimeSlotAvailability()`:

```php
if (! $bypassAvailability) {
    $this->validateProviderScheduleWindow($provider, $startTime, $endTime);
}

// #5 conflict check        ← خارج الشرط: يعمل دائمًا
// validateNotInPast()      ← خارج الشرط: يعمل دائمًا
```

**لماذا هذا الشكل بالذات؟**
- **العزل الجراحي:** صار "هل نفحص نافذة التوفّر؟" قرارًا واحدًا في `if` واحد قابل للتدقيق بنظرة. يستحيل أن يتسرّب التجاوز إلى فحص التعارض أو الماضي لأنهما خارج الشرط أصلًا.
- **عدم تغيير المنطق القائم:** نقلنا الفحوص نقلًا حرفيًا، فالمسار العادي (`$bypassAvailability = false`) يسلك نفس السلوك السابق **بدقّة 100%**.
- `validateProviderOffersService()` لا علاقة لها بهذه الدالة (تُنادى أبكر في `validateAndPrepareServices`)، فهي **لم تُمسّ** ⇒ "المزود يقدّم الخدمة" يبقى مفروضًا حتى في الحجز القسري.

**كيف يخدم المهمة:** هذا هو **الزناد الفعلي للميزة**. رفع `$bypassAvailability` يُسقط الفحوص #1–#4 فقط، تمامًا كما طلب المالك (إجازة/دوام/يوم عطلة)، مع إبقاء حاجز التعارض الذي يضمن أن المزود فعلًا فاضٍ.

---

### 3.4 الوسيط — `app/Services/BookingService.php`

ثلاثة تعديلات لتمرير الـ flag من المدخل إلى طبقة التحقق، وحفظ التمييز:

**(أ) استخراج الـ flag في `createBooking()`:**

```php
$bypassAvailability = (bool) ($bookingData['bypass_availability'] ?? false);
$overrideReason = $bypassAvailability ? ($bookingData['override_reason'] ?? null) : null;
```
> `$overrideReason` يُصفّى ليكون `null` ما لم يكن الحجز قسريًا فعلًا — حتى لا يُحفظ سبب لحجز عادي.

**(ب) تمرير الـ flag إلى التحضير ثم التحقق:**

```php
$preparedServices = $this->validateAndPrepareServices(
    $services, $date, $customer, $customerPhone, $allowSameDayPast, $bypassAvailability
);
```
وداخل `validateAndPrepareServices()` (أُضيف لها نفس الباراميتر الاختياري):
```php
$this->validationService->validateTimeSlotAvailability(
    $provider, $service, $startTime, $endTime, $allowSameDayPast, $bypassAvailability
);
```

**(ج) حفظ التمييز عند الإنشاء** داخل `Appointment::create([...])`:

```php
'is_override' => $bypassAvailability,
'override_reason' => $overrideReason,
```
(وأُضيف `$bypassAvailability, $overrideReason` إلى `use (...)` الخاص بـ `DB::transaction`.)

**لماذا:** `BookingService` لا يثق بالواجهة ولا يكرّر منطق التحقق؛ مهمته توصيل القرار. التمرير صريح وافتراضيّه `false`.

**كيف يخدم المهمة:** يصل قرار "قسري/عادي" من الداشبورد إلى نقطة القرار في `BookingValidationService`، ويُسجَّل في الموعد نفسه.

---

### 3.5 حدود الطاقم + الأمان — `app/Livewire/StaffDashboard.php`

تعديلان داخل `saveBookingFromAlpine()` (المسار النشط الوحيد لإنشاء الحجز من الداشبورد):

**(أ) حارس صلاحية جديد — لا نثق بالـ toggle من العميل:**

```php
$bypassAvailability = (bool) ($data['bypassAvailability'] ?? false);
if ($bypassAvailability && $this->dashDeny('force_booking')) {
    $this->dispatch('booking-error');
    return;
}
```

**(ب) حقن الـ flag في `bookingData`:**

```php
'bypass_availability' => $bypassAvailability,
'override_reason' => $bypassAvailability ? trim((string) ($data['overrideReason'] ?? '')) ?: null : null,
```

**لماذا (نقطة الأمان الحرجة):** إخفاء الزر في الـ Blade ليس خط دفاع كافٍ — يمكن تزوير payload الـ Livewire. لذلك نُعيد التحقق **server-side**: إن وصل التوغل مرفوعًا وكان المستخدم لا يملك `force_booking`، نوقف الطلب فورًا. الحارس القديم `dashDeny('create_booking')` يبقى كخط دفاع أول.

**كيف يخدم المهمة:** يضمن أن **فقط** من يملك الصلاحية يستطيع رفع الـ flag فعليًا، وأن الـ flag يُحقَن من مصدر موثوق (server) لا من ثقة عمياء بالعميل.

---

### 3.6 الواجهة — `resources/views/livewire/staff-dashboard.blade.php`

أربعة تعديلات:

**(أ) الـ Toggle داخل مودال الحجز** (بعد حقل الملاحظات) — يظهر فقط بالصلاحية:

```blade
@if ($this->dashCan('force_booking'))
    <div class="rounded-lg border border-amber-300 bg-amber-50/60 p-3">
        <label ...>
            <input type="checkbox" x-model="booking.bypassAvailability">
            <span>{{ __('dashboard.force_booking.toggle') }}</span>
            <span>{{ __('dashboard.force_booking.hint') }}</span>
        </label>
        <div x-show="booking.bypassAvailability" x-cloak>
            <input type="text" x-model="booking.overrideReason"
                   placeholder="{{ __('dashboard.force_booking.reason_placeholder') }}">
            <p>⚠ {{ __('dashboard.force_booking.warning') }}</p>
        </div>
    </div>
@endif
```

**(ب) حالة Alpine** — أُضيف حقلان إلى كائن `booking` (في التعريف الأولي **وفي** `resetBooking()` معًا):
```js
bypassAvailability: false,
overrideReason: '',
```

**(ج) حمولة `submitBooking()`** — تمرير القيمتين إلى الخادم:
```js
bypassAvailability: this.booking.bypassAvailability,
overrideReason: this.booking.overrideReason,
```

**(د) شارة ⚡ في التايملاين** — في بطاقة الموعد قبل سطر الوقت:
```blade
@if (!empty($apt['is_override']))
    <span class="text-amber-600" title="{{ __('dashboard.force_booking.badge_title') }}">⚡</span>
@endif
```

**لماذا:**
- `@if ($this->dashCan('force_booking'))`: يخفي التوغل عمّن لا يملك الصلاحية (تجربة نظيفة) — والـ server يُعيد التحقق على أي حال.
- إضافة الحقلين في **كلا** موضعَي حالة Alpine ضروري وإلا بقيت القيمة من حجز سابق بعد `resetBooking()`.
- `x-show` على حقل السبب: يظهر فقط عند تفعيل التوغل، مع تنبيه أحمر يذكّر أن فحص التعارض ما زال فعّالًا.

**كيف يخدم المهمة:** يحقّق قرار الواجهة المعتمد (toggle داخل المودال الحالي) ويعطي تتبّعًا بصريًا فوريًا (⚡) لأي حجز قسري على الجدول.

> ملاحظة: الشارة تحتاج أن يحمل عنصر التايملاين الحقل `is_override` — أُضيف في `StaffDashboard::getTimelineDataFromProviders()`: `'is_override' => (bool) $apt->is_override`.

---

### 3.7 تسجيل الصلاحية — `database/seeders/PermissionsSeeder.php`

**ما هو:** إضافة `'force_booking'` إلى قائمة قدرات `StaffDashboard` ضمن `PAGE_ABILITIES`.

**لماذا (حرج):** هذا الـ seeder فيه خطوة **prune** تحذف أي صلاحية في القاعدة غير موجودة في القائمة المرجعية. لو لم نُضِف `force_booking` للقائمة، لكانت ستُحذف عند أول re-seed. الإضافة هنا تجعلها جزءًا من "مصدر الحقيقة".

**كيف يخدم المهمة:** يُنشئ صفّ الصلاحية `StaffDashboard:force_booking` في القاعدة، وهو شرط أساسي لعمل `dashCan('force_booking')`.

---

### 3.8 منح الصلاحية — `database/seeders/RoleSeeder.php`

**ما هو:** إضافة `'StaffDashboard:force_booking'` إلى قائمة دور **manager** فقط.

**لماذا:**
- `admin` و`SuperAdmin` قيمتهما `'all'` ⇒ يحصلان على كل صلاحية تلقائيًا (منها الجديدة).
- `manager` قائمته صريحة ⇒ أضفناها له يدويًا.
- `provider` **لم نضِفها له عمدًا** ⇒ المزود العادي لا يملك الحجز القسري افتراضيًا (قرار "admin/manager فقط"). تبقى قابلة للمنح يدويًا لاحقًا من شاشة الأدوار.

**كيف يخدم المهمة:** يطبّق قرار النطاق: الإدارة فقط تستطيع تجاوز القواعد.

**التحقق بعد التنفيذ:** admin=YES، manager=YES، provider=no.

---

### 3.9 الترجمة — `lang/{en,ar,de}/dashboard.php`

**ما هو:** قسم جديد `force_booking` بخمسة مفاتيح في اللغات الثلاث:

| المفتاح | الاستخدام |
|---------|-----------|
| `toggle` | عنوان التوغل |
| `hint` | شرح مختصر تحت العنوان |
| `reason_placeholder` | placeholder حقل السبب |
| `warning` | تنبيه "فحص التعارض يبقى فعّالًا" |
| `badge_title` | tooltip شارة ⚡ في التايملاين |

**لماذا:** الداشبورد ثلاثي اللغة؛ أي نص جديد يجب أن يُترجَم لئلا يظهر مفتاح خام.

**كيف يخدم المهمة:** يكمل تجربة الواجهة بثلاث لغات بشكل متّسق.

---

### 3.10 إصلاح لاحق — قائمة الـ providers في المودال تحترم الـ force

**المشكلة المكتشَفة بعد أول تنفيذ:** الزر كان يتجاوز الفحص عند **الحفظ** فقط، لكن قائمة "Select Provider" داخل المودال تُملأ من `DashboardService::getAvailableProvidersForServiceAtTime()` التي **بحد ذاتها** تستبعد من عنده إجازة/خارج دوام. فمزوّدة في إجازة اليوم (مثل Sophie) **لا تظهر** في القائمة أصلًا ⇒ يستحيل اختيارها حتى مع تفعيل الـ force. الميزة كانت ناقصة عمليًا.

**الإصلاح — 3 تعديلات متكاملة:**

**(أ) `app/Services/DashboardService.php`** — أُضيف `bool $bypassAvailability = false` إلى `getAvailableProvidersForServiceAtTime()`، ولُفّت فحوص النافذة الأربعة (يوم العمل/الساعات/الإجازة اليومية/الساعية) داخل `if (! $bypassAvailability)`. فحص **تعارض المواعيد يبقى خارج الشرط** ⇒ المزود المشغول لا يُعرض أبدًا حتى في الـ force. و`$service->activeProviders()` يبقى الأساس ⇒ "يقدّم الخدمة" مفروض دائمًا.

**(ب) `app/Livewire/StaffDashboard.php`** — `getAvailableProvidersForBooking()` أخذ باراميتر `bool $bypassAvailability` يُمرَّر للخدمة، **بعد** قصره على الصلاحية:
```php
$bypassAvailability = $bypassAvailability && $this->dashCan('force_booking');
```
⇒ طلب مزوّر لا يستطيع كشف المزوّدين في إجازة.

**(ج) `resources/views/livewire/staff-dashboard.blade.php`** — تعديلان:
- `loadProvidersForService()` يمرّر `this.booking.bypassAvailability` كوسيط رابع لـ `$wire.getAvailableProvidersForBooking`.
- على `@change` لتوغل الـ force: تُعاد تعبئة قوائم المزوّدين لكل خدمة (`bs.provider_id = ''` ثم `loadProvidersForService(bs)`) ⇒ بمجرد تفعيل/إلغاء الـ force تتحدث القائمة فورًا (تظهر/تختفي Sophie) دون إعادة اختيار الوقت.

**كيف يخدم المهمة:** يجعل الـ force **متّسقًا بين الجلب والحفظ**: المزوّدون في إجازة يظهرون ويُختارون عند تفعيله، مع بقاء نفس الحارسين (الصلاحية + التعارض). بدون هذا الإصلاح كانت الميزة بلا قيمة عملية لسيناريو "Sophie في إجازة لكنها ستأتي لزبونة مميّزة".

---

## 4. لماذا "لم نكسر النظام"؟ (تحليل المخاطر)

1. **كل باراميتر جديد افتراضيّه `false`** ⇒ المسارات غير المحدَّثة (API، Web، Filament `CreateAppointment`، `BookingService2`, `AppointmentCreationService`, legacy `saveBooking`) تسلك **نفس سلوكها السابق بالضبط**. تحقّقنا أن كل المستدعين يمرّرون ≤ 5 وسائط ⇒ الوسيط السادس يبقى `false` لديهم.
2. **نقطة قرار واحدة** (`if (! $bypassAvailability)`) ⇒ يستحيل تسرّب التجاوز إلى فحص التعارض أو الماضي.
3. **العميل لا يرفع الـ flag**: لا يُقرأ من أي request API؛ يُحقَن server-side فقط بعد `dashDeny('force_booking')`.
4. **فحص التعارض و"المزود يقدّم الخدمة" باقيان** ⇒ لا حجز قسري فوق موعد قائم، ولا لخدمة لا يقدّمها المزود.
5. **`validateBasicData` لم تُمسّ** ⇒ حظر الأيام السابقة و`max_booking_days` والحد اليومي يبقى ساريًا (قرار المالك: "لا شيء إضافي").

---

## 5. حافة معروفة (ليست خطأ)

سيناريو "نفتح المحل جمعة بعد أسابيع": بما أن القرار كان **عدم** تجاوز `max_booking_days` (افتراضيّه 10)، لو كانت الجمعة أبعد من هذا الحد سيُرفض الحجز عبر `validateBasicData` برسالة "Cannot book more than N days in advance". الحل عند الحاجة: رفع الإعداد، أو توسعة الـ flag مستقبلًا ليشمل هذا الفحص. تُرك صارمًا حسب القرار.

---

## 6. checklist للاختبار اليدوي

> افترض مزوّدًا له موعد قائم 10:00–10:30 اليوم، ودوام 09:00–17:00، ولديه إجازة يومية يوم الجمعة.

1. **حجز قسري خارج الدوام:** فعّل التوغل واحجز 18:30 ⇒ **ينجح** (تجاوز ساعات الدوام).
2. **حجز قسري يوم إجازة:** اختر الجمعة (ضمن `max_booking_days`)، فعّل التوغل واحجز ⇒ **ينجح** (تجاوز يوم العمل/الإجازة).
3. **التعارض يبقى مفروضًا:** فعّل التوغل واحجز 10:00 لنفس المزود ⇒ **يُرفض** ("...is already booked...").
4. **المزود يقدّم الخدمة:** اختر خدمة لا يقدّمها المزود ⇒ **يُرفض** حتى مع التوغل.
5. **بدون توغل = السلوك القديم:** احجز خارج الدوام دون تفعيل التوغل ⇒ **يُرفض** كما قبل.
6. **الصلاحية:** سجّل دخولًا كـ provider بلا `force_booking` ⇒ **التوغل لا يظهر**؛ وحتى لو زُوّر الطلب ⇒ **يُوقَف server-side**.
7. **التتبّع:** أي حجز قسري ناجح ⇒ `is_override = 1` في القاعدة + شارة ⚡ على البطاقة.
8. **API ماضٍ/خارج دوام:** يبقى **مرفوضًا** (لا flag).

---

## 7. ملخّص الملفات المعدّلة

| # | الملف | جوهر التغيير |
|---|------|--------------|
| 1 | `database/migrations/2026_06_21_000003_add_is_override_to_appointments_table.php` | عمودا `is_override` + `override_reason` (جديد) |
| 2 | `app/Models/Appointment.php` | `$fillable` + cast `is_override` boolean |
| 3 | `app/Services/BookingValidationService.php` | باراميتر `$bypassAvailability` + عزل الفحوص #1–#4 في `validateProviderScheduleWindow()` المشروطة |
| 4 | `app/Services/BookingService.php` | استخراج/تمرير الـ flag عبر `createBooking` و`validateAndPrepareServices` + حفظ `is_override`/`override_reason` |
| 5 | `app/Livewire/StaffDashboard.php` | حارس `dashDeny('force_booking')` + حقن الـ flag في `bookingData` + `is_override` في بيانات التايملاين + `bypass` في `getAvailableProvidersForBooking` (§3.10) |
| 6 | `resources/views/livewire/staff-dashboard.blade.php` | toggle + حقل سبب + تنبيه + حالة Alpine + حمولة submit + شارة ⚡ + تمرير bypass وإعادة تحميل القائمة عند التوغل (§3.10) |
| 7 | `app/Services/DashboardService.php` | باراميتر `$bypassAvailability` في `getAvailableProvidersForServiceAtTime()` — يُظهر المزوّدين في إجازة مع إبقاء فحص التعارض (§3.10) |
| 8 | `database/seeders/PermissionsSeeder.php` | تسجيل قدرة `StaffDashboard:force_booking` |
| 9 | `database/seeders/RoleSeeder.php` | منحها لـ manager (admin/SuperAdmin تلقائيًا؛ provider لا) |
| 10 | `lang/{en,ar,de}/dashboard.php` | قسم `force_booking` (5 مفاتيح × 3 لغات) |
| — | `docs/FORCE_BOOKING.md` | هذا الملف |

> migration واحدة بسيطة؛ باقي التغييرات منطق/واجهة/صلاحيات. كل الباراميترات الجديدة افتراضيّها `false` ⇒ توافق رجعي كامل.

</div>
