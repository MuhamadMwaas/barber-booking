# نظام ملاحظات مقدم الخدمة والألوان — توثيق تقني شامل

> **تاريخ الإضافة:** 2026-05-27  
> **الفرع:** main  
> **النطاق:** ملاحظات مقدم الخدمة على مستوى الحجز + قاموس ألوان مرتبط بالحجوزات

---

## فهرس المحتويات

1. [نظرة عامة](#1-نظرة-عامة)
2. [Migrations — هجرات قاعدة البيانات](#2-migrations--هجرات-قاعدة-البيانات)
3. [Models الجديدة](#3-models-الجديدة)
4. [Filament Admin — Color Resource](#4-filament-admin--color-resource)
5. [تغييرات Appointment Model](#5-تغييرات-appointment-model)
6. [تغييرات Appointment Infolist في Filament](#6-تغييرات-appointment-infolist-في-filament)
7. [تغييرات DashboardService](#7-تغييرات-dashboardservice)
8. [تغييرات StaffDashboard Livewire Component](#8-تغييرات-staffdashboard-livewire-component)
9. [تغييرات Staff Dashboard Blade View](#9-تغييرات-staff-dashboard-blade-view)
10. [Invoice Line Type — Colors Used](#10-invoice-line-type--colors-used)
11. [TemplateBuilderService — Eager Loading](#11-templatebuilderservice--eager-loading)
12. [Translations — الترجمات](#12-translations--الترجمات)
13. [Permissions والأدوار](#13-permissions-والأدوار)
14. [كيفية النشر (Deployment)](#14-كيفية-النشر-deployment)
15. [دليل الاستخدام](#15-دليل-الاستخدام)
16. [قرارات معمارية ومبرراتها](#16-قرارات-معمارية-ومبرراتها)

---

## 1. نظرة عامة

يضيف هذا الإصدار ميزتين مستقلتين لكنهما مكمّلتان لبعضهما في سير عمل الحجوزات:

| الميزة | الوصف | من يستخدمها |
|---|---|---|
| **Provider Notes** | حقل ملاحظات مهنية يملأه مقدم الخدمة خلال أو بعد الحجز | Provider (Staff Dashboard) + Admin (Filament) |
| **Colors System** | قاموس منتجات الألوان يمكن ربطها بأي حجز مع الكميات المستخدمة | Admin يدير القاموس، Provider يسجل الاستخدام لكل حجز |

### لماذا أُضيفت هذه الميزات؟

**Provider Notes** — حقل `notes` الموجود مسبقاً هو حقل خاص بالعميل يُكتب عند الحجز (مثل: "أريده أفتح هذه المرة"). لم يكن هناك مكان مخصص ليكتب فيه مقدم الخدمة ملاحظاته المهنية مثل: حالة الشعر، العلاج المُطبَّق، الملاحظات الطبية. بدون هذا الحقل، تبقى المعرفة المهنية في ذهن مقدم الخدمة وتُفقد مع الوقت.

**Colors System** — الصالونات التي تقدم خدمات الصبغ والتلوين تحتاج لتسجيل المنتجات المستخدمة مع كل عميل، لسببين:
1. **تاريخ العميل** — "أي لون استخدمنا مع سارة قبل 3 أشهر؟" سؤال شائع جداً.
2. **توثيق الفاتورة** — منتجات الألوان المستخدمة يمكن أن تظهر على الإيصال المطبوع كمعلومات توثيقية (لا تؤثر على السعر في هذا التطبيق، لكنها تُنشئ سجلاً شفافاً).

---

## 2. Migrations — هجرات قاعدة البيانات

### 2.1 إضافة `provider_notes` إلى جدول `appointments`

**الملف:** `database/migrations/2026_05_27_100001_add_provider_notes_to_appointments_table.php`

```php
Schema::table('appointments', function (Blueprint $table) {
    $table->text('provider_notes')->nullable()->after('notes');
});
```

**لماذا `text` وليس `string`؟**  
ملاحظات مقدم الخدمة قد تكون طويلة (تاريخ حالة الشعر، خطوات علاج متعددة). النوع `text` يستوعب حتى 65 كيلوبايت وهو مناسب؛ أما `string` أو `VARCHAR(255)` فسيكون مقيِّداً جداً.

**لماذا `nullable`؟**  
معظم الحجوزات لن تحتوي على ملاحظات مقدم الخدمة في البداية. جعله إلزامياً كان سيكسر كل السجلات الموجودة مسبقاً ويُجبر مقدمي الخدمة على ملء بيانات في كل حجز حتى عند عدم الحاجة.

**التأثير:**  
- يُضيف عموداً واحداً فقط إلى جدول `appointments`.
- لا تتأثر أي بيانات موجودة.
- جميع الاستعلامات الحالية التي لا تشير إلى `provider_notes` تستمر في العمل دون أي تغيير.

---

### 2.2 إنشاء جدول `colors`

**الملف:** `database/migrations/2026_05_27_100002_create_colors_table.php`

```php
Schema::create('colors', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('hex_code', 7)->default('#000000');
    $table->string('brand')->nullable();
    $table->string('unit', 20)->default('ml');
    $table->decimal('stock_quantity', 8, 2)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**توضيح كل عمود:**

| العمود | النوع | السبب |
|---|---|---|
| `name` | `string` | اسم اللون أو المنتج، مثال: "Ash Blonde 9.1" |
| `hex_code` | `string(7)` | معرّف بصري في الواجهة (مربع اللون). ثابت عند 7 أحرف بصيغة `#RRGGBB` |
| `brand` | `string nullable` | العلامة التجارية اختيارية — بعض الصالونات تستخدم منتجات غير مُسمّاة |
| `unit` | `string(20)` | وحدة قياس المنتج: ml, g, oz, piece، إلخ |
| `stock_quantity` | `decimal(8,2) nullable` | كمية مرجعية للمدير — **ليس** نظام مخزون حقيقي |
| `is_active` | `boolean` | إخفاء اللون دون حذفه (يحافظ على السجلات التاريخية القديمة) |

**التأثير:**  
- ينشئ جدولاً جديداً مستقلاً.
- لا يُعدِّل أي جداول أخرى.
- يمكن للمدير البدء في ملء قاموس الألوان فور تشغيل الـ migration.

---

### 2.3 إنشاء جدول `appointment_colors` (Pivot Table)

**الملف:** `database/migrations/2026_05_27_100003_create_appointment_colors_table.php`

```php
Schema::create('appointment_colors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
    $table->foreignId('color_id')->constrained('colors')->cascadeOnDelete();
    $table->decimal('quantity', 8, 2)->default(0);
    $table->timestamps();
});
```

**لماذا جدول pivot بـ `id` خاص به؟**  
استخدام `updateOrCreate` (منطق upsert) على الـ pivot يصبح أسهل عندما يكون لكل صف `id` خاص. بدون المفتاح الأساسي كنا سنحتاج لتحديد الصف بالزوج المركّب `(appointment_id, color_id)` مما يُعقّد عملية الحذف في Livewire.

**لماذا `cascadeOnDelete` على المفتاحين الخارجيين؟**  
- عند حذف حجز → تُحذف تلقائياً جميع سجلات ألوانه (لا صفوف يتيمة).
- عند حذف لون من القاموس → تُحذف تلقائياً جميع سجلات استخدامه في الحجوزات.

**عمود `quantity`:**  
يخزّن كمية اللون المستخدمة في *هذا الحجز تحديداً* (مثال: 30 ml). هذا مستقل تماماً عن `stock_quantity` في جدول `colors` الذي هو مجرد رقم مرجعي.

**التأثير:**  
- ينشئ الرابط بين الحجوزات والألوان (many-to-many).
- حجز واحد يمكنه استخدام ألوان متعددة، ولون واحد يمكن أن يظهر في حجوزات كثيرة.

---

## 3. Models الجديدة

### 3.1 `app/Models/Color.php`

```php
protected $fillable = ['name', 'hex_code', 'brand', 'unit', 'stock_quantity', 'is_active'];

protected $casts = [
    'stock_quantity' => 'decimal:2',
    'is_active'      => 'boolean',
];

// علاقة many-to-many مع الحجوزات
public function appointments(): BelongsToMany
{
    return $this->belongsToMany(Appointment::class, 'appointment_colors')
        ->withPivot('quantity')
        ->withTimestamps();
}

// Scope لإرجاع الألوان النشطة فقط
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}

// Accessor لعرض "الاسم (البراند)" في الـ dropdown
public function getDisplayNameAttribute(): string
{
    return $this->brand ? "{$this->name} ({$this->brand})" : $this->name;
}
```

**`scopeActive()`** — تستخدمه دالة `DashboardService::getAllColors()` لعرض الألوان النشطة فقط في قائمة الاختيار لدى مقدم الخدمة. الألوان غير النشطة تُخفى من الإضافة الجديدة لكن تظل في السجلات التاريخية القديمة.

**`getDisplayNameAttribute()`** — يعرض "Ash Blonde 9.1 (Wella)" أو "Ash Blonde 9.1" فقط في قائمة الاختيار. يوفّر معلومات واضحة دون الحاجة لمنطق إضافي في الواجهة.

**التأثير:**  
- الـ Model هو المصدر الوحيد للحقيقة فيما يخص قاموس الألوان.
- يتبع نفس النمط الموجود في باقي Models في المشروع.

---

### 3.2 `app/Models/AppointmentColor.php`

```php
protected $table = 'appointment_colors';

protected $fillable = ['appointment_id', 'color_id', 'quantity'];

protected $casts = ['quantity' => 'decimal:2'];

public function appointment(): BelongsTo
{
    return $this->belongsTo(Appointment::class);
}

public function color(): BelongsTo
{
    return $this->belongsTo(Color::class);
}
```

**لماذا model مستقل للـ pivot وليس anonymous pivot class؟**  
العلاقة `colorRecords()` من نوع `HasMany` على `Appointment` تُرجع نسخاً من هذا الـ model — كل سجل له `id` خاص يُستخدم في عملية الحذف من Livewire عبر دالة `removeColorFromAppointment(int $appointmentColorId)`. استخدام anonymous pivot class لن يُعطي `id` قابلاً للاستخدام المباشر.

**التأثير:**  
- يمنح وصولاً مباشراً عبر ORM إلى سجلات appointment-color الفردية.
- يُستخدم في قوالب Blade لعرض قائمة الألوان في الـ accordion.

---

## 4. Filament Admin — Color Resource

### هيكل الملفات

```
app/Filament/Resources/Colors/
├── ColorResource.php          ← الـ Resource الرئيسي
├── Pages/
│   ├── ListColors.php
│   ├── CreateColor.php
│   ├── EditColor.php
│   └── ViewColor.php
├── Schemas/
│   ├── ColorForm.php          ← فورم الإنشاء والتعديل
│   └── ColorInfolist.php      ← صفحة العرض
└── Tables/
    └── ColorsTable.php        ← الجدول مع الفلاتر
```

هذا الهيكل يطابق تماماً النمط الموجود في بقية Resources في المشروع (مثل `Appointments/` و`Customers/`).

### أبرز ما في `ColorResource.php`

```php
protected static ?string $navigationGroup = 'settings';
protected static ?int $navigationSort = 30;
protected static string $model = Color::class;
```

- وُضع في مجموعة التنقل **Settings** إلى جانب الموارد الأخرى من نوع القواميس والإعدادات.
- يستخدم trait الـ `NavigationDefaultAccess` فيُولِّد تلقائياً permissions: `Color:access`، `Color:view`، `Color:create`، `Color:edit`، `Color:delete`، `Color:force_delete`.

### `ColorForm.php` — حقول الفورم

| الحقل | النوع | الملاحظات |
|---|---|---|
| Name | TextInput (مطلوب) | اسم اللون أو المنتج |
| Brand | TextInput (اختياري) | اسم الشركة المصنعة |
| Hex Code | ColorPicker | منتقي لوني بصري؛ يُخزَّن بصيغة `#RRGGBB` |
| Unit | Select | خيارات: ml, g, piece, oz |
| Stock Quantity | TextInput (رقمي، اختياري) | رقم مرجعي فقط |
| Is Active | Toggle | يبدأ مفعّلاً بشكل افتراضي |

### `ColorsTable.php` — الجدول

- يعرض **مربع اللون** (inline `<span>` بـ `background-color`) مع الاسم في العمود الأول.
- `TernaryFilter` على `is_active` للتصفية السريعة بين النشط وغير النشط.
- `ViewAction`، `EditAction`، `BulkDeleteAction`.

**التأثير:**  
- يستطيع المدير إدارة قاموس الألوان بالكامل من لوحة Filament.
- يمكن تعطيل الألوان (وليس حذفها) لإخفائها من قائمة اختيار مقدم الخدمة مع الحفاظ على سجلات الاستخدام التاريخية.

---

## 5. تغييرات Appointment Model

**الملف:** `app/Models/Appointment.php`

### الإضافة إلى `$fillable`

```php
'provider_notes',
```

بدون هذا، `Appointment::update(['provider_notes' => ...])` كان سيُتجاهل بصمت بسبب حماية الـ mass-assignment في Eloquent.

### العلاقات الجديدة

```php
// Many-to-many: appointment ↔ colors (عبر جدول appointment_colors)
public function colors(): BelongsToMany
{
    return $this->belongsToMany(Color::class, 'appointment_colors')
        ->withPivot('quantity')
        ->withTimestamps();
}

// HasMany إلى الـ pivot model (مطلوب للحصول على id كل سجل)
public function colorRecords(): HasMany
{
    return $this->hasMany(AppointmentColor::class, 'appointment_id');
}
```

**لماذا علاقتان `colors()` و`colorRecords()`؟**

| العلاقة | تُستخدم لـ |
|---|---|
| `colors()` BelongsToMany | الحصول على objects من نوع Color مع بيانات الـ pivot؛ يمكن الاستعلام "أي حجوزات استخدمت اللون X" |
| `colorRecords()` HasMany | جلب صفوف الـ pivot *مع id الخاص بكل صف* لعملية الحذف الفردي من Livewire |

**التأثير:**  
- الـ `Appointment` الآن لديه وصول كامل لتاريخ استخدام الألوان.
- الـ eager load لـ `colorRecords` في `getAppointmentDetails()` يجلب instances من `AppointmentColor` مع بيانات `Color` الداخلية.

---

## 6. تغييرات Appointment Infolist في Filament

**الملف:** `app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php`

### إضافة Provider Notes Entry

```php
TextEntry::make('provider_notes')
    ->label(__('dashboard.appointment_modal.provider_notes'))
    ->icon('heroicon-o-pencil-square')
    ->color('warning')
    ->placeholder('—')
    ->columnSpanFull(),
```

أُضيف في قسم **"Additional Information"** من الـ infolist. يظهر باللون الأصفر (warning) ليتميز بصرياً عن حقل ملاحظات العميل الذي يعلوه.

### إضافة قسم Colors Used

`Section` جديد قابل للطي (collapsible) يظهر فقط عندما يحتوي الحجز على سجلات ألوان. يعرض:
- مربع اللون (12×12px inline CSS span)
- اسم اللون + البراند
- الكمية + الوحدة

**التأثير:**  
- يستطيع المدير عند فتح أي حجز في Filament رؤية ملاحظات العميل وملاحظات مقدم الخدمة بشكل منفصل وواضح.
- تاريخ استخدام الألوان مرئي مباشرة على صفحة تفاصيل الحجز.

---

## 7. تغييرات DashboardService

**الملف:** `app/Services/DashboardService.php`

### تحديث `getAppointmentDetails()`

أُضيف `'colorRecords.color'` إلى العلاقات المُحمَّلة مسبقاً (eager loading):

```php
$appointment->load([
    // ... التحميلات الموجودة مسبقاً ...
    'colorRecords.color',
]);
```

هذا يضمن أنه عند فتح Staff Dashboard لـ modal الحجز، جميع سجلات الألوان (مع الـ Color model الأب الخاص بكل منها) محمّلة مسبقاً — لا استعلامات إضافية تُطلَق عند عرض الـ accordion.

### دالة جديدة `getAllColors()`

```php
public function getAllColors(): array
{
    return Color::active()
        ->orderBy('name')
        ->get(['id', 'name', 'hex_code', 'brand', 'unit', 'stock_quantity'])
        ->map(fn ($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'display_name'   => $c->display_name,
            'hex_code'       => $c->hex_code,
            'brand'          => $c->brand,
            'unit'           => $c->unit,
            'stock_quantity' => $c->stock_quantity ? (float) $c->stock_quantity : null,
        ])
        ->toArray();
}
```

**لماذا array بسيط وليس Collection؟**  
دالة `getPreloadedData()` تُرجع PHP array يتحوّل إلى JSON لاستهلاكه في Alpine.js. الـ arrays تُسلسَل بشكل نظيف؛ الـ Collections تضيف overhead غير ضروري.

**لماذا `Color::active()`؟**  
الألوان غير النشطة لا يجب أن تظهر في dropdown "إضافة لون" لدى مقدم الخدمة، حتى لو ظلت سجلاتها التاريخية موجودة في حجوزات قديمة.

**تأثير الأداء:**  
`getAllColors()` تُستدعى مرة واحدة عند تحميل الصفحة داخل `getPreloadedData()` التي تُخزَّن في cache لمدة **دقيقة واحدة لكل locale**. إذا أُضيف لون جديد للقاموس، سيظهر في الـ dashboard خلال دقيقة دون الحاجة لإلغاء الـ cache يدوياً.

---

## 8. تغييرات StaffDashboard Livewire Component

**الملف:** `app/Livewire/StaffDashboard.php`

### خاصية Public جديدة

```php
public string $editProviderNotes = '';
```

مرتبطة عبر `wire:model` بـ textarea الملاحظات المهنية في الـ modal.

### تحديث `openAppointmentModal()`

```php
$this->editProviderNotes = $appointment->provider_notes ?? '';
```

يملأ الـ textarea بالملاحظات الموجودة عند فتح الـ modal.

### دالة جديدة: `updateProviderNotes()`

```php
public function updateProviderNotes(): void
{
    $appointment = Appointment::findOrFail($this->selectedAppointmentId);

    $appointment->update(['provider_notes' => $this->editProviderNotes]);

    // تحديث الـ computed property
    unset($this->selectedAppointment);

    $this->dispatch('notify', [
        'type'    => 'success',
        'message' => __('dashboard.appointment_modal.provider_notes_saved'),
    ]);
}
```

تحفظ الملاحظات في قاعدة البيانات وتُعيد تحميل الـ computed property `$selectedAppointment` حتى يعكس الـ modal القيمة الجديدة فوراً دون إعادة تحميل كاملة للصفحة.

### دالة جديدة: `addColorToAppointment(int $colorId, float $quantity)`

```php
public function addColorToAppointment(int $colorId, float $quantity): void
{
    $appointment = Appointment::findOrFail($this->selectedAppointmentId);

    // Upsert: تحديث الكمية إن كان اللون موجوداً، وإلا إنشاء سجل جديد
    AppointmentColor::updateOrCreate(
        ['appointment_id' => $appointment->id, 'color_id' => $colorId],
        ['quantity' => $quantity]
    );

    unset($this->selectedAppointment);

    $this->dispatch('color-added', [
        'message' => __('dashboard.appointment_modal.color_added'),
    ]);
}
```

**منطق Upsert** — إذا أضاف مقدم الخدمة نفس اللون مرتين، تُحدَّث الكمية بدلاً من إنشاء صف مكرر. يمنع هذا عدم الاتساق في البيانات (مثل صفّين لـ "Ash Blonde" على نفس الحجز).

### دالة جديدة: `removeColorFromAppointment(int $appointmentColorId)`

```php
public function removeColorFromAppointment(int $appointmentColorId): void
{
    AppointmentColor::where('id', $appointmentColorId)
        ->where('appointment_id', $this->selectedAppointmentId) // أمان: التأكد أن السجل تابع لهذا الحجز
        ->delete();

    unset($this->selectedAppointment);

    $this->dispatch('color-removed', [
        'message' => __('dashboard.appointment_modal.color_removed'),
    ]);
}
```

**ملاحظة أمنية:** شرط `->where('appointment_id', $this->selectedAppointmentId)` يمنع أي طلب خاطئ أو متعمد من حذف سجل لون تابع لحجز آخر.

### تحديث `getPreloadedData()`

```php
'colors' => $this->dashboardService->getAllColors(),
```

أُضيف إلى البيانات المُخزَّنة في cache حتى يجد الـ dropdown الخاص بإضافة الألوان خياراته جاهزة دون طلبات HTTP إضافية.

**التأثير:**  
- كل إدارة الألوان تتم عبر 3 دوال Livewire نظيفة ومحددة المسؤوليات.
- نمط إلغاء صلاحية الـ computed property (`unset($this->selectedAppointment)`) يضمن دائماً أن الـ modal يعرض البيانات الأحدث بعد أي عملية تعديل.

---

## 9. تغييرات Staff Dashboard Blade View

**الملف:** `resources/views/livewire/staff-dashboard.blade.php`

### Accordion الملاحظات المهنية (Provider Notes)

```html
<!-- Provider Notes - تصميم أزرق للتمييز عن ملاحظات العميل -->
<div class="border border-blue-200 rounded-lg overflow-hidden bg-blue-50">
    <button ...> <!-- زر التبديل للـ accordion -->
        <span>{{ __('dashboard.appointment_modal.provider_notes') }}</span>
    </button>
    <div x-show="openSection === 'provider_notes'" ...>
        <textarea wire:model="editProviderNotes"
                  placeholder="{{ __('dashboard.appointment_modal.provider_notes_placeholder') }}"
                  rows="3"></textarea>
        <button wire:click="updateProviderNotes">
            {{ __('dashboard.appointment_modal.save_provider_notes') }}
        </button>
    </div>
</div>
```

**التصميم الأزرق** يميّز الملاحظات المهنية عن ملاحظات العميل (التي تستخدم التصميم المحايد/الافتراضي). الفرق البصري يعزّز في ذهن المستخدم أن هذين حقلان لغرضين مختلفين.

### Accordion الألوان المستخدمة (Colors Used)

```html
<!-- Colors Used - تصميم بنفسجي -->
<div class="border border-purple-200 rounded-lg overflow-hidden bg-purple-50">
    <button ...>
        <span>{{ __('dashboard.appointment_modal.colors_used') }}</span>
    </button>
    <div x-show="openSection === 'colors_used'">

        <!-- سجلات الألوان الموجودة -->
        @forelse ($selectedAppointment->colorRecords as $record)
            <div class="flex items-center gap-2">
                <!-- مربع اللون -->
                <span style="background-color: {{ $record->color->hex_code }};
                             width:16px; height:16px; border-radius:3px; border:1px solid #ccc;"></span>
                <!-- الاسم + البراند -->
                <span>{{ $record->color->name }}</span>
                @if ($record->color->brand)
                    <span class="text-xs text-gray-400">({{ $record->color->brand }})</span>
                @endif
                <!-- الكمية + الوحدة -->
                <span>{{ $record->quantity }} {{ $record->color->unit }}</span>
                <!-- زر الحذف -->
                <button wire:click="removeColorFromAppointment({{ $record->id }})"
                        wire:confirm="{{ __('dashboard.appointment_modal.confirm_remove_color') }}">
                    ✕
                </button>
            </div>
        @empty
            <p>{{ __('dashboard.appointment_modal.no_colors') }}</p>
        @endforelse

        <!-- فورم إضافة لون — Alpine local state -->
        <div x-data="{
            newColorId: '',
            newColorQty: 1,
            addingColor: false,
            async submitColor() {
                if (!this.newColorId) return;
                this.addingColor = true;
                await $wire.addColorToAppointment(this.newColorId, this.newColorQty);
                this.newColorId  = '';
                this.newColorQty = 1;
                this.addingColor = false;
            }
        }">
            <select x-model="newColorId">
                <option value="">{{ __('dashboard.appointment_modal.select_color') }}</option>
                @foreach ($preloadedData['colors'] as $color)
                    <option value="{{ $color['id'] }}">{{ $color['display_name'] }}</option>
                @endforeach
            </select>
            <input type="number" x-model="newColorQty" min="0.1" step="0.1" />
            <button @click="submitColor()" :disabled="addingColor || !newColorId">+</button>
        </div>

    </div>
</div>
```

**لماذا Alpine local state لفورم إضافة اللون؟**  
حقلا الاختيار والكمية هما حالة مؤقتة في الواجهة — لا تحتاج للبقاء في حالة Livewire على السيرفر. استخدام Alpine (`x-data`) للفورم يمنع رحلات Livewire غير ضرورية مع كل ضغطة مفتاح، مع الاحتفاظ باستدعاء دوال Livewire (`$wire.addColorToAppointment()`) لعملية الحفظ الفعلية في قاعدة البيانات.

**مستمعو الأحداث (Event Listeners):**

```html
@color-added.window  → يعرض toast نجاح
@color-removed.window → يعرض toast نجاح
```

هذه تستخدم نظام الإشعارات الموجود مسبقاً في Staff Dashboard.

**التأثير:**  
- مقدمو الخدمة يرون واجهة مرئية واضحة ومصنّفة بالألوان في modal الحجز.
- الـ accordion الأزرق للملاحظات المهنية والأرجواني للألوان يوضحان فور النظر الغرض من كل قسم.

---

## 10. Invoice Line Type — Colors Used

### قالب Blade

**الملف:** `resources/views/invoices/line-types/colors-used.blade.php`

يعرض جدولاً بالألوان المستخدمة في الحجز على الفاتورة المطبوعة.

```blade
@php
    $title        = $properties['title']         ?? 'Colors Used';
    $showTitle    = $properties['show_title']    ?? true;
    $showHex      = $properties['show_hex']      ?? true;
    $showBrand    = $properties['show_brand']    ?? true;
    $fontSize     = $properties['font_size']     ?? 9;
    $colorRecords = $invoice->appointment?->colorRecords ?? collect();
@endphp

@if ($colorRecords->isNotEmpty())
    <div class="line-item colors-used-container"
         style="font-size: {{ $fontSize }}px; margin-top: {{ $marginTop }}px;">

        @if ($showTitle)
            <div style="font-weight: bold; border-bottom: 1px solid #e2e8f0;">{{ $title }}</div>
        @endif

        <table style="width: 100%;">
            @foreach ($colorRecords as $colorRecord)
                @php $color = $colorRecord->color; @endphp
                @if ($color)
                    <tr>
                        <td>
                            @if ($showHex)
                                <span style="display:inline-block; width:12px; height:12px;
                                             background: {{ $color->hex_code }};
                                             border: 1px solid #ccc; border-radius:2px;"></span>
                            @endif
                        </td>
                        <td>
                            {{ $color->name }}
                            @if ($showBrand && $color->brand)
                                <span style="color:#94a3b8;">({{ $color->brand }})</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            {{ number_format($colorRecord->quantity, 2) }} {{ $color->unit }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </table>
    </div>
@endif
```

**الشرط `@if ($colorRecords->isNotEmpty())`:** الكتلة بأكملها مُغلَّفة بهذا الشرط. الحجوزات التي لا تحتوي على ألوان لا تُنتج أي مخرجات — لا يظهر قسم فارغ على الفاتورة.

**CSS مُضمَّن فقط:** HTML الفاتورة عادةً يُعرض في متصفح headless أو سياق طباعة حيث CSS الخارجي قد لا يُحمَّل. كل التنسيق inline.

### إدخال Config

**الملف:** `config/invoice-line-types.php`

```php
'colors_used' => [
    'label'      => 'Colors Used (Client History)',
    'icon'       => 'heroicon-o-swatch',
    'blade_view' => 'invoices.line-types.colors-used',
    'sections'   => ['body', 'footer'],
    'unique'     => true,
    'properties' => [
        'title'         => 'Colors Used',
        'show_title'    => true,
        'show_hex'      => true,
        'show_brand'    => true,
        'font_size'     => 9,
        'margin_top'    => 5,
        'margin_bottom' => 5,
    ],
],
```

**`unique: true`** — كتلة ألوان واحدة فقط لكل قالب فاتورة منطقياً.  
**`sections: ['body', 'footer']`** — الألوان معلومات توثيقية؛ لا مكان لها في الـ header.

### كيفية إضافة هذا الـ Line Type إلى قالب فاتورة

1. اذهب إلى Filament Admin → **Invoice Templates**.
2. افتح قالباً موجوداً أو أنشئ جديداً.
3. في قسم **Body** أو **Footer**، اضغط **"Add Line"**.
4. اختر **"Colors Used (Client History)"** من dropdown أنواع السطور.
5. اضبط العنوان وخيارات العرض (إظهار الـ hex، إظهار البراند، حجم الخط).
6. احفظ القالب.

من تلك اللحظة، أي فاتورة تُطبع باستخدام هذا القالب ستتضمن تلقائياً قسم الألوان إن كان الحجز يحتوي على سجلات ألوان.

**التأثير:**  
- لا تأثير على الأسعار — الألوان تظهر كبيانات توثيقية فقط.
- القسم غير مرئي على فواتير الحجوزات التي لا تحتوي على سجلات ألوان.
- يمنح عملاء الصالون إيصالاً مطبوعاً يوضّح المنتجات المستخدمة معهم.

---

## 11. TemplateBuilderService — Eager Loading

**الملف:** `app/Services/InvoiceTemplate/TemplateBuilderService.php`

### التغيير

أُضيف `'appointment.colorRecords.color'` إلى دالة `loadInvoiceRelationships()`:

```php
protected function loadInvoiceRelationships(Invoice $invoice): void
{
    $invoice->load([
        // ... التحميلات الموجودة مسبقاً ...
        'appointment.colorRecords.color',
    ]);
}
```

**لماذا هذا ضروري؟**  
عندما يُشغِّل قالب Blade الخاص بـ `colors-used` السطر `$invoice->appointment->colorRecords`، يجب أن تكون تلك السجلات محمّلة مسبقاً في الذاكرة. بدون هذا الـ eager load، سيُطلق كل سجل لون استعلام SQL منفصل (مشكلة N+1). لحجز يحتوي على 5 سجلات ألوان، هذا يعني 5 استعلامات إضافية أثناء توليد الفاتورة — غير مقبول في مسار الطباعة وتوليد PDF.

**التأثير:**  
- توليد الفواتير يحمّل سجلات الألوان الآن في استعلام واحد محسَّن.
- لا تراجع في الأداء للفواتير التي لا تحتوي على سجلات ألوان (تُرجع collections فارغة فوراً).

---

## 12. Translations — الترجمات

أُضيفت مفاتيح ترجمة إلى جميع اللغات الثلاث المدعومة.

### المفاتيح المضافة داخل قسم `appointment_modal`

| المفتاح | الإنجليزية 🇬🇧 | العربية 🇸🇦 | الألمانية 🇩🇪 |
|---|---|---|---|
| `provider_notes` | Provider Notes | ملاحظات مقدم الخدمة | Anbieter-Notizen |
| `provider_notes_placeholder` | Professional observations... | ملاحظات مهنية... | Fachliche Beobachtungen... |
| `save_provider_notes` | Save Provider Notes | حفظ ملاحظات المزود | Anbieter-Notizen speichern |
| `provider_notes_saved` | Provider notes saved | تم حفظ ملاحظات المزود | Anbieter-Notizen gespeichert |
| `colors_used` | Colors Used | الألوان المستخدمة | Verwendete Farben |
| `no_colors` | No colors recorded yet | لم يتم تسجيل ألوان بعد | Noch keine Farben erfasst |
| `select_color` | Select color | اختر لونًا | Farbe auswählen |
| `qty` | Qty | الكمية | Menge |
| `color_added` | Color added | تمت إضافة اللون | Farbe hinzugefügt |
| `color_removed` | Color removed | تم حذف اللون | Farbe entfernt |
| `confirm_remove_color` | Remove this color entry? | هل تريد حذف هذا اللون؟ | Diesen Farbeintrag entfernen? |

### الملفات المُعدَّلة

- `lang/en/dashboard.php`
- `lang/ar/dashboard.php`
- `lang/de/dashboard.php`

**التأثير:**  
- جميع تسميات الواجهة مترجمة بالكامل منذ اليوم الأول.
- الـ cache الذي يدرك الـ locale في `getPreloadedData()` (دقيقة واحدة) يضمن التقاط تغييرات اللغة بسرعة.

---

## 13. Permissions والأدوار

### كيف تُولَّد Permissions تلقائياً؟

يستخدم المشروع trait الـ `NavigationDefaultAccess` على جميع Filament Resources. الـ `PermissionsSeeder` يكتشف تلقائياً كل ملف `*Resource.php` ويُولِّد permissions من اسم الـ class.

`ColorResource.php` ← يُولِّد:
- `Color:access`
- `Color:view`
- `Color:create`
- `Color:edit`
- `Color:delete`
- `Color:force_delete`

### تأثير على الأدوار

| الدور | الوصول إلى Color Resource |
|---|---|
| `SuperAdmin` | ✅ وصول كامل (يستخدم استراتيجية `'all'`) |
| `admin` | ✅ وصول كامل (يستخدم استراتيجية `'all'`) |
| `staff` / `provider` | ❌ لا وصول للوحة Filament (يستخدمون Staff Dashboard فقط) |

**لم تكن هناك حاجة لأي تغييرات يدوية في الـ seeders.** الـ `PermissionsSeeder` يتولى كل شيء تلقائياً.

---

## 14. كيفية النشر (Deployment)

شغِّل الـ migrations على قاعدة البيانات الإنتاجية:

```bash
php artisan migrate --force
```

ينفّذ هذا الأمر الـ migrations الثلاثة الجديدة:
1. `add_provider_notes_to_appointments_table` — آمن، يضيف عموداً nullable
2. `create_colors_table` — ينشئ جدولاً جديداً
3. `create_appointment_colors_table` — ينشئ جدول الـ pivot

ثم (اختياري) أعد تشغيل seeder الـ permissions لتسجيل permissions الـ Color Resource الجديدة:

```bash
php artisan db:seed --class=PermissionsSeeder --force
```

> **ملاحظة:** إعادة تشغيل الـ seeder آمنة تماماً. يستخدم منطق `updateOrCreate` ولن يُكرِّر permissions موجودة.

أخيراً، امسح الـ caches:

```bash
php artisan config:clear
php artisan view:clear
php artisan optimize
```

---

## 15. دليل الاستخدام

### للمدير (Filament Admin)

**إدارة قاموس الألوان:**
1. اذهب إلى **Settings → Colors**.
2. أنشئ ألواناً مع: الاسم، البراند، Hex Code (استخدم منتقي الألوان)، الوحدة، والكمية الاحتياطية (اختياري).
3. استخدم toggle الـ **Is Active** لإخفاء الألوان دون حذفها.

**عرض ألوان حجز معين:**
1. افتح أي حجز في Filament Admin.
2. قسم **"Colors Used"** يعرض جميع الألوان المسجّلة لذلك الحجز.

**إضافة الألوان لقوالب الفواتير:**
1. اذهب إلى **Invoice Templates** → افتح قالباً → أضف سطر **"Colors Used (Client History)"** في Body أو Footer.

---

### لمقدم الخدمة (Staff Dashboard)

**إضافة ملاحظات مهنية:**
1. افتح modal الحجز.
2. افتح accordion **"Provider Notes"** (الأزرق).
3. اكتب ملاحظاتك المهنية.
4. اضغط **"Save Provider Notes"**.

**تسجيل الألوان المستخدمة:**
1. افتح modal الحجز.
2. افتح accordion **"Colors Used"** (الأرجواني).
3. اختر لوناً من القائمة، أدخل الكمية، اضغط **"+"**.
4. لحذف لون، اضغط **"✕"** بجانبه وأكّد الحذف.

---

## 16. قرارات معمارية ومبرراتها

### لماذا حقلان منفصلان للملاحظات؟

حقل `notes` الموجود مسبقاً يكتبه العميل أو موظف الاستقبال عند الحجز — يلتقط تفضيلات العميل ("أريده أفتح هذه المرة"). حقل `provider_notes` الجديد يكتبه المحترف *خلال أو بعد* الحجز — يلتقط المخرجات التقنية ("طُبِّق 9.1 Ash Blonde، مطوِّر 30 vol، 35 دقيقة معالجة"). خلط هذين في حقل واحد كان سيُسبب:
- ارتباكاً في من يجب أن يقرأ ماذا
- تعقيداً في عرضهما بشكل منفصل في التقارير أو تاريخ العميل

### لماذا لا نظام مخزون كامل؟

نظام المخزون الحقيقي يتطلب: خصم المخزون عند اكتمال الحجز، تنبيهات انخفاض المخزون، تتبع أوامر الشراء، وإدارة الموردين. هذا نطاق منفصل تماماً. هذا التطبيق يلتقط **تاريخ الاستخدام** (توثيق)، وليس إدارة المخزون. حقل `stock_quantity` في جدول `Color` هو قيمة مرجعية للمدير فقط — لا يُخصم تلقائياً أبداً.

### لماذا `colorRecords()` HasMany بدلاً من `colors()` BelongsToMany فقط؟

عند عرض accordion الألوان في Livewire، كل عنصر في القائمة يحتاج `id` صف الـ pivot لتشغيل عملية `removeColorFromAppointment($id)`. علاقة `colors()` BelongsToMany تُعطي وصولاً لـ Color objects مع `->pivot->quantity`، لكن `id` الـ pivot لا يكون متاحاً بسهولة ما لم تُضمِّنه صراحةً عبر `withPivot('id')`. استخدام model مستقل `AppointmentColor` عبر `colorRecords()` HasMany أوضح، أكثر type-safety، ومتسق مع كيفية التعامل مع أنماط pivot-with-extras في هذا المشروع.

### لماذا Alpine state لفورم إضافة اللون؟

حقلا الـ dropdown والكمية هما حالة واجهة مؤقتة — لا تحتاج للاستمرار حتى يضغط المستخدم "+". ربطهما بـ Livewire properties عبر `wire:model` كان سيُطلق رحلة إلى السيرفر مع كل ضغطة مفتاح وكل تغيير في القائمة. Alpine's `x-data` يُبقي هذه الحالة محلية حتى يُستدعى `submitColor()`، الذي يستدعي `$wire.addColorToAppointment()` مرة واحدة فقط لعملية الكتابة الفعلية في قاعدة البيانات. هذا هو التوزيع الصحيح للمسؤوليات بين Alpine (حالة الواجهة) و Livewire (حالة السيرفر).

---

*تم إنشاء هذا التوثيق: 2026-05-27*  
*النظام: إدارة صالون تجميل — Laravel 12 + Filament 4.0*
