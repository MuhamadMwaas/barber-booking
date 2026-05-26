# خطة تنفيذ ميزة التفريق بين `Online API` bookings و`In-store/Internal` bookings

> هذه الوثيقة **خطة تنفيذ فقط**. لا تحتوي تنفيذ فعلي للكود، ولا تغيّر سلوك النظام حالياً.

---

## 1. الهدف من المهمة

المطلوب هو إضافة قدرة واضحة وصريحة داخل النظام لتمييز مصدر إنشاء الموعد نفسه، بحيث نستطيع التفريق بين:

- موعد تم إنشاؤه من التطبيق/العميل عبر `API`
- موعد تم إنشاؤه داخلياً من قبل الموظف أو الإدارة عبر `Staff Dashboard` أو `Filament Admin`

المطلوب هنا هو **تمييز source of booking** وليس `payment_method`، وليس `payment_status`، وليس `created_status`.

بمعنى آخر:

- `payment_method = online` لا يعني بالضرورة أن الموعد تم إنشاؤه من التطبيق
- `payment_method = cash` لا يعني بالضرورة أن الموعد تم إنشاؤه داخل المحل
- `created_status = 1/0` هو مؤشر تأكيد/حجز فعلي للوقت، وليس channel/source

---

## 2. القرارات المثبتة قبل التنفيذ

بحسب الإجابات المعتمدة لهذه المهمة، التنفيذ يجب أن يلتزم بالقرارات التالية:

1. نريد **مستويين فقط** للمصدر في المرحلة الحالية:
   - `online_api`
   - `internal`

2. `Staff Dashboard` و`Filament Admin` يُعتبران **نفس الفئة business-wise**:
   - كلاهما يدخل تحت `internal`

3. أماكن الإظهار/الفلترة المطلوبة في المرحلة الأولى:
   - `Filament appointments table / infolist`
   - `Staff Dashboard timeline / appointment modal`

4. المواعيد القديمة الموجودة قبل إضافة الحقل:
   - **تبقى `null` / `unknown`**
   - لا نطبّق `backfill` استنتاجي في هذه المرحلة

5. لا نريد حالياً فتح هذه المهمة إلى تعديل print/invoice أو تعريض الحقل في customer-facing API responses إلا إذا تم طلب ذلك لاحقاً.

---

## 3. القراءة الحالية من الكود

### 3.1 مسار الحجز عبر `API`

المسار الفعلي الحالي:

`routes/api.php` -> `App\Http\Controllers\Api\BookingController@store` -> `App\Services\BookingService::createBooking()`

الملاحظات المهمة:

- `BookingCreateRequest` يتحقق من `services`, `date`, `notes`, `payment_method`
- لا يوجد أي حقل باسم `booking_source` أو `booking_channel`
- `Api\BookingController` يمرر `$request->validated()` مباشرة إلى `BookingService`
- `BookingService` ينشئ `Appointment::create([...])` بدون أي metadata يوضح هل الموعد أتى من `API` أو من لوحة داخلية

النتيجة الحالية:

- الموعد القادم من التطبيق لا يحمل أي أثر domain-level واضح يميّزه كمصدر `API`

### 3.2 مسار الحجز عبر `Staff Dashboard`

المسار الفعلي النشط الآن:

`resources/views/livewire/staff-dashboard.blade.php` -> Alpine `submitBooking()` -> `App\Livewire\StaffDashboard::saveBookingFromAlpine()` -> `BookingService::createBooking()`

الملاحظات المهمة:

- `saveBookingFromAlpine()` يرسل:
  - `payment_method = cash`
  - `is_confirmed = true`
  - `mark_as_paid = false`
- هذا المسار يميّز حالة الدفع والتأكيد، لكنه لا يضع أي source flag يدل على أن الموعد داخلي
- يوجد أيضاً مسار legacy داخل نفس المكوّن هو `saveBooking()` ويستدعي `BookingService::createBooking()` كذلك

النتيجة الحالية:

- الحجز الداخلي من الـ dashboard لا يمكن تمييزه عن الحجز القادم من الـ API من خلال `appointments` data model نفسها

### 3.3 مسار إنشاء الموعد عبر `Filament Admin`

المسار الفعلي الحالي:

`App\Filament\Resources\Appointments\Pages\CreateAppointment` -> `handleRecordCreation()` -> `Appointment::create($data)`

الملاحظات المهمة:

- `CreateAppointment` **لا يستخدم** `BookingService`
- الصفحة تجهز البيانات وتتحقق منها ثم تحفظ `Appointment` مباشرة
- لذلك حتى لو أضفنا source handling في `BookingService` فقط، سيبقى مسار `Filament` غير مغطى

النتيجة الحالية:

- `Filament manual appointments` لا تحمل source flag أيضاً

### 3.4 الفجوة الحالية في `appointments` schema

في الجدول `appointments` لا يوجد حالياً أي عمود صريح يصف مصدر إنشاء الموعد.

الموجود حالياً:

- `payment_method`
- `payment_status`
- `created_status`

وكلها **لا تحل المشكلة**:

- `payment_method` يصف كيف يُدفع، لا كيف أُنشئ الموعد
- `payment_status` يصف حالة التحصيل، لا channel
- `created_status` يصف هل الموعد confirmed enough ليحجز الوقت، لا source

### 3.5 ملاحظة مهمة جداً عن `created_status`

هناك نقطة تقنية مهمة يجب المحافظة عليها أثناء التنفيذ:

- `created_status` مستخدم اليوم في availability logic وdashboard queries كـ confirmation flag
- في `AppointmentInfolist` يوجد حالياً row بعنوان `Created Via` لكنه مربوط فعلياً إلى `created_status`

هذا يعني أن الواجهة اليوم تعرض **label مضلل**:

- الاسم يقول: `Created Via`
- لكن القيمة الحقيقية: `created_status`

لذلك لا يجوز البناء فوق هذا الالتباس أو إعادة استخدام `created_status` كمصدر.

---

## 4. التصميم المقترح

### 4.1 إضافة field جديد مستقل: `booking_source`

التصميم المقترح هو إضافة عمود جديد مستقل في `appointments` باسم:

- `booking_source`

نوعه المقترح:

- `string` nullable + indexed

القيم المعتمدة حالياً:

- `online_api`
- `internal`

القيمة للمواعيد القديمة:

- `null`

### 4.2 لماذا `booking_source` أفضل من `boolean` أو إعادة استخدام حقل قديم؟

هذا التصميم أفضل من `is_online_booking` أو من إعادة استعمال `created_status` للأسباب التالية:

1. الحقل يعبّر عن business meaning بشكل صريح
2. يدعم `null` legacy data بسهولة
3. قابل للتوسّع لاحقاً إذا تقرر فصل:
   - `staff_dashboard`
   - `filament_admin`
   - `phone_booking`
   - `walk_in_kiosk`
4. لا يخلط بين source وبين payment أو confirmation

### 4.3 تمثيل القيم على مستوى الـ domain

أفضل تمثيل في الكود هو إضافة `string-backed enum` جديد:

- `App\Enum\BookingSource`

بقيميْن:

- `ONLINE_API = 'online_api'`
- `INTERNAL = 'internal'`

سبب اختيار `string-backed enum` تحديداً:

- القراءة من قاعدة البيانات أو logs ستكون أوضح من tinyint
- قيمة الحقل مفهومة مباشرة عند debugging
- التوسعة المستقبلية أسهل

### 4.4 قاعدة أساسية يجب فرضها

`booking_source` يجب أن يكون **system-owned** وليس user-controlled.

هذا يعني:

- لا نضيفه إلى `BookingCreateRequest`
- لا نسمح للعميل بإرساله من التطبيق
- لا نضيفه كحقل editable داخل `Filament form`
- يتم stamping له داخل السيرفر حسب مسار الإنشاء

### 4.5 العلاقة بين `booking_source` و`payment_method`

العلاقة المقصودة يجب أن تكون مستقلة بالكامل:

- حجز `online_api` قد يكون `payment_method = cash` أو `online`
- حجز `internal` قد يكون `payment_method = cash`, `card`, أو حتى `online` إذا اختارت الإدارة ذلك

إذن:

- `payment_method` لا يُستخدم للاستدلال على source
- `booking_source` لا يغير منطق الدفع الحالي

---

## 5. حدود المرحلة الأولى

### داخل النطاق

- تخزين `booking_source` على كل موعد جديد
- وسم مواعيد `API` كـ `online_api`
- وسم مواعيد `Staff Dashboard` و`Filament Admin` كـ `internal`
- إظهار source في `Filament appointments table`
- إظهار source في `Filament appointment infolist`
- إظهار source في `Staff Dashboard timeline`
- إظهار source في `Staff Dashboard appointment modal`

### خارج النطاق حالياً

- تعديل `invoice print` أو قالب الفاتورة
- تعديل customer-facing `AppointmentResource` responses
- تعديل availability rules بناءً على source
- عمل `data backfill` للمواعيد القديمة
- refactor شامل لتوحيد كل creation paths في service واحدة

---

## 6. خطة التنفيذ التفصيلية خطوة بخطوة

## Step 1 — إضافة schema field جديد في قاعدة البيانات

### الملف الذي سنضيفه

- `database/migrations/<timestamp>_add_booking_source_to_appointments_table.php`

### ما الذي سنفعله فيه

- إضافة عمود جديد `booking_source`
- النوع: `string(...)->nullable()->index()`
- يفضّل وضعه قرب metadata الخاصة بالحجز مثل `payment_method`
- عدم وضع `default` على مستوى قاعدة البيانات

### لماذا هذا مهم

- لأننا نريد الحفاظ على المواعيد القديمة كـ `null`
- ولأن وضع default مثل `internal` سيؤدي إلى **misclassification** لأي مسار ننسى ختمه لاحقاً

### كيف يخدم المهمة

- يوفّر storage layer صريحة للتفريق بين `online_api` و`internal`

---

## Step 2 — إضافة enum domain جديد للمصدر

### الملف الذي سنضيفه

- `app/Enum/BookingSource.php`

### ما الذي سنضيفه فيه

- `string-backed enum`
- الحالات:
  - `ONLINE_API`
  - `INTERNAL`
- helper methods مقترحة:
  - `value` ثابتة (`online_api`, `internal`)
  - `translationKey()` أو ما يعادلها لتسهيل الربط مع ملفات اللغة
  - `filamentColor()` إن أردنا توحيد ألوان الـ badge

### لماذا هذا مهم

- يمنع استخدام strings متناثرة في أكثر من ملف
- يقلل احتمالات typo مثل `online-api` / `online_api` / `api`
- يجعل التحويلات في `Filament` و`Dashboard` و`Model cast` منضبطة

### كيف يخدم المهمة

- يثبت vocabulary واحد للمصدر عبر كل طبقات النظام

---

## Step 3 — تحديث `Appointment` model لاحتضان الحقل الجديد

### الملف الذي سنعدله

- `app/Models/Appointment.php`

### التعديلات المطلوبة

1. إضافة `booking_source` إلى `fillable`
2. إضافة cast:
   - `'booking_source' => BookingSource::class`
3. إضافة helper/accessor مناسب مثل:
   - `isOnlineApiBooking()`
   - `isInternalBooking()`
   - optional: `getBookingSourceLabelAttribute()` مع fallback إلى `Unknown`

### لماذا هذا مهم

- لأن كل أسطح العرض ستقرأ الحقل من `Appointment`
- ولأن `Filament` و`Livewire` سيتعاملان مع model cast أو helper methods بسهولة

### كيف يخدم المهمة

- يجعل source جزءاً أصيلاً من الـ aggregate root بدل أن يبقى مجرد field خام غير منضبط

---

## Step 4 — جعل `BookingService` يفرض وجود source صريح

### الملف الذي سنعدله

- `app/Services/BookingService.php`

### التعديلات المطلوبة

1. قراءة `booking_source` من `$bookingData`
2. جعل وجوده **مطلوباً على مستوى service**
3. إذا لم يُمرَّر، ترمي الخدمة `InvalidArgumentException`
4. أثناء `Appointment::create([...])` نضيف:
   - `'booking_source' => $bookingSource`

### لماذا هذا مهم

- لأن `BookingService` هو نقطة الإنشاء المشتركة بين:
  - `API bookings`
  - `Staff Dashboard bookings`
- إذا تركناه يقبل الإنشاء بدون source، فسنفتح باب regression مستقبلي صامت

### كيف يخدم المهمة

- يضمن أن كل caller لهذه الخدمة يعلن بوضوح هل الحجز `online_api` أم `internal`

### ملاحظة مهمة

- لا نغيّر أي شيء في منطق:
  - `payment_method`
  - `payment_status`
  - `created_status`
- هذه الطبقة فقط تضيف metadata جديدة مستقلة

---

## Step 5 — ختم bookings القادمة من `API` كمصدر `online_api`

### الملف الذي سنعدله

- `app/Http/Controllers/Api/BookingController.php`

### التعديلات المطلوبة

- قبل استدعاء `BookingService::createBooking()` سنبني payload يضيف:
  - `'booking_source' => BookingSource::ONLINE_API->value`

### لماذا هذا الملف بالتحديد

- لأنه هو active controller لمسار `POST /api/bookings`
- وهو boundary الصحيحة لتمييز أن هذا الحجز قادم من التطبيق/API وليس من واجهة داخلية

### كيف يخدم المهمة

- كل حجز عبر الـ API سيُختم تلقائياً كمصدر `online_api`

### شيء مهم لن نفعله هنا

- **لن** نضيف `booking_source` إلى `BookingCreateRequest`
- حتى لا يستطيع client spoof هذا الحقل

---

## Step 6 — تحديث الـ web controller legacy بشكل defensive

### الملف الذي سنعدله

- `app/Http/Controllers/BookingController.php`

### سبب إدخاله في الخطة رغم أنه يبدو غير مربوط حالياً

- هذا controller موجود ويستدعي `BookingService::createBooking()`
- إذا جعلنا `BookingService` يفرض `booking_source` وتركنا هذا المسار دون تحديث، فسنترك future breakage إذا أُعيد ربطه لاحقاً

### التعديل المطلوب

- إضافة `booking_source` صريح في payload قبل تمريره إلى `BookingService`
- وإذا كان هذا controller يمثل حجزاً من واجهة عميل web مستقبلاً، فالأقرب وضعه على `online_api`

### كيف يخدم المهمة

- يغلق فجوة مستقبلية محتملة ويجعل الخدمة آمنة ضد الاستخدام الناقص

### ملاحظة

- إذا تبين أثناء التنفيذ أن هذا المسار legacy غير مستخدم نهائياً، يبقى تحديثه defensive وليس feature expansion

---

## Step 7 — ختم bookings القادمة من `Staff Dashboard` كمصدر `internal`

### الملف الذي سنعدله

- `app/Livewire/StaffDashboard.php`

### الأماكن المحددة داخل الملف

1. `saveBookingFromAlpine(array $data)`
2. `saveBooking()` legacy path
3. `getTimelineDataFromProviders()`

### التعديلات المطلوبة

#### أولاً: في `saveBookingFromAlpine()`

- إضافة:
  - `'booking_source' => BookingSource::INTERNAL->value`

#### ثانياً: في `saveBooking()`

- نفس الإضافة السابقة
- رغم أن هذا المسار يبدو legacy، لكنه ما زال يستدعي `BookingService`

#### ثالثاً: في `getTimelineDataFromProviders()`

- توسيع appointment item shape بإضافة:
  - `booking_source`
  - `booking_source_label` أو equivalent string جاهزة للعرض

### لماذا هذا مهم

- لأن الـ Dashboard ليس فقط create surface، بل هو أيضاً display surface ضمن نفس المهمة

### كيف يخدم المهمة

- أي موعد ينشأ من اللوحة اليومية سيُخزن كـ `internal`
- والـ timeline سيتمكن من إظهاره بصرياً لاحقاً

---

## Step 8 — ختم creation path في `Filament Admin` كمصدر `internal`

### الملف الذي سنعدله

- `app/Filament/Resources/Appointments/Pages/CreateAppointment.php`

### لماذا هذا الملف مهم جداً

- لأنه bypasses `BookingService`
- ويحفظ `Appointment` مباشرة داخل `handleRecordCreation()`
- ولو تجاهلناه سيبقى جزء من المواعيد الداخلية بلا source حتى بعد بقية التعديلات

### التعديل المقترح

- قبل `Appointment::create($data)` نحقن:
  - `'booking_source' => BookingSource::INTERNAL->value`

### أين بالضبط أفضل مكان لوضعه

أفضل موضع عملي:

- داخل `mutateFormDataBeforeCreate()` أو مباشرة قبل `Appointment::create($data)` في `handleRecordCreation()`

الاختيار الموصى به:

- حقنه في `mutateFormDataBeforeCreate()` ليصبح جزءاً من payload النهائي مبكراً

### لماذا لا نضيفه إلى `AppointmentForm.php`؟

- لأن هذا field يجب أن يبقى **system-owned**
- ولا نريد admin/operator يغير source يدوياً من form

### كيف يخدم المهمة

- كل manual appointment يُنشأ من `Filament Admin` سيُوسم كـ `internal`

---

## Step 9 — تصحيح وعرض source داخل `Filament` table/infolist

### الملفات التي سنعدّلها

- `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php`
- `app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php`

### أولاً: `AppointmentsTable.php`

#### التعديلات المطلوبة

1. إضافة column جديد مثلاً بعد `payment_status` أو قرب metadata الأعمدة:
   - `booking_source`
2. عرضها كـ `badge`
3. mapping مقترح:
   - `online_api` -> badge أزرق أو indigo
   - `internal` -> badge slate/amber بحسب الـ design language
   - `null` -> `Unknown` badge رمادي
4. إضافة filter جديد للمصدر

#### ملاحظة مهمة حول filter

لأن لدينا legacy rows بقيمة `null`، فالفلترة يجب أن تدعم ثلاث حالات فعلياً:

- `online_api`
- `internal`
- `unknown` (`whereNull('booking_source')`)

لذلك قد نحتاج:

- `SelectFilter` مخصص مع query callback
أو
- filter مستقل لـ `Unknown`

### ثانياً: `AppointmentInfolist.php`

#### المشكلة الحالية

- هناك row بعنوان `Created Via` مربوط إلى `created_status`
- وهذا misleading

#### التعديل الموصى به

1. **إضافة row جديدة حقيقية** للمصدر:
   - `booking_source`
2. عدم سرقة المعنى من `created_status`
3. إما:
   - الإبقاء على `created_status` لكن تحت label صحيح مثل `Confirmed`
   - أو إخفاؤه إذا لم يعد مطلوباً عملياً

#### التوصية الأفضل

- الإبقاء على `created_status` كمعلومة confirmation إن كانت مفيدة
- وإضافة `booking_source` كحقل مستقل بعنوان `Created Via`

### كيف يخدم هذا الـ step المهمة

- يعطي الإدارة مكاناً واضحاً وموثوقاً لمعرفة هل الموعد جاء من التطبيق أم من الداخل
- ويصلح التسمية الخاطئة الحالية بدل البناء عليها

---

## Step 10 — عرض source داخل `Staff Dashboard` timeline وmodal

### الملفات التي سنعدّلها

- `app/Livewire/StaffDashboard.php`
- `resources/views/livewire/staff-dashboard.blade.php`
- `lang/ar/dashboard.php`
- `lang/en/dashboard.php`
- `lang/de/dashboard.php`

### أولاً: `StaffDashboard.php`

كما ذُكر سابقاً، سنضيف `booking_source` وlabel مناسب داخل array الخاص بكل appointment في `getTimelineDataFromProviders()`.

### ثانياً: `staff-dashboard.blade.php` — timeline card

#### المكان الحالي

بطاقة الموعد داخل الـ timeline تعرض حالياً:

- الوقت
- الخدمات
- اسم العميل
- رقم الحجز

#### التعديل المقترح

إضافة source badge خفيفة داخل card، مع مراعاة ضيق المساحة.

التوصية العملية:

1. إضافة badge صغيرة تحت سطر الوقت أو بجانبه
2. إظهارها فقط عند وجود مساحة رأسية كافية باستخدام نفس منطق `blockVisible(...)`
3. إذا كانت البطاقة قصيرة جداً:
   - لا نكسر layout
   - ويبقى source ظاهراً بوضوح داخل الـ modal

#### mapping بصري مقترح

- `online_api` -> badge بلون بارد مثل blue/indigo مع label مثل `API` أو `Online`
- `internal` -> badge بلون محايد/دافئ مثل slate/amber مع label مثل `Internal`
- `null` -> badge رمادي `Unknown`

### ثالثاً: `staff-dashboard.blade.php` — appointment modal

#### المكان الحالي

المودال يعرض حالياً:

- booking number
- customer
- service
- provider
- status
- payment status

#### التعديل المقترح

- إضافة row جديدة باسم `Booking Source`
- تُعرض كـ badge مثل status/payment status
- مكانها المنطقي:
  - بعد `provider`
  - وقبل `status`

### لماذا هذا مهم

- لأن المطلوب ليس التخزين فقط، بل تمكين الطاقم من معرفة origin مباشرة أثناء التشغيل اليومي

### كيف يخدم المهمة

- يعطي فريق العمل تمييزاً بصرياً فورياً بين مواعيد التطبيق ومواعيد الإنشاء الداخلي

---

## Step 11 — إضافة مفاتيح ترجمة جديدة

### الملفات التي سنعدّلها

- `lang/ar/resources.php`
- `lang/en/resources.php`
- `lang/de/resources.php`
- `lang/ar/dashboard.php`
- `lang/en/dashboard.php`
- `lang/de/dashboard.php`

### المفاتيح المقترحة في `resources.php`

- `booking_source`
- `filter_booking_source`
- `booking_source_online_api`
- `booking_source_internal`
- `booking_source_unknown`

### المفاتيح المقترحة في `dashboard.php`

- `appointment_modal.booking_source`
- `timeline.booking_source_online_api`
- `timeline.booking_source_internal`
- `timeline.booking_source_unknown`

### لماذا هذا مهم

- لأن السطوح المطلوبة (`Filament` و`Dashboard`) متعددة اللغات
- ولأن source badges يجب أن تكون مفهومة بصرياً ولغوياً

### كيف يخدم المهمة

- يجعل الإظهار النهائي coherent مع بقية النظام متعدد اللغات

---

## Step 12 — تحديث الـ seed data لتوليد حالات واضحة أثناء التطوير

### الملف الذي سنعدله

- `database/seeders/AppointmentSeeder.php`

### التعديل المطلوب

- إضافة `booking_source` صريح لكل appointment seed جديد
- توزيع seed data بين:
  - `online_api`
  - `internal`

### لماذا هذا مهم

- حتى لا تصبح كل بيانات dev/testing `null`
- وحتى نستطيع اختبار:
  - filters في `Filament`
  - badges في `Dashboard`

### ملاحظة مهمة

- لا نشتق source من `payment_method` داخل seeder
- بل نضعه صراحةً في dataset نفسها

---

## Step 13 — تحديث التوثيق بعد اكتمال التنفيذ

### الملفات التي سنعدّلها

- `docs/BOOKING_FLOW.md`
- `docs/STAFF_DASHBOARD.md`
- optional: `Agent.md`

### ما الذي سنوثقه

#### في `BOOKING_FLOW.md`

- أن `POST /api/bookings` يختم المواعيد الجديدة بـ `booking_source = online_api`
- وأن `booking_source` server-side metadata وليست request field

#### في `STAFF_DASHBOARD.md`

- أن bookings المنشأة من dashboard تُخزن كـ `internal`
- وأن timeline data contract صار يتضمن source information

#### في `Agent.md` (اختياري لكن مفيد)

- إضافة ملاحظة high-level أن `Appointment` صار يحتوي `booking_source` مستقل عن `payment_method` و`created_status`

### لماذا هذا مهم

- لأن هذه المهمة تمس domain concept جديد نسبياً
- وتوثيقه يقلل احتمالات أن يعيد أحد لاحقاً استخدام `payment_method` كبديل للمصدر

---

## Step 14 — إضافة tests تغطي كل creation path فعلي

### ملفات الاختبار التي سنضيفها

أسماء مقترحة، ويمكن تعديلها بحسب أسلوب التسمية النهائي في المشروع:

- `tests/Feature/Api/ApiBookingSourceTest.php`
- `tests/Feature/Livewire/StaffDashboardBookingSourceTest.php`
- `tests/Feature/Filament/CreateAppointmentBookingSourceTest.php`
- optional: `tests/Unit/Models/AppointmentBookingSourceTest.php`

### التغطيات المطلوبة

#### 1. API path

- إنشاء booking عبر `POST /api/bookings`
- التأكد أن الموعد الناتج يحمل `booking_source = online_api`

#### 2. Staff Dashboard path

- إنشاء booking عبر `saveBookingFromAlpine()` أو flow مكافئ
- التأكد أن الموعد الناتج يحمل `booking_source = internal`

#### 3. Filament create path

- إنشاء appointment من create page
- التأكد أن السجل الناتج يحمل `booking_source = internal`

#### 4. Legacy data rendering

- record بدون `booking_source`
- يجب أن يظهر كـ `Unknown` في `Filament`
- ولا يكسر `Dashboard` rendering

#### 5. Defensive service test

- استدعاء `BookingService::createBooking()` بدون `booking_source`
- يجب أن يفشل failure واضحاً

### لماذا هذا مهم

- لأن لدينا أكثر من creation path
- ولأن جزءاً من المهمة هو منع regression وليس مجرد تخزين الحقل في مسار واحد

---

## 7. ملفات لن نعدلها في المرحلة الأولى عمداً

### `app/Http/Requests/Api/BookingCreateRequest.php`

لن نضيف له `booking_source` لأن هذا field يجب ألا يكون client-controlled.

### `app/Http/Resources/AppointmentResource.php`

بحسب قرارات النطاق الحالية، لن نعرّض `booking_source` في customer-facing API response في هذه المرحلة.

### `app/Services/BookingValidationService.php`

لا يوجد سبب لتعديل validation logic بسبب source، لأن المصدر لا يغيّر availability أو conflict rules حالياً.

### `app/Services/DashboardService.php`

في الغالب لا يحتاج تعديل في query layer نفسها لهذه المهمة، لأن `StaffDashboard` يمكنه تمرير source للواجهة من نفس model المحمّل حالياً.

### `app/Services/BookingService2.php`

يبدو كمسار legacy وغير مربوط حالياً. لا نوسّع المهمة لتعديله إلا إذا ظهر استخدام فعلي أثناء التنفيذ.

### `app/Services/Appointments/AppointmentCreationService.php`

يوجد كخدمة لكن المسار النشط في `Filament` الحالي لا يمر بها. لن نحوّل المسار إليها في هذه المرحلة حتى لا نوسع scope بلا داع.

---

## 8. ترتيب التنفيذ الموصى به

الترتيب الصحيح لتقليل المخاطر:

1. إضافة migration
2. إضافة `BookingSource` enum
3. تحديث `Appointment` model
4. تحديث `BookingService` ليفرض source
5. تحديث callers:
   - `Api\BookingController`
   - `BookingController` legacy
   - `StaffDashboard`
   - `CreateAppointment`
6. تحديث `Filament` display/filter
7. تحديث `Dashboard` display
8. تحديث translations
9. تحديث seeders
10. إضافة/تشغيل الاختبارات
11. تحديث docs

سبب هذا الترتيب:

- نبدأ من schema/domain
- ثم نضمن persistence correctness
- ثم ننتقل إلى surfaces التي تعرض البيانات

---

## 9. مخاطر التنفيذ ونقاط الانتباه

### الخطر 1 — خلط source مع payment

إذا جرى استنتاج `booking_source` من `payment_method` أو `payment_status` فسنكرر الخطأ المفاهيمي نفسه بشكل جديد.

**المعالجة:**

- source يُختم عند creation boundary فقط

### الخطر 2 — نسيان مسار `Filament`

إذا تم تحديث `BookingService` فقط، سيظل `CreateAppointment` خارج التغطية.

**المعالجة:**

- تعديل `CreateAppointment.php` صراحةً

### الخطر 3 — كسر path غير نشط لاحقاً

إذا فُرض `booking_source` داخل `BookingService` وتركنا `app/Http/Controllers/BookingController.php` كما هو، قد ينكسر مسار legacy إذا استُخدم مستقبلاً.

**المعالجة:**

- تحديثه بشكل defensive

### الخطر 4 — legacy rows بدون source

إذا لم نضع fallback واضحاً، قد تظهر badges فارغة أو أخطاء rendering في `Filament` و`Dashboard`.

**المعالجة:**

- `null` يجب أن يعرض `Unknown`

### الخطر 5 — استخدام label `Created Via` الحالية بشكل خاطئ

إذا تم فقط تغيير label من غير إصلاح field binding، ستبقى الواجهة misleading.

**المعالجة:**

- ربط `Created Via` فعلياً بـ `booking_source`
- وعدم استخدام `created_status` كبديل

---

## 10. معايير القبول النهائية بعد التنفيذ

تعتبر المهمة مكتملة عندما تتحقق الشروط التالية:

1. أي booking جديد عبر `POST /api/bookings` يُحفظ بقيمة:
   - `booking_source = online_api`

2. أي booking جديد عبر `Staff Dashboard` يُحفظ بقيمة:
   - `booking_source = internal`

3. أي appointment جديد عبر `Filament CreateAppointment` يُحفظ بقيمة:
   - `booking_source = internal`

4. المواعيد القديمة تبقى `null` ولا تُعدّل آلياً

5. `Filament appointments table` يعرض source بوضوح ويتيح الفلترة عليه

6. `Filament appointment infolist` يعرض `Created Via` الحقيقي من `booking_source`

7. `Staff Dashboard timeline` يظهر source badge بدون كسر layout

8. `Staff Dashboard appointment modal` يعرض `Booking Source` بوضوح

9. `created_status` يبقى confirmation flag فقط ولا يُعاد تعريفه كمصدر

10. لا يتم تمرير `booking_source` من client request body في الـ API

---

## 11. التوصية النهائية

التنفيذ الأنظف لهذه المهمة ليس في تعديل الـ UI فقط، بل في إدخال domain concept جديد واضح اسمه `booking_source`، ثم ختمه صراحةً في كل creation boundary، وبعدها عرضه في `Filament` و`Staff Dashboard`.

هذا هو الحل الذي:

- يحل المشكلة من الجذر
- لا يخلط بين source وبين payment أو confirmation
- يحافظ على legacy data بدون تخمينات
- ويبقي الباب مفتوحاً لتوسعة مستقبلية إذا تقرر لاحقاً فصل `Staff Dashboard` عن `Filament Admin` كمصدرين مستقلين
