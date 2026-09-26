# DASH-01 — حلاق يظهر "متاحاً" في الداشبورد لكن لا يمكن حجز موعد معه

> **التاريخ:** 25 سبتمبر 2026
> **المصدر:** بلاغ من مدير الصالون عن الحلاقة **Layla Ibrahim** وخدمة **Men's Haircut**
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بالاختبارات وعلى بيانات حقيقية** (التحقق البصري في المتصفح لم يُنفَّذ بعد، انظر القسم 8)
> **الاختبارات:** 11 اختباراً جديداً تمر (50 assertion) · المجموعة الكاملة: 506 نجاح و12 فشلاً، والـ 12 كلها موجودة قبل هذا التعديل

---

## 1. البلاغ كما وصل

> "بعض الحلاقين يظهرون على الداشبورد كحلاقين متاحين لكن لا يمكن حجز موعد معهم."

- المزود: **Layla Ibrahim**.
- تأكد المدير أن المزود **يقدّم الخدمة** المراد حجزها.
- تأكد المدير أن المزود **ليس لديه إجازة** في ذلك اليوم.
- عند اختيار خدمة **Men's Haircut** في نافذة الحجز، لا تظهر Layla في قائمة الحلاقين.
- ملاحظة المدير: في صفحة الخدمة، بجانب Layla **نقطة رمادية**، وبجانب الباقين نقطة خضراء.

---

## 2. التحليل: هل المشكلة موجودة فعلاً في الكود؟

### 2.1 معنى النقطة الرمادية

كل ربط بين حلاق وخدمة هو سطر في جدول `provider_service`، وفي هذا السطر حقل اسمه `is_active`. صفحة عرض الخدمة ترسم النقطة من هذا الحقل مباشرة ([`ServiceInfolist.php:158`](../../app/Filament/Resources/Services/Schemas/ServiceInfolist.php#L158)):

```php
$isActive = $provider->pivot->is_active;
$statusDot = $isActive
    ? '<span style="... background: #22c55e; ..."></span>'   // أخضر
    : '<span style="... background: #94a3b8; ..."></span>';  // رمادي
```

- **نقطة خضراء:** الربط مفعّل، والحلاق يقدّم الخدمة.
- **نقطة رمادية:** الحلاق مسجّل على الخدمة، لكن **الربط موقوف**، أي أنه لا يقدّمها حالياً.

إذن ملاحظة المدير هي نفسها سبب المشكلة.

### 2.2 نافذة الحجز كانت تتصرف كما صُمّمت

عند اختيار خدمة ووقت في نافذة الحجز، تُستدعى `getAvailableProvidersForServiceAtTime()`، وكانت تبدأ هكذا:

```php
$providers = $service->activeProviders()->get();
```

و`activeProviders()` في [`Service.php:86`](../../app/Models/Service.php#L86) تأخذ فقط من ربطه مفعّل:

```php
public function activeProviders()
{
    return $this->providers()
        ->wherePivot('is_active', true)
        ->where('users.is_active', true);
}
```

حتى لو تجاوز أحد الواجهة، التحقق عند الحفظ يرفض الربط الموقوف أيضاً ([`BookingValidationService.php:93`](../../app/Services/BookingValidationService.php#L93)):

```php
$offers = DB::table('provider_service')
    ->where('provider_id', $provider->id)
    ->where('service_id', $service->id)
    ->where('is_active', true)
    ->exists();
```

**النتيجة:** النقطة الرمادية، ونافذة الحجز، والتحقق عند الحفظ، الثلاثة متفقة. إخفاء Layla كان تصرفاً صحيحاً حسب البيانات.

### 2.3 لماذا تظهر "متاحة" في الداشبورد؟

الشريط الجانبي في الداشبورد يُبنى من `getProvidersWithStatus()`، وهذه الدالة تجيب عن سؤال واحد: **هل يعمل الحلاق اليوم؟** تنظر إلى جدول الدوام وإجازة اليوم الكامل فقط، ولا تعرف شيئاً عن الخدمات. لذلك تظهر Layla متاحة لأنها تداوم، وهذا لا يعني أنها تقدّم كل الخدمات.

### 2.4 إعادة إنتاج المشكلة على البيانات المحلية (Laravel Boost)

| الفحص | النتيجة |
|---|---|
| Layla في جدول `users` | `id = 9`، `is_active = 1` |
| ربطها بـ Men's Haircut | **مفعّل** (`is_active = 1`) |
| ربطها بـ Haircut & Beard Combo | **موقوف** (`is_active = 0`) |
| ربطها بـ Beard Trim & Shape | **موقوف** (`is_active = 0`) |
| جدول دوامها | كل الأيام، من 09:00 إلى 18:00، والجمعة والسبت من 10:00 إلى 19:00 |
| إجازاتها | إجازة ساعية واحدة يوم 2026-09-18 من 11:00 إلى 14:00 |

شغّلنا الدالة الحقيقية على عدة أيام وأوقات. Layla تظهر لخدمة Men's Haircut في كل الأوقات العادية، إلا في حالتين:
- يوم 2026-09-18 بين 11:00 و14:00، بسبب إجازتها الساعية.
- الجمعة والسبت قبل 10:00، لأن دوامها يبدأ الساعة 10:00.

**الاستنتاج:** المشكلة لم تظهر محلياً لخدمة Men's Haircut. هذا يؤكد أن المدير جرّب على Production، وأن الربط هناك موقوف لأن النقطة رمادية عنده. وقد أكّد صاحب المشروع أن التجربة كانت على Production.

### 2.5 أين الخطأ الحقيقي في الكود إذن؟

المدير كتب "تم التأكد أن المزود يقدّم الخدمة". التأكد كان من صفحة الحلاق، و**صفحة الحلاق كانت تعرض بيانات خاطئة**. هذا هو الخطأ الأساسي. وأثناء التحليل ظهرت ثلاثة أخطاء أخرى في الداشبورد:

| # | الخطأ | أثره |
|---|---|---|
| 1 | صفحة الحلاق تعرض **كل** الخدمات المربوطة كأنها مقدَّمة، حتى الموقوفة. عمود "Status" يقرأ حالة الخدمة وليس حالة الربط. العدّادات تعدّ الموقوف | الإدارة تظن أن الحلاق يقدّم خدمة لا يمكن حجزها معه، **وهذا ما حدث في البلاغ** |
| 2 | نافذة الحجز **تُخفي** الحلاق غير المتاح بدون أي تفسير | الموظف لا يعرف لماذا اختفى الحلاق، فيرفع بلاغاً |
| 3 | منطق الإجازات في الداشبورد **نسخة قديمة** تختلف عن التحقق (BOOK-04) | حلاق في إجازة يظهر متاحاً، ثم يُرفض الحجز عند الحفظ |
| 4 | الداشبورد يعتبر موعد **No Show** حجزاً قائماً، والتحقق لا يعتبره كذلك | حلاق متاح فعلاً يختفي من النافذة |

الخطآن 3 و4 سببهما واحد: `DashboardService` كان يحمل **نسخته الخاصة** من قواعد الإجازات والتعارض، والنسخة الرسمية في `BookingValidationService` أُصلحت سابقاً بينما بقيت نسخة الداشبورد كما هي.

---

## 3. القرارات المتفق عليها قبل التعديل

| السؤال | القرار |
|---|---|
| أين جرّب المدير؟ | على **Production** |
| كيف تُعرض الخدمات في صفحة الحلاق؟ | **كل الخدمات مع حالة كل ربط**: الموقوفة بلون رمادي وبعلامة واضحة، والعدّادات تعدّ المفعّل فقط |
| ماذا تفعل نافذة الحجز بالحلاق غير المتاح؟ | **تعرضه باهتاً مع السبب** بدل إخفائه |
| هل نصلح منطق الإجازات؟ | **نعم، في الأماكن الثلاثة**: نافذة الحجز، والشريط الجانبي، والجدول الزمني |

---

## 4. التعديلات بالتفصيل

### 4.1 تفعيل أعمدة الربط في علاقة `User::services()` · [`app/Models/User.php`](../../app/Models/User.php#L233-L238)

**قبل:**
```php
public function services(): BelongsToMany
{
    return $this->belongsToMany(Service::class, 'provider_service', 'provider_id', 'service_id')
        // ->withPivot('is_active', 'custom_price', 'custom_duration', 'notes')
        ->withTimestamps();
}
```

**بعد:**
```php
public function services(): BelongsToMany
{
    return $this->belongsToMany(Service::class, 'provider_service', 'provider_id', 'service_id')
        ->withPivot('is_active', 'custom_price', 'custom_duration', 'notes')
        ->withTimestamps();
}
```

**شرح الكود:** في علاقة `belongsToMany`، لا يحمّل Laravel من الجدول الوسيط إلا مفاتيح الربط والـ timestamps. أي عمود آخر يجب ذكره في `withPivot()` ليصبح متاحاً عبر `$service->pivot->is_active`. السطر كان معلَّقاً (commented out)، فكان `$service->pivot->is_active` يعطي `null` دائماً من جهة الحلاق.

**لماذا:** كل التعديلات من 4.2 إلى 4.6 تحتاج أن تعرف من جهة الحلاق هل ربطه بالخدمة مفعّل أم لا. بدون هذا السطر لا يمكن ذلك.

**كيف يخدم المهمة:** هو الأساس الذي يسمح لصفحة الحلاق بأن "ترى" النقطة الرمادية نفسها التي تراها صفحة الخدمة. العلاقة المقابلة `Service::providers()` كانت تحمل `withPivot` أصلاً، ولهذا كانت صفحة الخدمة صحيحة وصفحة الحلاق خاطئة.

**الأثر الجانبي:** إضافة أعمدة للقراءة فقط. لا يتغير شكل أي استعلام موجود ولا نتيجته.

---

### 4.2 قائمة "Services Offered" وعدّاد الخدمات في صفحة الحلاق · [`ProviderInfolist.php`](../../app/Filament/Resources/Providers/Schemas/ProviderInfolist.php#L156-L210)

#### أ) القائمة

**قبل:**
```php
->getStateUsing(function ($record) {
    $services = $record->services()
        ->orderBy('services.sort_order')
        ->get()
        ->map(fn ($service) => $service->translated_name)
        ->filter()
        ->values()
        ->toArray();

    return $services ?: [__('resources.provider_resource.no_services')];
})
->listWithLineBreaks()
```

**بعد:**
```php
->getStateUsing(function ($record) {
    // Every linked service is listed, but a link switched off in
    // provider_service is labelled as such: ...
    $services = $record->services()
        ->orderBy('services.sort_order')
        ->get()
        ->filter(fn ($service) => filled($service->translated_name))
        ->map(fn ($service) => $service->pivot->is_active
            ? $service->translated_name
            : $service->translated_name . ' — ' . __('resources.provider_resource.service_link_inactive'))
        ->values()
        ->toArray();

    return $services ?: [__('resources.provider_resource.no_services')];
})
->color(fn (string $state): string => str_ends_with($state, ' — ' . __('resources.provider_resource.service_link_inactive'))
    ? 'gray'
    : 'primary')
->listWithLineBreaks()
```

**شرح الكود:**
- `filter` صار **قبل** `map`، لأن `map` الآن يُلحق نصاً بالاسم، فلن يكون فارغاً أبداً بعدها. الفلترة يجب أن تتم على الاسم الأصلي.
- `map` يفحص `pivot->is_active`: الخدمة المفعّلة تظهر باسمها، والموقوفة تظهر هكذا: `Men's Haircut — موقوفة لهذا المزود`.
- `color()` في Filament يُستدعى لكل عنصر من عناصر الـ badge. إذا انتهى النص بالعلامة يصبح لونه `gray`، وإلا يبقى `primary` وهو اللون الافتراضي السابق.

**لماذا:** اتُّفق على عرض **كل** الخدمات مع حالتها (القسم 3). الإخفاء الكامل كان سيُخفي أيضاً معلومة أن هناك ربطاً موقوفاً يمكن تفعيله.

**كيف يخدم المهمة:** هذا القسم هو المكان الذي "تأكد" منه المدير أن Layla تقدّم الخدمة. الآن سيرى أمام الخدمة بلون رمادي "موقوفة لهذا المزود"، فيعرف السبب مباشرة دون الحاجة لبلاغ.

#### ب) عدّاد الخدمات في قسم الإحصائيات

**قبل:**
```php
->getStateUsing(fn ($record) => $record->services()->count()),
```

**بعد:**
```php
->getStateUsing(fn ($record) => $record->services()->wherePivot('is_active', true)->count()),
```

**شرح الكود:** `wherePivot` يضيف شرطاً على عمود في الجدول الوسيط `provider_service`، فيُعدّ فقط الربط المفعّل.

**لماذا وكيف يخدم المهمة:** العدّاد عنوانه "الخدمات المقدمة". كان لـ Layla يقول 5 وهي تقدّم فعلياً 3. رقم خاطئ يعزّز الانطباع الخاطئ نفسه.

---

### 4.3 بطاقة الإحصائيات أعلى صفحة الحلاق · [`ProviderStatsOverviewWidget.php`](../../app/Filament/Resources/Providers/Widgets/ProviderStatsOverviewWidget.php#L85-L86)

**قبل:**
```php
// Services offered count
$servicesCount = $this->record->services()->count();
```

**بعد:**
```php
// Services offered count — active links only, matching the "active services" caption
$servicesCount = $this->record->services()->wherePivot('is_active', true)->count();
```

**شرح الكود:** نفس شرط 4.2-ب.

**لماذا:** البطاقة نفسها مكتوب تحتها "Active Services" (`resources.provider_resource.active_services`)، بينما كانت تعدّ الموقوف أيضاً. أي أن النص كان يناقض الرقم.

**كيف يخدم المهمة:** أول رقم يراه المدير عند فتح صفحة الحلاق صار صحيحاً.

---

### 4.4 عمود عدد الخدمات في جدول الحلاقين · [`ProvidersTable.php`](../../app/Filament/Resources/Providers/Tables/ProvidersTable.php#L57-L63)

**قبل:**
```php
TextColumn::make('services_count')
    ->label(__('resources.provider_resource.services'))
    ->counts('services')
```

**بعد:**
```php
TextColumn::make('services_count')
    ->label(__('resources.provider_resource.services'))
    // Active links only — a switched-off link cannot be booked.
    ->counts(['services' => fn ($query) => $query->where('provider_service.is_active', true)])
```

**شرح الكود:** `counts()` في Filament يقبل مصفوفة: المفتاح اسم العلاقة، والقيمة closure تُضيّق الاستعلام. Filament يحوّلها إلى `withCount(...)`، ويبقى اسم العمود الناتج `services_count` كما هو، فلا يتأثر الترتيب (`sortable`). استعملنا `where('provider_service.is_active', ...)` باسم الجدول صراحة، لأن داخل `withCount` يُبنى استعلام فرعي ويجب تجنّب الالتباس مع `services.is_active`.

**لماذا وكيف يخدم المهمة:** نفس منطق 4.2 و4.3: كل رقم "خدمات" في صفحات الحلاق يعني الآن "خدمات يمكن حجزها معه".

---

### 4.5 صفحة المستخدم العامة (UserResource) · [`UserInfolist.php`](../../app/Filament/Resources/Users/Schemas/UserInfolist.php)

الحلاق مستخدم أيضاً، ويمكن فتحه من شاشة Users. هذه الشاشة كان فيها نفس الخطأين.

#### أ) قائمة الخدمات

**قبل:**
```php
->getStateUsing(function ($record) {
    $services = $record->services()
        ->pluck('services.name')
        ->toArray();

    return $services ?: [__('resources.user.no_services')];
})
```

**بعد:**
```php
->getStateUsing(function ($record) {
    // A link switched off in provider_service cannot be booked,
    // so it is labelled instead of listed as a plain offer.
    $services = $record->services()
        ->get()
        ->map(fn ($service) => $service->pivot->is_active
            ? $service->name
            : $service->name . ' — ' . __('resources.provider_resource.service_link_inactive'))
        ->toArray();

    return $services ?: [__('resources.user.no_services')];
})
->color(fn (string $state): string => str_ends_with($state, ' — ' . __('resources.provider_resource.service_link_inactive'))
    ? 'gray'
    : 'primary')
```

**شرح الكود:** `pluck('services.name')` يجلب عموداً واحداً ولا يحمّل الـ pivot، لذلك استبدلناه بـ `get()` ثم `map`، بنفس فكرة 4.2-أ. أبقينا `name` كما كان (وليس `translated_name`) حتى لا نغيّر أي شيء غير المطلوب في هذه الشاشة.

#### ب) العدّاد

**قبل:** `$record->services()->count()`
**بعد:** `$record->services()->wherePivot('is_active', true)->count()`

**لماذا وكيف يخدم المهمة:** لو أصلحنا صفحة Providers وحدها، سيبقى المدير يرى المعلومة الخاطئة إذا فتح الحلاق من شاشة Users. يجب أن تقول الشاشتان نفس الشيء.

---

### 4.6 عمود "Status" في تبويب خدمات المستخدم · [`Users/RelationManagers/ServicesRelationManager.php`](../../app/Filament/Resources/Users/RelationManagers/ServicesRelationManager.php#L214-L222)

**قبل:**
```php
TextColumn::make('is_active')
    ->label(__('resources.user.status'))
    ->badge()
    ->formatStateUsing(fn ($state) => $state ? __('resources.user.active') : __('resources.user.inactive'))
```

**بعد:**
```php
// The provider's link to the service, not the service itself:
// a globally active service can still be switched off for this
// provider, and then it cannot be booked with them.
TextColumn::make('pivot.is_active')
    ->label(__('resources.user.status'))
    ->badge()
    ->formatStateUsing(fn ($state) => $state ? __('resources.user.active') : __('resources.user.inactive'))
```

**شرح الكود:** هذا جدول خدمات **حلاق معيّن**. الاسم `is_active` بدون بادئة يقرأ عمود `services.is_active`، أي هل الخدمة مفعّلة **في الصالون عموماً**. أما `pivot.is_active` فيقرأ `provider_service.is_active`، أي هل الخدمة مفعّلة **لهذا الحلاق**. التنسيق والألوان بقيت كما هي.

**لماذا:** Men's Haircut مفعّلة في الصالون، لذلك كان العمود يقول "Active" (أخضر) أمامها في صفحة Layla، حتى لو كان ربطها بها موقوفاً. هذا خطأ مباشر يطابق ما رآه المدير تماماً.

**كيف يخدم المهمة:** العمود الذي عنوانه "Status" في صفحة الحلاق صار يعرض الحالة التي تحدد فعلاً إمكانية الحجز.

---

### 4.7 قلب التعديل: نافذة الحجز تعرض كل الحلاقين مع السبب · [`DashboardService.php`](../../app/Services/DashboardService.php#L235-L339)

هذا أكبر تعديل. قسمنا الدالة القديمة إلى ثلاث دوال.

#### قبل (دالة واحدة تُسقط غير المتاح بصمت عبر `continue`)

```php
public function getAvailableProvidersForServiceAtTime(int $serviceId, string $date, string $startTime, int $duration, bool $bypassAvailability = false): array {
    $service = Service::find($serviceId);
    if (!$service) return [];

    $providers = $service->activeProviders()->get();          // ← الربط الموقوف يُستبعد هنا بصمت
    $carbonDate = Carbon::parse($date);
    $slotStart = Carbon::parse($date . ' ' . $startTime);
    $slotEnd = $slotStart->copy()->addMinutes($duration);
    $availableProviders = [];

    foreach ($providers as $provider) {
        if (! $bypassAvailability) {
            $schedule = ProviderScheduledWork::where('user_id', $provider->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_work_day', true)
                ->where('is_active', true)
                ->first();
            if (!$schedule) continue;                          // ← بصمت

            // ... فحص ساعات الدوام
            if ($slotStart->lt($scheduleStart) || $slotEnd->gt($scheduleEnd)) continue;

            $hasFullDayOff = ProviderTimeOff::where('user_id', $provider->id)
                ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)               // ← لا يرى end_date = NULL
                ->exists();
            if ($hasFullDayOff) continue;

            $hasHourlyConflict = ProviderTimeOff::where('user_id', $provider->id)
                ->where('type', ProviderTimeOff::TYPE_HOURLY)
                ->whereDate('start_date', $date)               // ← يرى اليوم الأول فقط
                ->where('start_time', '<', $slotEnd->format('H:i:s'))
                ->where('end_time', '>', $slotStart->format('H:i:s'))
                ->exists();
            if ($hasHourlyConflict) continue;
        }

        $hasAppointmentConflict = Appointment::where('provider_id', $provider->id)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->whereNotIn('status', [                           // ← No Show يُعتبر حجزاً قائماً
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
            ])
            ->where(function ($q) use ($slotStart, $slotEnd) {
                $q->where('start_time', '<', $slotEnd)
                    ->where('end_time', '>', $slotStart);
            })
            ->exists();
        if ($hasAppointmentConflict) continue;

        $availableProviders[] = ['id' => ..., 'first_name' => ..., 'last_name' => ..., 'name' => ...];
    }

    return $availableProviders;
}
```

الأسطر المعلَّمة بـ ← هي بالضبط الأخطاء 2 و3 و4 في جدول القسم 2.5.

#### بعد — الدالة الجديدة `getProviderAvailabilityForServiceAtTime()`

```php
public function getProviderAvailabilityForServiceAtTime(int $serviceId, string $date, string $startTime, int $duration, bool $bypassAvailability = false): array {
    $service = Service::find($serviceId);
    if (!$service) return [];

    $providers = $service->providers()
        ->where('users.is_active', true)
        ->orderBy('first_name')
        ->get();
    $slotStart = Carbon::parse($date . ' ' . $startTime);
    $slotEnd = $slotStart->copy()->addMinutes($duration);

    $available = [];
    $unavailable = [];

    foreach ($providers as $provider) {
        $reason = $this->unavailabilityReason($provider, $date, $slotStart, $slotEnd, $bypassAvailability);

        $row = [
            'id' => $provider->id,
            'first_name' => $provider->first_name,
            'last_name' => $provider->last_name,
            'name' => $provider->full_name,
            'available' => $reason === null,
            'reason' => $reason,
            'reason_label' => $reason ? __('dashboard.booking_modal.unavailable_reason.' . $reason) : null,
        ];

        if ($reason === null) {
            $available[] = $row;
        } else {
            $unavailable[] = $row;
        }
    }

    return array_merge($available, $unavailable);
}
```

**شرح الكود:**
- **من يُعرض؟** `providers()` بدل `activeProviders()`، أي كل حلاق **مربوط** بالخدمة حتى لو كان الربط موقوفاً، مع إبقاء شرط أن حساب المستخدم فعّال (`users.is_active`). الحلاق غير المربوط بالخدمة أصلاً، والحساب المعطّل، **لا يُعرضان** حتى لا تمتلئ القائمة بأسماء لا علاقة لها بالخدمة.
- **لكل حلاق صف** فيه الحقول القديمة نفسها (`id`, `first_name`, `last_name`, `name`) وثلاثة حقول جديدة:
  - `available`: هل يمكن اختياره؟
  - `reason`: رمز السبب الثابت (للكود والاختبارات).
  - `reason_label`: النص المترجم الذي يراه الموظف، ويُترجم على السيرفر حسب لغة المستخدم.
- **الترتيب:** المتاحون أولاً ثم غير المتاحين، وكل مجموعة مرتبة بالاسم الأول (`orderBy('first_name')`)، فلا يضطر الموظف للبحث عن المتاحين بين الباهتين.

#### بعد — الدالة الخاصة `unavailabilityReason()`

```php
private function unavailabilityReason(User $provider, string $date, Carbon $slotStart, Carbon $slotEnd, bool $bypassAvailability): ?string {
    // A link switched off in provider_service: the provider is listed on the
    // service (grey dot) but does not offer it. Force booking never overrides this.
    if (! $provider->pivot->is_active) {
        return 'service_disabled';
    }

    if (! $bypassAvailability) {
        $schedule = ProviderScheduledWork::where('user_id', $provider->id)
            ->where('day_of_week', Carbon::parse($date)->dayOfWeek)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->first();

        if (! $schedule) {
            return 'not_working';
        }

        $scheduleStart = Carbon::parse($date . ' ' . $schedule->start_time);
        $scheduleEnd = Carbon::parse($date . ' ' . $schedule->end_time);
        if ($slotStart->lt($scheduleStart) || $slotEnd->gt($scheduleEnd)) {
            return 'outside_hours';
        }

        $onLeave = ProviderTimeOff::where('user_id', $provider->id)
            ->coveringDate($date)
            ->get()
            ->contains(fn (ProviderTimeOff $timeOff) => $timeOff->blocksWindow($slotStart, $slotEnd));
        if ($onLeave) {
            return 'on_leave';
        }
    }

    $isBusy = Appointment::where('provider_id', $provider->id)
        ->blocksProviderTime()
        ->overlapping($slotStart, $slotEnd)
        ->exists();

    return $isBusy ? 'busy' : null;
}
```

**شرح الكود، سطراً بسطر:**

| الفحص | الرمز | ماذا تغيّر عن القديم ولماذا |
|---|---|---|
| `pivot->is_active` | `service_disabled` | **جديد كفحص صريح.** كان يتم بصمت داخل `activeProviders()`. الآن يعطي سبباً. يأتي **قبل** فحص `bypassAvailability`، لأن force booking يتجاوز نافذة التوفّر فقط، ولا يجعل الحلاق يقدّم خدمة لا يقدّمها (مطابق للتحقق وللقرار الموثّق في force booking) |
| جدول الدوام | `not_working` | نفس المنطق القديم |
| ساعات الدوام | `outside_hours` | نفس المنطق القديم |
| الإجازات | `on_leave` | **تغيّر.** بدل استعلامين مكتوبين يدوياً، صار يستعمل `coveringDate()` و`blocksWindow()` من `ProviderTimeOff`، وهما نفس ما يستعمله `BookingValidationService`. استعلام واحد يغطي النوعين: إجازة اليوم الكامل تغطي اليوم كله فتُرجع `blocksWindow` قيمة true دائماً، والإجازة الساعية تُحسب حسب شريحتها الحقيقية في ذلك اليوم |
| التعارض | `busy` | **تغيّر.** بدل الشرط اليدوي صار يستعمل `blocksProviderTime()` و`overlapping()` من `Appointment`، وهما نفس ما يستعمله التحقق. `blocksProviderTime` يعتبر فقط `PENDING` و`COMPLETED` حجزاً قائماً، فلم يعد No Show يُخفي الحلاق. أُزيل `whereDate('appointment_date')` لأن `start_time` و`end_time` من نوع `datetime` كاملاً، فشرط التداخل يحدد اليوم بنفسه |

**ترتيب الفحوصات مقصود:** أول سبب ينطبق هو الذي يظهر. الربط الموقوف أولاً لأنه الأكثر ثباتاً (يحتاج تدخّل الإدارة)، ثم الدوام، ثم الإجازة، ثم الحجز، وهو الأكثر تغيّراً.

**لماذا نقلنا القواعد إلى الدوال المشتركة؟** الخطآن 3 و4 سببهما أن الداشبورد كان يحمل **نسخة ثانية** من القواعد. كلما أُصلحت النسخة الرسمية بقيت نسخة الداشبورد قديمة. الآن لا توجد نسخة ثانية، فأي إصلاح مستقبلي في `ProviderTimeOff` أو `Appointment` يصل للداشبورد تلقائياً.

#### بعد — الدالة القديمة صارت غلافاً (wrapper)

```php
public function getAvailableProvidersForServiceAtTime(int $serviceId, string $date, string $startTime, int $duration, bool $bypassAvailability = false): array {
    return array_values(array_map(
        fn (array $provider) => array_diff_key($provider, ['available' => true, 'reason' => true, 'reason_label' => true]),
        array_filter(
            $this->getProviderAvailabilityForServiceAtTime($serviceId, $date, $startTime, $duration, $bypassAvailability),
            fn (array $provider) => $provider['available']
        )
    ));
}
```

**شرح الكود:** تأخذ القائمة الكاملة، وتبقي المتاحين فقط (`array_filter`)، وتحذف الحقول الثلاثة الجديدة (`array_diff_key`)، وتعيد ترقيم المصفوفة (`array_values`). النتيجة بنفس شكلها القديم تماماً.

**لماذا أبقيناها؟** الدالة `getAvailableProvidersAtTime()` في `StaffDashboard` ما زالت تستدعيها. إبقاؤها بنفس العقد يضمن ألا ينكسر أي مستدعٍ حالي أو مستقبلي يتوقع "المتاحين فقط". وصارت تستفيد من القواعد المصححة تلقائياً.

**كيف يخدم التعديل كله المهمة:** لو كان هذا الكود موجوداً يوم البلاغ، لرأى المدير في نافذة الحجز: `Layla Ibrahim — الخدمة موقوفة لهذا المزود`، بلون باهت. البلاغ ما كان ليُرفع أصلاً.

---

### 4.8 الشريط الجانبي: من هو في إجازة اليوم؟ · [`DashboardService::getProvidersWithStatus()`](../../app/Services/DashboardService.php#L31-L61)

**قبل:**
```php
$fullDayOffIds = ProviderTimeOff::whereIn('user_id', $providerIds)
    ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
    ->where('start_date', '<=', $date)
    ->where('end_date', '>=', $date)
    ->pluck('user_id')
    ->unique()
    ->toArray();
```

**بعد:**
```php
// Off for the whole day: a full-day leave, or the middle day of a
// multi-day hourly leave. Same range rule as BookingValidationService —
// the old `end_date >= $date` missed null-ended leaves (BOOK-04).
$dayStart = $carbonDate->copy()->startOfDay();
$fullDayOffIds = ProviderTimeOff::whereIn('user_id', $providerIds)
    ->coveringDate($date)
    ->get()
    ->filter(function (ProviderTimeOff $timeOff) use ($dayStart) {
        $window = $timeOff->blockedWindowOn($dayStart);

        return $window !== null
            && $window['start']->lte($dayStart)
            && $window['end']->gte($dayStart->copy()->addDay());
    })
    ->pluck('user_id')
    ->unique()
    ->values()
    ->toArray();
```

**شرح الكود:**
- `coveringDate($date)` يجلب كل إجازة يغطي مداها هذا اليوم. يتعامل مع `end_date = NULL` كإجازة ليوم واحد عبر `COALESCE(end_date, start_date)`.
- `blockedWindowOn($dayStart)` يعطي الشريحة التي تشغلها الإجازة **في هذا اليوم تحديداً** (`start` و`end`).
- الشرط يعتبر الحلاق "في إجازة اليوم" فقط إذا كانت الشريحة تغطي اليوم كله، من منتصف الليل إلى منتصف الليل التالي. هذا ينطبق على:
  - إجازة اليوم الكامل.
  - **اليوم الأوسط** من إجازة ساعية على عدة أيام، لأنها غياب متصل واحد وليست نفس الساعات تتكرر كل يوم.
- إجازة ساعية عادية (مثل 11:00 إلى 14:00) **لا** تجعل الحلاق "في إجازة اليوم"، وهذا صحيح لأنه يعمل باقي اليوم.
- `values()` يعيد ترقيم المصفوفة بعد `unique()`، والنتيجة تُستعمل فقط مع `in_array` فلا يتغير السلوك.

**لماذا:** في SQL قيمة `NULL >= '2026-09-10'` ليست `false` بل `UNKNOWN`، فكانت كل إجازة يوم كامل بدون تاريخ نهاية تُتجاهل. كان الحلاق يظهر في الشريط الجانبي كأنه يعمل، ثم يرفض التحقق أي حجز معه. هذا هو خطأ BOOK-04 نفسه الذي أُصلح في التحقق ولم يُصلح هنا.

**كيف يخدم المهمة:** عنوان البلاغ حرفياً "يظهر متاحاً لكن لا يمكن الحجز". هذا مسار ثانٍ مستقل يُنتج نفس الشكوى، وأغلقناه. وبما أن `isProviderSelectableForTimeline()` تعتمد على `has_day_off`، صار الحلاق في إجازة يُستبعد من أعمدة الجدول الزمني الافتراضية كما يجب.

---

### 4.9 جلب الإجازات لرسمها على الجدول الزمني · [`DashboardService::getTimeOffsForDate()`](../../app/Services/DashboardService.php#L119-L130)

**قبل:**
```php
$query = ProviderTimeOff::with('provider', 'reason')
    ->where(function ($q) use ($date) {
        $q->where(function ($q2) use ($date) {
            $q2->where('type', ProviderTimeOff::TYPE_FULL_DAY)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date);
        })->orWhere(function ($q2) use ($date) {
            $q2->where('type', ProviderTimeOff::TYPE_HOURLY)
                ->whereDate('start_date', $date);
        });
    });
```

**بعد:**
```php
// Callers draw each row with ProviderTimeOff::blockedWindowOn(), so a
// multi-day leave shows the slice it really occupies on $date.
$query = ProviderTimeOff::with('provider', 'reason')
    ->coveringDate($date);
```

**شرح الكود:** شرط واحد مشترك بدل شرطين يدويين. يجلب كل إجازة (يومية أو ساعية) يغطي مداها هذا اليوم. حساب الساعات المرسومة انتقل إلى 4.11.

**لماذا:** الشرطان القديمان فيهما نفس الخطأين: إجازة اليوم الكامل بدون تاريخ نهاية لا تُرسم، والإجازة الساعية على عدة أيام تُرسم في يومها الأول فقط.

**كيف يخدم المهمة:** الجدول الزمني هو ما ينظر إليه الموظف قبل فتح نافذة الحجز. إذا لم تظهر الإجازة عليه، يظن أن الحلاق متاح.

---

### 4.10 الـ Livewire endpoint الذي تستدعيه النافذة · [`StaffDashboard::getAvailableProvidersForBooking()`](../../app/Livewire/StaffDashboard.php#L456-L472)

**قبل:**
```php
$bypassAvailability = $bypassAvailability && $this->dashCan('force_booking');

return $this->dashboardService->getAvailableProvidersForServiceAtTime(
    $serviceId, $this->selectedDate, $startTime, $duration ?: 30, $bypassAvailability
);
```

**بعد:**
```php
$bypassAvailability = $bypassAvailability && $this->dashCan('force_booking');

// Returns unavailable providers too, flagged with a reason, so the modal
// can show them greyed out instead of making them silently disappear.
return $this->dashboardService->getProviderAvailabilityForServiceAtTime(
    $serviceId, $this->selectedDate, $startTime, $duration ?: 30, $bypassAvailability
);
```

**شرح الكود:** هذه الدالة تستدعيها الواجهة عبر `$wire.getAvailableProvidersForBooking(...)`. تغيّر فقط اسم الدالة المستدعاة، فصارت تعيد القائمة الكاملة مع الأسباب. فحص صلاحية `force_booking` بقي كما هو قبل الاستدعاء، فلا يستطيع طلب مزوّر أن يجعل حلاقاً في إجازة يظهر متاحاً.

**لماذا وكيف يخدم المهمة:** هي نقطة الوصل بين 4.7 (السيرفر) و4.12 (الواجهة).

---

### 4.11 رسم الإجازة على الجدول الزمني بالساعات الصحيحة · [`StaffDashboard::getTimelineDataFromProviders()`](../../app/Livewire/StaffDashboard.php#L1564-L1597)

**قبل:**
```php
$timeOffsByProvider = [];
foreach ($timeOffs as $to) {
    $pid = $to->user_id;
    // ...
    $timeOffsByProvider[$pid][] = [
        'id' => $to->id,
        'type' => $to->type,
        'start_time' => $to->type === ProviderTimeOff::TYPE_HOURLY ? ($to->start_time?->format('H:i') ?? '') : $startTime,
        'end_time' => $to->type === ProviderTimeOff::TYPE_HOURLY ? ($to->end_time?->format('H:i') ?? '') : $endTime,
        'reason' => $to->reason?->name ?? '',
    ];
}
```

**بعد:**
```php
// Draw the slice each leave occupies on the selected day: a multi-day
// hourly leave blocks the whole of its middle days, not the same hours
// every day. An edge at midnight is drawn from/to the salon's hours.
$dayStart = Carbon::parse($this->selectedDate)->startOfDay();
$timeOffsByProvider = [];
foreach ($timeOffs as $to) {
    $window = $to->blockedWindowOn($dayStart);
    if ($window === null) {
        continue;
    }

    $pid = $to->user_id;
    // ...
    $timeOffsByProvider[$pid][] = [
        'id' => $to->id,
        'type' => $to->type,
        'start_time' => $window['start']->lte($dayStart) ? $startTime : $window['start']->format('H:i'),
        'end_time' => $window['end']->gte($dayStart->copy()->addDay()) ? $endTime : $window['end']->format('H:i'),
        'reason' => $to->reason?->name ?? '',
    ];
}
```

**شرح الكود:**
- `$startTime` و`$endTime` هنا هما ساعة فتح الصالون وإغلاقه، أي حدود الجدول الزمني.
- `blockedWindowOn()` يعطي الشريحة الحقيقية في اليوم المختار:

| نوع الإجازة | الشريحة | ما يُرسم |
|---|---|---|
| يوم كامل | من منتصف الليل إلى منتصف الليل | من فتح الصالون إلى إغلاقه (كما كان سابقاً) |
| ساعية ليوم واحد | من `start_time` إلى `end_time` | نفس الساعات (كما كان سابقاً) |
| ساعية على عدة أيام، **اليوم الأول** | من `start_time` إلى نهاية اليوم | من `start_time` إلى إغلاق الصالون |
| ساعية على عدة أيام، **يوم أوسط** | اليوم كله | من فتح الصالون إلى إغلاقه |
| ساعية على عدة أيام، **اليوم الأخير** | من بداية اليوم إلى `end_time` | من فتح الصالون إلى `end_time` |

- إذا بدأت الشريحة عند منتصف الليل، نرسم من فتح الصالون. وإذا انتهت عند منتصف الليل التالي، نرسم حتى الإغلاق. هذا يمنع كتابة `00:00` كنهاية، وهي قيمة كانت سترسم الكتلة بالعكس.
- `blockedWindowOn()` تعيد `null` للصفوف المعطوبة (إجازة ساعية بدون أوقات)، فنتخطاها بـ `continue` بدل رسم كتلة فارغة. الكود القديم كان يرسل `''` في هذه الحالة.

**لماذا:** قبل التعديل، إجازة ساعية من الأحد 15:00 إلى الثلاثاء 12:00 كانت تُرسم يوم الأحد فقط، ومن 15:00 إلى 12:00، أي بساعات معكوسة. أما يوم الاثنين فلا يُرسم شيء، مع أن الحلاق غائب طوال اليوم.

**كيف يخدم المهمة:** ما يُرسم على الجدول الزمني صار يطابق ما تعتبره نافذة الحجز والتحقق "إجازة". الموظف يرى نفس الحقيقة في كل مكان.

---

### 4.12 الواجهة: عرض غير المتاحين باهتين مع السبب · [`staff-dashboard.blade.php`](../../resources/views/livewire/staff-dashboard.blade.php#L832-L856)

**قبل:**
```html
<template x-for="p in (bs._availableProviders || [])" :key="p.id">
    <label
        class="flex items-center space-x-2 px-3 py-2 rounded-lg border cursor-pointer hover:bg-amber-50"
        :class="bs.provider_id == p.id ? 'border-amber-500 bg-amber-50' : 'border-gray-200'"
        @click="bs.provider_id = p.id">
        <span ...radio... ></span>
        <span class="text-sm" x-text="p.name"></span>
    </label>
</template>
<template
    x-if="!bs._loadingProviders && (!bs._availableProviders || bs._availableProviders.length === 0)">
    <p class="text-xs text-gray-400">{{ __('dashboard.booking_modal.no_providers') }}</p>
</template>
```

**بعد:**
```html
<template x-for="p in (bs._availableProviders || [])" :key="p.id">
    <label
        class="flex items-center space-x-2 px-3 py-2 rounded-lg border"
        :class="!p.available ? 'border-gray-100 bg-gray-50 opacity-60 cursor-not-allowed' :
            (bs.provider_id == p.id ? 'border-amber-500 bg-amber-50 cursor-pointer' :
            'border-gray-200 cursor-pointer hover:bg-amber-50')"
        :title="p.available ? '' : p.reason_label"
        @click="if (p.available) bs.provider_id = p.id">
        <span ...radio... ></span>
        <span class="text-sm" :class="!p.available && 'text-gray-500'" x-text="p.name"></span>
        <span x-show="!p.available" class="ms-auto text-[11px] text-gray-500"
            x-text="p.reason_label"></span>
    </label>
</template>
<template
    x-if="!bs._loadingProviders && !(bs._availableProviders || []).some(p => p.available)">
    <p class="text-xs text-gray-400">{{ __('dashboard.booking_modal.no_providers') }}</p>
</template>
```

**شرح الكود:**
- **الشكل:** غير المتاح يأخذ خلفية رمادية فاتحة، وشفافية 60%، ومؤشر `cursor-not-allowed`، ولا يتلوّن عند المرور عليه. المتاح بقي بشكله القديم تماماً.
- **السبب:** يظهر نص صغير في طرف السطر (`ms-auto` تعمل في الاتجاهين، العربي RTL والإنجليزي LTR)، ويظهر أيضاً كـ tooltip عبر `title`.
- **منع الاختيار:** `@click="if (p.available) ..."`، فالنقر على غير المتاح لا يفعل شيئاً. التحقق على السيرفر يبقى خط الدفاع الأخير على أي حال.
- **رسالة "لا يوجد مقدمو خدمة متاحون":** كانت تظهر عندما تكون القائمة فارغة. الآن القائمة قد تحتوي غير متاحين فقط، لذلك صار الشرط "لا يوجد أحد **متاح**" (`!...some(p => p.available)`). الرسالة تظهر تحت الأسماء الباهتة، فيفهم الموظف أنه لا يوجد من يمكن اختياره ويرى السبب لكل واحد.
- اسم المتغير `_availableProviders` بقي كما هو رغم أنه صار يحمل غير المتاحين أيضاً. تغيير الاسم كان سيلمس ستة مواضع أخرى في الملف بلا فائدة وظيفية.

**لماذا:** هذا هو القرار الثاني المتفق عليه: "نعرضه باهتاً مع السبب".

**كيف يخدم المهمة:** الشكوى كانت "لا أستطيع إيجاد العامل". الآن العامل موجود دائماً في القائمة إذا كان مربوطاً بالخدمة، ومعه سبب واضح لعدم إمكانية اختياره.

---

### 4.13 الواجهة: لا اختيار تلقائياً لحلاق غير متاح · [`staff-dashboard.blade.php`](../../resources/views/livewire/staff-dashboard.blade.php#L2274)

**قبل:**
```js
if (bs._preselectedProvider && result.some(p => p.id == bs._preselectedProvider)) {
    bs.provider_id = bs._preselectedProvider;
```

**بعد:**
```js
if (bs._preselectedProvider && result.some(p => p.available && p.id == bs._preselectedProvider)) {
    bs.provider_id = bs._preselectedProvider;
```

**شرح الكود:** عند السحب على عمود حلاق في الجدول الزمني لإنشاء حجز، يُحفظ ذلك الحلاق في `_preselectedProvider` ليُختار تلقائياً عندما تصل القائمة. سابقاً كان وجوده في القائمة يعني أنه متاح. الآن القائمة تحتوي غير المتاحين أيضاً، فأضفنا شرط `p.available`.

**لماذا:** بدون هذا الشرط، لو سحب الموظف على عمود Layla واختار خدمة موقوفة لها، ستُختار تلقائياً رغم أنها باهتة، ثم يُرفض الحجز عند الحفظ. أي أننا كنا سنعيد إنتاج نفس الشكوى بطريقة جديدة.

**كيف يخدم المهمة:** يحافظ على الضمان الأساسي: ما يمكن اختياره في النافذة يُقبل عند الحفظ.

---

### 4.14 الترجمات · `lang/{ar,en,de}/dashboard.php` و `lang/{ar,en,de}/resources.php`

**أُضيف في `dashboard.booking_modal`:**

| المفتاح | عربي | English | Deutsch |
|---|---|---|---|
| `unavailable_reason.service_disabled` | الخدمة موقوفة لهذا المزود | Service disabled for this provider | Leistung für diesen Mitarbeiter deaktiviert |
| `unavailable_reason.not_working` | لا يعمل في هذا اليوم | Not working this day | Arbeitet an diesem Tag nicht |
| `unavailable_reason.outside_hours` | خارج ساعات الدوام | Outside working hours | Außerhalb der Arbeitszeit |
| `unavailable_reason.on_leave` | في إجازة | On leave | Im Urlaub |
| `unavailable_reason.busy` | لديه حجز آخر | Has another booking | Hat eine andere Buchung |

**أُضيف في `resources.provider_resource`:**

| المفتاح | عربي | English | Deutsch |
|---|---|---|---|
| `service_link_inactive` | موقوفة لهذا المزود | Disabled for this provider | Für diesen Mitarbeiter deaktiviert |

**لماذا:** الواجهة تعمل بثلاث لغات، والسبب يجب أن يظهر بلغة الموظف. الصالون في برلين، فالألمانية ضرورية.

---

### 4.15 الاختبارات · [`tests/Feature/Booking/DashboardProviderAvailabilityTest.php`](../../tests/Feature/Booking/DashboardProviderAvailabilityTest.php) (ملف جديد)

الاختبارات مبنية على `SalonFixture` الموجود في المشروع. فيه صالون فيه حلاق لكل حالة استبعاد، ومنها `inactivePivot`: حلاق ربطه بالخدمة موقوف، **وهي حالة Layla بالضبط**.

| الاختبار | ماذا يثبت |
|---|---|
| lists a switched-off provider_service link as unavailable with a reason | **حالة البلاغ:** الربط الموقوف يظهر في القائمة بسبب `service_disabled` والنص المترجم، ولا يختفي |
| gives each excluded provider the reason that excludes them | كل حلاق في الـ fixture يأخذ سببه الصحيح: `not_working`، و`on_leave`، و`busy`، و`outside_hours`، والإجازة الساعية تمنع 11:00 ولا تمنع 10:00 |
| never lists providers who are not linked to the service or whose account is disabled | غير المربوط والحساب المعطّل لا يظهران أبداً |
| puts available providers first | الترتيب: المتاحون قبل غير المتاحين |
| keeps `getAvailableProvidersForServiceAtTime` returning only bookable providers in its old shape | الغلاف القديم يعيد المتاحين فقط وبالمفاتيح الأربعة القديمة بالضبط |
| reads a full-day leave with no end date as that single day (BOOK-04) | إجازة بدون تاريخ نهاية تمنع الحجز **وتظهر** في الشريط الجانبي كـ `has_day_off` |
| treats a multi-day hourly leave as one continuous absence | اليوم الأوسط من إجازة ساعية على عدة أيام محجوب كله، في النافذة وفي الشريط الجانبي |
| does not treat a no-show as blocking the chair | موعد No Show لا يُخفي الحلاق |
| lets force booking bypass the availability window but not a disabled link or a clash | force booking يتجاوز الإجازة ويوم العطلة، لكنه **لا** يتجاوز الربط الموقوف ولا الحجز المتعارض |
| **marks available exactly the providers the booking validation accepts** | **اختبار التطابق:** لكل حلاق في القائمة، يستدعي `validateProviderOffersService()` و`validateTimeSlotAvailability()` الحقيقيتين، ويتأكد أن "متاح" في الداشبورد ⇔ "مقبول" في التحقق. إذا اختلفت القاعدتان مستقبلاً يفشل هذا الاختبار ويذكر اسم الحلاق |
| returns unavailable providers with reasons from the Livewire modal endpoint | الـ endpoint الذي تستدعيه الواجهة فعلاً يعيد الحلاق الموقوف مع سببه (اختبار عبر Livewire وبصلاحيات حقيقية) |

**لماذا اختبار التطابق مهم تحديداً؟** الخطآن 3 و4 نشآ لأن قاعدتين كان يُفترض أن تكونا واحدة انفصلتا دون أن يلاحظ أحد. هذا الاختبار يجعل أي انفصال مستقبلي يظهر فوراً في CI، بدل أن يظهر كبلاغ من مدير الصالون.

---

### 4.16 التوثيق · [`docs/STAFF_DASHBOARD.md`](../STAFF_DASHBOARD.md) القسم 13.9

حُدّث وصف الدالة ليذكر أن النافذة تعرض كل المزودين المربوطين مع `available` و`reason`، مع جدول الأسباب الخمسة، وأن القواعد مشتركة مع `BookingValidationService` ويحميها اختبار التطابق.

---

## 5. التحقق

### 5.1 الاختبارات

```
php artisan test tests/Feature/Booking/DashboardProviderAvailabilityTest.php
Tests:    11 passed (50 assertions)
```

المجموعة الكاملة:
```
Tests:    12 failed, 3 skipped, 506 passed (1823 assertions)
```

الـ 12 كلها موجودة قبل هذا التعديل ولا علاقة لها به: Fiskaly (5)، ورفع الصورة الشخصية (3)، وحذف الحساب (1)، والصفحة الرئيسية (1)، و`PasswordRequirementsTest` (1)، و`AuthRateLimitTest` (1). الاختباران الأخيران ليسا في القائمة السابقة الموثّقة، لذلك أُعيد تشغيلهما **بعد إزالة التعديلات مؤقتاً** (`git stash`)، وفشلا كذلك، فهما قديمان.

### 5.2 على بيانات حقيقية (قاعدة البيانات المحلية)

صفحة Layla:
```
active count: 3                      ← كان يظهر 5
Men's Haircut          => pivot 1
Beard Trim & Shape     => pivot 0    ← يظهر الآن رمادياً "موقوفة لهذا المزود"
Hot Towel Shave        => pivot 1
Grey Coverage Coloring => pivot 1
Haircut & Beard Combo  => pivot 0    ← يظهر الآن رمادياً "موقوفة لهذا المزود"
```

نافذة الحجز لخدمة Haircut & Beard Combo يوم 2026-09-27 الساعة 10:00:
```
Aisha Al Zaabi   | OK
Jasmine Lee      | OK
Maria Rodriguez  | OK
Noor Ahmed       | OK
Sophie Martin    | OK
Elena Petrov     | Not working this day
Layla Ibrahim    | Service disabled for this provider     ← حالة البلاغ
Sarah Johnson    | On leave
```

---

## 6. على السيرفر (Production)

### 6.1 التأكد من سبب البلاغ

استعلام للقراءة فقط:

```sql
SELECT s.name, ps.is_active, ps.updated_at
FROM provider_service ps
JOIN services s ON s.id = ps.service_id
JOIN users u ON u.id = ps.provider_id
WHERE u.first_name = 'Layla' AND u.last_name = 'Ibrahim';
```

المتوقع: `is_active = 0` أمام Men's Haircut.

### 6.2 الحل الفوري (لا يحتاج نشر الكود)

إذا كان المطلوب أن تقدّم Layla الخدمة: صفحة الخدمة Men's Haircut ← Edit ← تبويب Providers ← مفتاح **Active** عند Layla ← Save. بعدها تظهر مباشرة في نافذة الحجز.

### 6.3 نشر الكود

- **لا يوجد migration.** كل التعديلات كود وترجمات.
- بعد النشر: `php artisan view:clear` لأن ملف Blade تغيّر، و`php artisan optimize:clear` إذا كانت الترجمات أو الـ config مخزّنة مؤقتاً.
- مسار الحجز من تطبيق الموبايل (API) لم يُلمس.

---

## 7. ما لم يُلمس عمداً

| الشيء | لماذا بقي كما هو |
|---|---|
| `Service::activeProviders()` | ما زالت تُستعمل في `getProvidersForService()` وفي `ServiceAvailabilityService` (مسار العملاء). سلوكها صحيح. النافذة فقط هي التي تحتاج رؤية الموقوفين لتشرح السبب |
| `BookingValidationService` | هو المرجع الصحيح. جعلنا الداشبورد يتبعه، ولم نغيّره |
| الحلاق المربوط بفرع آخر | الداشبورد يعمل حالياً على فرع واحد (موثّق في القسم 13.5 من `STAFF_DASHBOARD.md`). تصفية الفروع خارج نطاق هذه المهمة |
| أسباب الرفض في الـ API للعملاء | تبقى رسالة عامة واحدة حتى لا تُستعمل لمعرفة أسماء المزودين. عرض الأسباب في الداشبورد مقبول لأن مستخدمه موظف يرى الإجازات أصلاً على الجدول الزمني |
| اسم متغير الواجهة `_availableProviders` | تغييره شكلي ويلمس ستة مواضع أخرى بلا فائدة وظيفية (القسم 4.12) |
| `DashboardService::getProvidersWithStatus()` لا يعرف الخدمات | الشريط الجانبي يجيب عن "من يعمل اليوم"، لا "من يقدّم كل خدمة". هذا صحيح حسب التصميم، والنافذة هي مكان سؤال الخدمة |

---

## 8. ما لم يُتحقق منه بعد

- **الشكل في المتصفح:** اختبار Livewire يثبت أن الواجهة تُعرض بدون أخطاء وأن البيانات صحيحة، لكن لم تُفتح نافذة الحجز في متصفح لرؤية الأسطر الباهتة بالعين، ولا تجربة الاتجاه RTL. يُنصح بتجربة سريعة قبل النشر: اختيار Haircut & Beard Combo، والتأكد أن Layla تظهر باهتة وغير قابلة للنقر ومعها السبب.
- **بيانات Production:** الاستنتاج أن ربط Layla موقوف هناك مبني على النقطة الرمادية التي رآها المدير، ولم يُقرأ من قاعدة Production مباشرة. الاستعلام في القسم 6.1 يحسم ذلك.

---

## 9. ملخص الملفات

| الملف | نوع التغيير |
|---|---|
| `app/Models/User.php` | تفعيل `withPivot` |
| `app/Filament/Resources/Providers/Schemas/ProviderInfolist.php` | قائمة الخدمات مع الحالة + عدّاد المفعّل |
| `app/Filament/Resources/Providers/Widgets/ProviderStatsOverviewWidget.php` | عدّاد المفعّل |
| `app/Filament/Resources/Providers/Tables/ProvidersTable.php` | عدّاد المفعّل |
| `app/Filament/Resources/Users/Schemas/UserInfolist.php` | قائمة الخدمات مع الحالة + عدّاد المفعّل |
| `app/Filament/Resources/Users/RelationManagers/ServicesRelationManager.php` | عمود Status يقرأ الربط |
| `app/Services/DashboardService.php` | دالة جديدة مع الأسباب + قواعد الإجازات والتعارض المشتركة |
| `app/Livewire/StaffDashboard.php` | الـ endpoint يعيد القائمة الكاملة + رسم الإجازة بالشريحة الصحيحة |
| `resources/views/livewire/staff-dashboard.blade.php` | عرض غير المتاحين باهتين مع السبب + منع الاختيار التلقائي |
| `lang/{ar,en,de}/dashboard.php` | أسباب عدم التوفّر |
| `lang/{ar,en,de}/resources.php` | `service_link_inactive` |
| `tests/Feature/Booking/DashboardProviderAvailabilityTest.php` | جديد: 11 اختباراً |
| `docs/STAFF_DASHBOARD.md` | تحديث القسم 13.9 |
