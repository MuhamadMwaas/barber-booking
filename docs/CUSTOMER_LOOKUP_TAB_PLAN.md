<div dir="rtl">

# خطة تنفيذ: تاب "Customers" — البحث عن العملاء وسجل حجوزاتهم

> **التاريخ:** 2026-05-29
> **الفرع:** main
> **النطاق:** إضافة تاب `Customers` بجانب `Calendar` و `Admin` في شريط Staff Dashboard، يتيح للطاقم البحث عن العملاء (مسجّلين + ضيوف) وعرض سجل حجوزاتهم بشكل **read-only** لفهم "عادة" العميل (الخدمات، ملاحظات العميل، ملاحظات المقدم، الألوان).
> **الحالة:** مخطّط جاهز للتنفيذ — لم يُكتب أي كود بعد.

---

## 0. القرارات المعتمدة (Decision Log)

هذه القرارات حُسمت مع صاحب المشروع وتُعدّ ثابتة لهذه الخطة:

| # | القرار | الخيار المعتمد | الأثر المعماري |
|---|--------|----------------|-----------------|
| D1 | **بنية التاب** | صفحة/مكوّن Livewire **مستقل** (route + component + blade خاص) | عزل كامل عن مكوّن StaffDashboard الضخم؛ تحميل عند الحاجة فقط؛ لا تضخيم للديون التقنية الموجودة |
| D2 | **هوية الضيف** | **قائمة حجوزات مسطّحة بلا تجميع** للضيوف | لا نبني "ملف عميل افتراضي" للضيف؛ كل حجز ضيف مطابق يظهر كصف مستقل |
| D3 | **عرض الحجز** | **Read-only** فقط | لا تعديل من هذه الشاشة؛ عرض الخدمات + الملاحظات + ملاحظات المقدم + الألوان + الحالة/السعر/التاريخ |
| D4 | **آلية البحث** | **زر بحث صريح (Submit)** | لا live-search؛ المستخدم يكتب ثم يضغط "بحث" (أو Enter)؛ أخفّ على السيرفر |

---

## 1. Detailed Task Summary

### 1.1 شرح المهمة بوضوح
المطلوب إضافة **تاب ثالث** في شريط التنقّل العلوي لـ Staff Dashboard اسمه **Customers**، يقع بجانب `Calendar` (الصفحة الحالية) و `Admin` (لوحة Filament). عند فتح التاب:

1. يظهر **مربع بحث** يقبل: **الاسم** أو **الإيميل** أو **رقم الهاتف**.
2. عند الضغط على **زر البحث**، يُجلب:
   - **العملاء المسجّلون** (`User` بدور `customer`) المطابقون.
   - **حجوزات الضيوف** (`Appointment` بـ `customer_id = NULL`) المطابقة، كقائمة **مسطّحة** بلا تجميع.
3. عند اختيار **عميل مسجّل** → تُعرض كل حجوزاته (عبر `customer_id`).
4. عند الضغط على أي **حجز** (سواء من سجل عميل مسجّل أو من قائمة الضيوف) → يُعرض **بشكل read-only**:
   - قائمة الخدمات (`services_record`).
   - ملاحظات العميل (`appointments.notes`).
   - ملاحظات المقدم (`appointments.provider_notes`).
   - الألوان المستخدمة (`colorRecords` → hex / brand / quantity / unit).
   - الحالة + حالة الدفع + الإجمالي + التاريخ.

### 1.2 إعادة الصياغة الدقيقة لما هو مطلوب
> "أعطِ الطاقم ذاكرة عميل سريعة: ابحث عن أي عميل قديم (حتى لو كان ضيفًا بلا حساب) بالاسم/الإيميل/الهاتف، اعرض حجوزاته، ومن الحجز اعرض ما يكفي لفهم كيف يُقدَّم له عادةً — كل ذلك بسرعة وبشكل عرض فقط."

### 1.3 الهدف التجاري/الوظيفي
- **استرجاع المعرفة المهنية**: عميل يقول "بدي مثل العادة" → الموظف يفتح سجله ويرى الخدمات والألوان وملاحظات المقدم السابقة → يقدّم نفس الخدمة بثقة دون اعتماد على ذاكرة بشرية.
- **استمرارية الخدمة** بين مقدّمي خدمة مختلفين: المعرفة لم تعد محبوسة في ذهن مقدم واحد.
- **سرعة الاستقبال**: تقليل وقت السؤال/التذكّر عند الكاونتر.

هذه الميزة هي امتداد طبيعي لميزة **Provider Notes & Colors** الموثّقة في [docs/PROVIDER_NOTES_AND_COLORS.md](PROVIDER_NOTES_AND_COLORS.md)، لكن من زاوية "العميل عبر الزمن" بدلًا من "اليوم الواحد".

---

## 2. Current State — فهم الوضع الحالي

### 2.1 ما هو جاهز ويخدمنا
| العنصر | الموقع | كيف يخدم المهمة |
|--------|--------|------------------|
| **مكان التاب جاهز** | [staff-dashboard.blade.php:10-16](../resources/views/livewire/staff-dashboard.blade.php#L10-L16) | يوجد `<nav>` فيه Calendar/Admin + سطر `customers` **معطّل بالتعليق** |
| **مفتاح الترجمة موجود** | `lang/{en,ar,de}/dashboard.php` | `dashboard.customers` معرّف مسبقًا في اللغات الثلاث |
| **بحث عملاء مسجّلين جزئي** | [DashboardService::getCustomers()](../app/Services/DashboardService.php#L295-L310) | يبحث في `User` بالاسم/الإيميل/الهاتف (لكن **لا يشمل الضيوف** ويستخدم `like` حسّاس لحالة الأحرف) |
| **محمّل تفاصيل غني جاهز** | [DashboardService::getAppointmentDetails()](../app/Services/DashboardService.php#L316-L331) | يحمّل مسبقًا `services_record`, `provider`, `invoice.items`, **`colorRecords.color`** — يصلح مباشرةً لشاشة العرض |
| **عرض غني مرجعي** | [staff-dashboard.blade.php:810-999](../resources/views/livewire/staff-dashboard.blade.php#L810-L999) | يعرض ملاحظات العميل + ملاحظات المقدم + الألوان — نقتبس منه التصميم (لكن بنسخة read-only) |
| **علاقات Appointment** | [Appointment.php](../app/Models/Appointment.php) | `customer()`, `provider()`, `services_record()`, `colorRecords()`, accessors للضيف |

### 2.2 المفهوم المزدوج لـ"العميل" (أهم نقطة بنيوية)
- **عميل مسجّل**: `User` (role=customer) → حجوزاته عبر `Appointment.customer_id`.
- **عميل ضيف**: `Appointment.customer_id = NULL` → الهوية في الأعمدة الخام `customer_name` / `customer_email` / `customer_phone`.

> ⚠️ **تنبيه حرج:** الـ accessors `getCustomerNameAttribute` / `getCustomerEmailAttribute` / `getCustomerPhoneAttribute` في [Appointment.php:241-254](../app/Models/Appointment.php#L241-L254) تُرجع بيانات `User` إن وُجد، وإلا الأعمدة الخام. لذلك **بحث الضيوف يجب أن يستهدف الأعمدة الخام مباشرة في الاستعلام** (`whereNull('customer_id')` + `where('customer_name'/'customer_email'/'customer_phone', 'ilike', ...)`)، لا الـ accessor.

### 2.3 قيود بنيوية يجب احترامها
1. **الـ header (شريط التابات) داخل مكوّن StaffDashboard وليس في الـ layout.** تأكّدنا أن [layouts/dashboard.blade.php](../resources/views/layouts/dashboard.blade.php) يحتوي فقط على `{{ $slot }}` + مستمعي `notify`/`printInvoice`/`printAppointment`. ⟸ لذا يجب **استخراج الـ nav إلى partial مشترك** ليظهر في الصفحتين مع إبراز التاب النشط الصحيح.
2. **الوصول** عبر صلاحية `StaffDashboard:access` في [EnsureStaffDashboardAccess](../app/Http/Middleware/EnsureStaffDashboardAccess.php) → سنعيد استخدام نفس الـ middleware/الصلاحية (لا صلاحية جديدة).
3. **قاعدة البيانات PostgreSQL** (حسب Agent.md) → نستخدم `ilike` لعدم حساسية حالة الأحرف، ونحدّ النتائج (limit) للسرعة.
4. **StaffDashboard ضخم وفيه ديون تقنية** → لا نضيف أي منطق بحث عميل بداخله؛ كل المنطق الجديد في مكوّن/خدمة منفصلة.
5. **نمط المشروع**: cache قصير للبيانات الثابتة، Alpine لحالة الواجهة المؤقتة، Livewire لحالة السيرفر، أحداث `notify` عبر الـ layout.

---

## 3. Target Outcome — السلوك النهائي المتوقع

بعد التنفيذ يجب أن يصبح النظام كالتالي:

1. يظهر تاب **Customers** نشطًا/قابلًا للنقر بجانب Calendar و Admin في   مع إبراز صحيح للتاب الحالي.
2. زيارة `/dashboard/customers` تفتح مكوّن `CustomerLookup` بنفس الـ layout والـ header.
3. كتابة استعلام (≥ 2 حرف) + الضغط على **بحث** يعرض:
   - قسم **"عملاء مسجّلون"**: بطاقات (اسم + هاتف + إيميل + عدد الحجوزات).
   - قسم **"حجوزات ضيوف"**: قائمة مسطّحة من الحجوزات (اسم الضيف + هاتف + تاريخ + ملخّص خدمات + شارات: له ملاحظات/ألوان).
4. النقر على **عميل مسجّل** يعرض سجل حجوزاته (الأحدث أولًا) كصفوف قابلة للنقر.
5. النقر على **أي حجز** يفتح عرض **read-only** كامل (خدمات + ملاحظات العميل + ملاحظات المقدم + الألوان + الحالة + الدفع + الإجمالي + التاريخ).
6. لا يوجد أي زر تعديل/حفظ/دفع/إلغاء في هذه الشاشة (read-only بحت).
7. البحث سريع (limit + ilike + eager-loading صحيح، بلا N+1)، يعمل بالعربية/الإنجليزية/الألمانية ويحترم RTL.
8. **لا تراجع (no regression)** في صفحة Calendar الحالية بعد استخراج الـ nav.

---

## 4. Execution Plan — خطة التنفيذ بالمراحل

> **ملاحظة عامة:** كل subtask مكتوب كبند TODO قابل للتنفيذ مع **سبب (Why)**. نفّذ المراحل بالترتيب لأن بينها تبعيات.

---

### Phase 0 — استخراج الـ Navigation المشترك (تمهيد إلزامي)

**الهدف:** جعل شريط التابات (Calendar / Customers  + مبدّل اللغة + الإشعارات + الأفاتار) قابلًا لإعادة الاستخدام في الصفحتين دون تكرار، مع إبراز التاب النشط الصحيح. **هذه المرحلة شرط لكل ما بعدها.**

**Dependencies:** لا شيء (نقطة البداية).

**Tasks / TODO:**

- [ ] **0.1 — إنشاء partial مشترك للـ header**: أنشئ `resources/views/partials/staff-nav.blade.php` بنقل محتوى `<header>...</header>` من [staff-dashboard.blade.php:7-67](../resources/views/livewire/staff-dashboard.blade.php#L7-L67) كما هو.
  - **Why:** الـ header حاليًا محبوس داخل مكوّن واحد؛ بدون استخراجه سنضطر لنسخه في الصفحة الجديدة (تكرار = ديون). الـ partial يجعل أي تعديل مستقبلي على الشريط في مكان واحد.
- [ ] **0.2 — تمرير متغيّر `$active`**: اجعل الـ partial يستقبل `$active` ('calendar' | 'customers') ويبني كلاسات الإبراز ديناميكيًا (نشط = `text-amber-600 border-b-2 border-amber-500`، غير نشط = `text-gray-500 hover:text-gray-700`).
  - **Why:** كل صفحة يجب أن تُبرز تابها الصحيح؛ تمرير `$active` أنظف من شرط مكرر داخل الـ partial.
- [ ] **0.3 — تفعيل تاب Customers**: داخل الـ partial، استبدل/فعّل السطر المعطّل ليشير إلى `<a href="/dashboard/customers">{{ __('dashboard.customers') }}</a>`، مع إبقاء Calendar (`/dashboard`) و Admin (`/admin`).
  - **Why:** هذا هو الربط البصري للميزة؛ مفتاح `dashboard.customers` موجود مسبقًا فلا حاجة لترجمة جديدة هنا.
- [ ] **0.4 — توحيد مصدر `activeLanguages`**: أنشئ trait `app/Livewire/Concerns/ProvidesDashboardChrome.php` فيه `protected function getActiveLanguages(): array` (نقل المنطق من [StaffDashboard::getActiveLanguages()](../app/Livewire/StaffDashboard.php#L1253-L1267)). اجعل StaffDashboard يستخدم الـ trait، وكذلك المكوّن الجديد.
  - **Why:** الـ partial يعتمد على `$activeLanguages`؛ توحيده في trait يمنع تكرار منطق الـ cache في مكوّنين.
- [ ] **0.5 — تعديل staff-dashboard.blade.php**: استبدل كتلة `<header>` بـ `@include('partials.staff-nav', ['active' => 'calendar'])`.
  - **Why:** تطبيق الاستخراج على الصفحة القائمة + ضمان أن Calendar يبقى التاب المُبرَز.
- [ ] **0.6 — اختبار عدم التراجع يدويًا**: افتح `/dashboard` وتأكّد أن الشريط واللغة والأفاتار تعمل تمامًا كما قبل.
  - **Why:** الاستخراج تغيير حسّاس على صفحة منتجة؛ يجب التأكد من صفر تراجع قبل المتابعة.

**ملاحظات تنفيذ:**
- الـ partial يستخدم `auth()->user()`, `app()->getLocale()`, `$activeLanguages` — تأكّد أنها متاحة في سياق كلا المكوّنين.
- لا تنقل أي منطق Alpine خاص بالـ timeline إلى الـ partial؛ فقط الـ header.

---

### Phase 1 — Routing & هيكل المكوّن الجديد

**الهدف:** إنشاء الصفحة المستقلة `/dashboard/customers` بمكوّن Livewire فارغ يعمل ضمن نفس الحماية والـ layout والـ header.

**Dependencies:** Phase 0.

**Tasks / TODO:**

- [ ] **1.1 — إضافة Route**: داخل مجموعة `EnsureStaffDashboardAccess` في [routes/web.php:78-92](../routes/web.php#L78-L92) أضف:
  `Route::livewire('/dashboard/customers', \App\Livewire\CustomerLookup::class)->name('staff.dashboard.customers');`
  - **Why:** إعادة استخدام نفس الـ middleware يضمن نفس قواعد الوصول دون كود إضافي؛ والاسم يسهّل الربط.
- [ ] **1.2 — إنشاء المكوّن**: `app/Livewire/CustomerLookup.php` يستخدم trait الـ Phase 0، ويرجع `view('livewire.customer-lookup', [...])->layout('layouts.dashboard')`.
  - **Why:** نفس الـ layout = نفس الإطار العام والـ toast/print listeners؛ والـ trait يوفّر `activeLanguages`.
- [ ] **1.3 — إنشاء الـ blade**: `resources/views/livewire/customer-lookup.blade.php` ببنية: جذر `<div class="h-screen flex flex-col">` + `@include('partials.staff-nav', ['active' => 'customers'])` + منطقة محتوى رئيسية فارغة + حالة "ابدأ البحث".
  - **Why:** هيكل مطابق بصريًا لصفحة Calendar (header ثابت + body) يحافظ على اتساق التجربة.
- [ ] **1.4 — حالة فارغة افتراضية (Empty State)**: رسالة لطيفة "ابحث عن عميل بالاسم أو الإيميل أو الهاتف" عند عدم وجود استعلام.
  - **Why:** يمنع شاشة فارغة محيّرة عند الدخول الأول.
- [ ] **1.5 — تحقّق التشغيل**: افتح `/dashboard/customers` وتأكّد من ظهور الشريط مع إبراز تاب Customers والصفحة الفارغة.
  - **Why:** بوابة تأكيد قبل بناء المنطق.

**ملاحظات تنفيذ:**
- تسمية المكوّن `CustomerLookup` (بحث/استعراض) أوضح من `CustomerHistory` لأنه يشمل البحث + السجل + العرض.
- لا تضف `wire:poll` هنا (لا حاجة لتحديث دوري في شاشة بحث).

---

### Phase 2 — طبقة البيانات (Search & History Service)

**الهدف:** بناء كل استعلامات البحث والسجل في خدمة مستقلة محسّنة الأداء، تغطّي العملاء المسجّلين + الضيوف، وتعيد بيانات جاهزة للعرض.

**Dependencies:** لا شيء (يمكن العمل عليها بالتوازي مع Phase 1)، لكنها مطلوبة قبل Phase 3.

**Tasks / TODO:**

- [ ] **2.1 — إنشاء `app/Services/CustomerLookupService.php`**.
  - **Why:** عزل منطق الاستعلامات عن المكوّن (single responsibility) وتسهيل الاختبار وإعادة الاستخدام.
- [ ] **2.2 — `searchRegisteredCustomers(string $q, int $limit = 25): Collection`**: استعلام `User` (role=customer, is_active) مع `ilike` على first_name/last_name/email/phone، مع `withCount` لعدد حجوزاته (`customerAppointments`), `limit`.
  - **Why:** بطاقات العملاء المسجّلين تحتاج الاسم + الهاتف + الإيميل + عدد الحجوزات؛ `withCount` يتجنّب N+1.
  - **Note:** استخدم `ilike` بدل `like` (الموجود في `getCustomers`) لأن PostgreSQL يجعل `like` حسّاسًا لحالة الأحرف.
- [ ] **2.3 — `searchGuestAppointments(string $q, int $limit = 50): Collection`**: استعلام `Appointment` بـ `whereNull('customer_id')` + `ilike` على الأعمدة الخام `customer_name`/`customer_email`/`customer_phone`، مرتّب `appointment_date desc`, مع eager-load خفيف (`provider:id,first_name,last_name`, `services_record:id,appointment_id,service_name,sequence_order`) + `withCount('colorRecords')`.
  - **Why:** قرار D2 = قائمة مسطّحة؛ كل صف يحتاج ملخّصًا سريعًا (مقدم + خدمات + مؤشّر ألوان) دون تحميل التفاصيل الكاملة.
  - **Note:** **لا تستخدم accessor `customer_name`** هنا — استعلم على الأعمدة الخام مباشرة (انظر §2.2 في فهم الوضع الحالي).
- [ ] **2.4 — `getCustomerAppointments(int $userId, int $limit = 100): Collection`**: كل حجوزات العميل المسجّل عبر `customer_id`، مرتّبة `appointment_date desc`, بنفس الـ eager-load الخفيف للملخّص + `withCount('colorRecords')`.
  - **Why:** عند اختيار عميل مسجّل نعرض سجله كاملًا (أو حتى حد معقول) بصفوف ملخّصة سريعة.
- [ ] **2.5 — إعادة استخدام التفاصيل الكاملة**: لشاشة العرض read-only، استخدم [DashboardService::getAppointmentDetails($id)](../app/Services/DashboardService.php#L316-L331) القائمة (تحمّل `services_record`, `provider`, `invoice.items`, `colorRecords.color`).
  - **Why:** لا تكرار — الدالة تحمّل بالفعل كل ما تحتاجه شاشة العرض بما فيها الألوان وملاحظات المقدم (عبر حقول الموديل).
- [ ] **2.6 — (اختياري/تحسين) فهارس DB**: migration يضيف فهارس على `appointments(customer_phone)`, `appointments(customer_email)` و(إن أمكن) فهرس trigram (`gin_trgm_ops`) لتسريع `ilike` على بيانات كبيرة. نفّذ فقط إن كان حجم البيانات يبرّره.
  - **Why:** `ilike %x%` لا يستفيد من الفهارس B-tree العادية؛ trigram يسرّع البحث الجزئي. يُؤجَّل إن كانت البيانات صغيرة.
  - **Dependency:** قرار صاحب المشروع حول حجم البيانات المتوقع (يمكن تأجيله لمرحلة لاحقة دون كسر شيء).

**ملاحظات تنفيذ:**
- وحّد حدّ أدنى لطول الاستعلام (≥ 2 حرف) في الخدمة أو المكوّن لتفادي مسح الجدول كاملًا.
- رتّب دائمًا الأحدث أولًا (`appointment_date desc, start_time desc`).
- قرار العرض: **أظهر كل الحالات** (بما فيها الملغاة/No-show) مع شارة حالة واضحة — لأن "التاريخ" يفيد حتى لو أُلغي؛ يمكن لاحقًا إضافة فلتر.

---

### Phase 3 — واجهة البحث (Explicit Submit)

**الهدف:** نموذج بحث بزر صريح يعرض النتائج في قسمين (مسجّلون + ضيوف) بسرعة ووضوح.

**Dependencies:** Phase 1 (الهيكل) + Phase 2 (الخدمة).

**Tasks / TODO:**

- [ ] **3.1 — حالة المكوّن**: أضف `public string $search = '';` + `public bool $searched = false;` + خصائص النتائج (`$registeredResults`, `$guestResults`) أو احسبها في `search()`.
  - **Why:** `searched` يميّز "لم يبحث بعد" عن "بحث ولا نتائج".
- [ ] **3.2 — دالة `search()`**: تتحقّق من طول الاستعلام (≥2)، تستدعي `searchRegisteredCustomers` + `searchGuestAppointments`, وتعبّئ النتائج. اربطها بـ `wire:submit` على `<form>` (يدعم Enter) + زر "بحث".
  - **Why:** قرار D4 = submit صريح؛ `wire:submit` يلتقط Enter والزر معًا.
- [ ] **3.3 — مربع البحث + الزر**: input (`wire:model="search"` بدون `.live`) + زر submit + زر "مسح" يعيد الحالة.
  - **Why:** بدون `.live` = لا رحلات لكل ضغطة مفتاح (قرار D4، وأخفّ على السيرفر).
- [ ] **3.4 — قسم العملاء المسجّلين**: بطاقات (اسم + شارة "مسجّل" + هاتف + إيميل + عدد الحجوزات) قابلة للنقر `wire:click="selectCustomer(id)"`.
  - **Why:** تمييز بصري واضح أن هذا حساب موحّد له سجل كامل.
- [ ] **3.5 — قسم حجوزات الضيوف (قائمة مسطّحة)**: صفوف (اسم الضيف + هاتف + تاريخ + ملخّص خدمات + شارات ألوان/ملاحظات) قابلة للنقر `wire:click="viewAppointment(id)"` تفتح العرض مباشرة.
  - **Why:** قرار D2 = لا تجميع؛ الضيف يفتح تفاصيل الحجز مباشرة (لا "ملف").
- [ ] **3.6 — حالات الحافة**: حالة "لا نتائج"، حالة "اكتب حرفين على الأقل"، مؤشّر `wire:loading` على زر البحث.
  - **Why:** وضوح التجربة وتغذية راجعة فورية أثناء الاستعلام.
- [ ] **3.7 — RTL/i18n**: استخدم مفاتيح ترجمة جديدة (Phase 6) وراعِ الاتجاه عبر `app()->getLocale()`.
  - **Why:** المشروع ثلاثي اللغة؛ النصوص المضمّنة تكسر الاتساق.

**ملاحظات تنفيذ:**
- اعرض القسمين بترتيب: المسجّلون أولًا ثم الضيوف، مع عنوان فرعي لكل قسم وعدّاد نتائج.
- إن كان أحد القسمين فارغًا، أخفِ عنوانه بدل عرض قسم فارغ.

---

### Phase 4 — سجل حجوزات العميل المسجّل

**الهدف:** عند اختيار عميل مسجّل، عرض كل حجوزاته كقائمة ملخّصة قابلة للنقر.

**Dependencies:** Phase 2 (`getCustomerAppointments`) + Phase 3.

**Tasks / TODO:**

- [ ] **4.1 — `selectCustomer(int $userId)`**: تخزّن `$selectedCustomerId` وتجلب `getCustomerAppointments`، مع رأس يعرض اسم/هاتف/إيميل العميل وزر "رجوع للنتائج".
  - **Why:** انتقال واضح من "نتائج البحث" إلى "سجل عميل محدّد" مع إمكانية الرجوع.
- [ ] **4.2 — قائمة الحجوزات الملخّصة**: صفوف (تاريخ + وقت + مقدم + ملخّص خدمات + شارة حالة + إجمالي + شارات ملاحظات/ألوان) `wire:click="viewAppointment(id)"`.
  - **Why:** نظرة سريعة على التاريخ تتيح للموظف رصد "النمط/العادة" قبل فتح التفاصيل.
- [ ] **4.3 — حالة "لا حجوزات"**: رسالة عند عميل مسجّل بلا حجوزات.
  - **Why:** بعض العملاء مسجّلون دون حجوزات فعلية.

**ملاحظات تنفيذ:**
- أبرز بصريًا الحجوزات التي تحتوي على ملاحظات مقدم/ألوان (هي الأهم لـ"فهم العادة").

---

### Phase 5 — عرض الحجز Read-only

**الهدف:** عرض كامل للحجز للقراءة فقط: خدمات + ملاحظات العميل + ملاحظات المقدم + الألوان + الحالة/الدفع/الإجمالي/التاريخ.

**Dependencies:** Phase 2 (`getAppointmentDetails`) + Phase 3/4 (نقاط الدخول).

**Tasks / TODO:**

- [ ] **5.1 — `viewAppointment(int $id)` + computed `selectedAppointment`**: استخدم `#[Computed]` يستدعي `getAppointmentDetails($id)` (نمط مطابق لـ StaffDashboard).
  - **Why:** computed يضمن تحميلًا واحدًا ويُبسّط الـ blade؛ والدالة جاهزة بكل العلاقات.
- [ ] **5.2 — partial عرض read-only**: أنشئ `resources/views/partials/appointment-readonly.blade.php` يعرض:
  - رقم الحجز + التاريخ + الوقت.
  - العميل (اسم؛ شارة "@" إن مسجّل) + الهاتف/الإيميل.
  - الخدمات (`services_record` مرتّبة بـ sequence_order + المدة + السعر).
  - ملاحظات العميل (`notes`) — نص للعرض فقط.
  - ملاحظات المقدم (`provider_notes`) — بتمييز أزرق كالأصل، عرض فقط.
  - الألوان (`colorRecords`): مربع hex + اسم + brand + الكمية + الوحدة.
  - الحالة + حالة الدفع (نفس badge maps في [staff-dashboard.blade.php:677-721](../resources/views/livewire/staff-dashboard.blade.php#L677-L721)) + الإجمالي.
  - **Why:** قرار D3 = read-only؛ نقتبس التصميم من المودال القائم لكن **بلا** textarea/أزرار حفظ/حذف/دفع.
- [ ] **5.3 — حاوية العرض (modal أو panel)**: اعرض الـ partial داخل modal (`wire:click.self` للإغلاق) أو panel جانبي، مع زر إغلاق و`@if ($this->selectedAppointment)`.
  - **Why:** نمط المودال مألوف في الـ dashboard ويحافظ على سياق نتائج البحث خلفه.
- [ ] **5.4 — تأكيد غياب أي إجراء كتابة**: راجع الـ partial للتأكد من عدم وجود أي `wire:click` لكتابة/تعديل.
  - **Why:** صون قرار read-only ومنع تسرّب منطق تعديل عن طريق النسخ من المودال الأصلي.

**ملاحظات تنفيذ:**
- العرض يعمل لكل من المسجّل والضيف بنفس الـ partial (التفاصيل مستقلة عن `customer_id`).
- استخدم `customer_name`/`customer_email`/`customer_phone` accessors هنا للعرض (تتعامل مع الحالتين تلقائيًا).

---

### Phase 6 — الترجمة، الصقل، والأداء، وقائمة الفحص

**الهدف:** اكتمال ثلاثي اللغة + RTL + ضمان الأداء (بلا N+1) + قائمة QA يدوية.

**Dependencies:** Phases 1–5.

**Tasks / TODO:**

- [ ] **6.1 — مفاتيح ترجمة جديدة**: أضف قسم `customer_lookup` في `lang/{en,ar,de}/dashboard.php` (search_placeholder, search_button, clear, min_chars, registered_customers, guest_bookings, no_results, bookings_count, back_to_results, no_bookings, view_booking, … + عناوين أقسام العرض إن لزم).
  - **Why:** المشروع ثلاثي اللغة؛ كل نص واجهة يجب أن يكون مترجمًا (مفتاح `customers` موجود مسبقًا فقط للتاب).
- [ ] **6.2 — مراجعة RTL**: تحقّق من المحاذاة/الأيقونات في العربية على كل الشاشات الفرعية.
  - **Why:** اتجاه RTL يكسر بعض التخطيطات إن لم يُراعَ.
- [ ] **6.3 — تدقيق الأداء (N+1)**: فعّل `DB::listen`/Laravel Debugbar أثناء بحث ضخم وتأكّد من عدد استعلامات ثابت (eager-loading + withCount).
  - **Why:** البحث على بيانات كبيرة قد يولّد N+1 في صفوف الملخّص (مقدم/خدمات/عدد ألوان) إن لم تُحمّل مسبقًا.
- [ ] **6.4 — حدود ونتائج**: ثبّت `limit` معقولًا واعرض تلميح "ضيّق بحثك" عند بلوغ الحد.
  - **Why:** يمنع تحميل آلاف الصفوف ويحافظ على السرعة المطلوبة.
- [ ] **6.5 — قائمة QA يدوية** (نفّذها وسجّل النتائج):
  1. الوصول: مستخدم بلا `StaffDashboard:access` يُمنع (403).
  2. التابات تظهر وتُبرَز صحيحًا في `/dashboard` و`/dashboard/customers`.
  3. بحث بالاسم يعيد عملاء مسجّلين + ضيوف.
  4. بحث بالهاتف يعيد ضيوفًا بلا حساب.
  5. بحث بالإيميل يعمل (حساسية الأحرف عبر ilike).
  6. اختيار عميل مسجّل يعرض سجله الأحدث أولًا.
  7. النقر على حجز (مسجّل وضيف) يفتح عرض read-only كاملًا بالألوان وملاحظات المقدم.
  8. لا يوجد أي زر تعديل/حفظ/دفع في العرض.
  9. صفحة Calendar تعمل بلا تراجع بعد استخراج الـ nav.
  10. اللغات الثلاث + RTL تعمل.
  - **Why:** لا توجد اختبارات آلية لهذه الشاشات (حسب توثيق Staff Dashboard)، فالـ QA اليدوي هو شبكة الأمان.

**ملاحظات تنفيذ:**
- لا تستخدم cache لنتائج البحث (متغيّرة وحسب الاستعلام)؛ الـ cache مناسب فقط للقوائم الثابتة كالألوان/اللغات.

---

## 5. ملخّص الملفات المتأثّرة

| النوع | الملف | التغيير |
|------|-------|---------|
| جديد | `app/Livewire/CustomerLookup.php` | المكوّن الرئيسي |
| جديد | `app/Livewire/Concerns/ProvidesDashboardChrome.php` | trait لـ `getActiveLanguages()` |
| جديد | `app/Services/CustomerLookupService.php` | استعلامات البحث/السجل |
| جديد | `resources/views/livewire/customer-lookup.blade.php` | واجهة المكوّن |
| جديد | `resources/views/partials/staff-nav.blade.php` | شريط التابات المشترك |
| جديد | `resources/views/partials/appointment-readonly.blade.php` | عرض الحجز read-only |
| جديد (اختياري) | `database/migrations/..._add_search_indexes_to_appointments.php` | فهارس بحث (Phase 2.6) |
| تعديل | `routes/web.php` | route جديد ضمن مجموعة الحماية |
| تعديل | `resources/views/livewire/staff-dashboard.blade.php` | استبدال `<header>` بالـ partial |
| تعديل | `app/Livewire/StaffDashboard.php` | استخدام الـ trait (Phase 0.4) |
| تعديل | `lang/{en,ar,de}/dashboard.php` | قسم `customer_lookup` |
| إعادة استخدام | `app/Services/DashboardService.php::getAppointmentDetails()` | بلا تعديل (للعرض read-only) |

---

## 6. المخاطر والتخفيف

| الخطر | التخفيف |
|-------|---------|
| كسر صفحة Calendar عند استخراج الـ nav | Phase 0.6 اختبار تراجع صريح قبل المتابعة |
| بطء `ilike %x%` على بيانات كبيرة | limit + حد أدنى للأحرف + فهارس trigram اختيارية (2.6) |
| N+1 في صفوف الملخّص | eager-load خفيف + `withCount` + تدقيق 6.3 |
| البحث على accessor بدل الأعمدة الخام للضيوف | تنبيه صريح §2.2 + 2.3 — الاستعلام على الأعمدة الخام |
| تسرّب منطق تعديل من المودال الأصلي إلى العرض | partial read-only منفصل + مراجعة 5.4 |
| ازدواج منطق اللغة/الـ header | trait + partial مشترك (Phase 0) |

---

## 7. خارج النطاق (Out of Scope)

- أي تعديل/إضافة/دفع للحجوزات من تاب Customers (read-only فقط — D3).
- بناء "ملف عميل موحّد" للضيوف (D2 = قائمة مسطّحة).
- تصدير/طباعة سجل العميل.
- live-search لحظي (D4 = submit صريح).
- نظام مخزون حقيقي للألوان (كما في توثيق الألوان — توثيقي فقط).

---

*أُعدّت هذه الخطة بناءً على قراءة مباشرة للكود الحالي بتاريخ 2026-05-29. النظام: إدارة صالون — Laravel 12 + Filament 4.0 + Livewire.*
