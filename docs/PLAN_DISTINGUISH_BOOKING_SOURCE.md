# خطة تنفيذ: التفريق بين الحجوزات Online و In-Person

> **النسخة:** 1.0
> **التاريخ:** 2026-05-21
> **حالة التنفيذ:** لم يبدأ بعد — هذه وثيقة تخطيط فقط

---

## فهرس المحتويات

1. [فهم المهمة](#1-فهم-المهمة)
2. [تحليل النظام الحالي](#2-تحليل-النظام-الحالي)
3. [الحل المقترح](#3-الحل-المقترح)
4. [خطة التنفيذ التفصيلية](#4-خطة-التنفيذ-التفصيلية)
   - [4.1 إنشاء BookingSource Enum](#41-إنشاء-bookingsource-enum)
   - [4.2 إنشاء Migration](#42-إنشاء-migration)
   - [4.3 تحديث Appointment Model](#43-تحديث-appointment-model)
   - [4.4 تحديث BookingService](#44-تحديث-bookingservice)
   - [4.5 تحديث API BookingController](#45-تحديث-api-bookingcontroller)
   - [4.6 تحديث StaffDashboard](#46-تحديث-staffdashboard)
   - [4.7 تحديث Filament CreateAppointment](#47-تحديث-filament-createappointment)
   - [4.8 تحديث Web BookingController](#48-تحديث-web-bookingcontroller)
   - [4.9 تحديث Filament AppointmentResource](#49-تحديث-filament-appointmentresource)
   - [4.10 تحديث DashboardService](#410-تحديث-dashboardservice)
   - [4.11 تحديث StaffDashboard Timeline View](#411-تحديث-staffdashboard-timeline-view)
   - [4.12 تحديث API AppointmentResource](#412-تحديث-api-appointmentresource)
5. [ملخص الملفات المتغيرة](#5-ملخص-الملفات-المتغيرة)
6. [ملاحظات إضافية](#6-ملاحظات-إضافية)

---

## 1. فهم المهمة

### الهدف
إضافة إمكانية التفريق بين المواعيد المحجوزة **Online** (عن طريق التطبيق عبر API) والمواعيد المحجوزة **In-Person** (عن طريق المدير/اللوحة/الـ Dashboard).

### نطاق العمل
- **مصدرين فقط:** `online` (API) و `in_person` (Staff Dashboard + Filament Admin + Web Booking)
- **الهدف:** تتبع ومراقبة + تقارير وإحصائيات
- **تأثير على Business Logic:** لا يوجد — المعلومة للتوثيق والتقارير فقط
- **البيانات القديمة:** لا توجد بيانات حالياً (النظام تحت التطوير)

---

## 2. تحليل النظام الحالي

### 2.1 مصادر إنشاء الحجوزات

النظام الحالي لديه **4 مسارات** لإنشاء Appointment:

| المسار | الملف | الـ Method | يستخدم `BookingService`؟ |
|--------|-------|-----------|--------------------------|
| **API Mobile App** | `app/Http/Controllers/Api/BookingController.php` | `store()` | ✅ `BookingService::createBooking()` |
| **Staff Dashboard** | `app/Livewire/StaffDashboard.php` | `saveBookingFromAlpine()` | ✅ `BookingService::createBooking()` |
| **Filament Admin** | `app/Filament/Resources/Appointments/Pages/CreateAppointment.php` | `handleRecordCreation()` | ❌ مباشر (validation فقط) |
| **Web Booking** | `app/Http/Controllers/BookingController.php` | `create()` | ✅ `BookingService::createBooking()` |

### 2.2 البيانات المخزنة حالياً في `appointments` table

| الحقل | القيمة عند API Online | القيمة عند Dashboard |
|-------|----------------------|---------------------|
| `payment_method` | `cash` أو `online` | `cash` (fix) |
| `payment_status` | `PAID_ONSTIE_CASH` أو `PENDING` | `PENDING` |
| `created_status` | `1` للـ cash، `0` للـ online | `1` |
| `status` | `PENDING` (0) | `PENDING` (0) |

### 2.3 المشكلة الحالية
- **لا يوجد حقل** يحدد مصدر الحجز بشكل صريح
- لا يمكن التمييز بين حجز Dashboard (in_person) وحجز API بقيمة `payment_method=cash`

---

## 3. الحل المقترح

### 3.1 الفكرة
إضافة حقل `booking_source` إلى جدول `appointments` من نوع **string enum** بقيمتين:
- `online` — للحجوزات القادمة من API (التطبيق/العميل)
- `in_person` — للحجوزات القادمة من Dashboard أو Filament (الموظف/المدير)

### 3.2 لماذا String وليس Int؟
- readability في الـ database
- توافق مع الـ backed enums في PHP 8.1
- لا يحتاج mapping عند قراءة الـ raw data

### 3.3 أين سيظهر هذا الحقل؟

| المكان | كيف سيظهر |
|--------|-----------|
| Filament Table | Column `booking_source` مع badge أزرق/رمادي |
| Filament View/Edit | حقل معروض في Infolist |
| Staff Dashboard Cards | أيقونة صغيرة (🌐 أو 🏪) على بطاقة الموعد |
| API JSON Response | حقل `booking_source` في AppointmentResource |
| Database | `appointments.booking_source` |
| Reports | كأساس للفلترة والتجميع |

---

## 4. خطة التنفيذ التفصيلية

### 4.1 إنشاء BookingSource Enum

**الملف الجديد:** `app/Enum/BookingSource.php`

```php
<?php

namespace App\Enum;

enum BookingSource: string
{
    case ONLINE = 'online';
    case IN_PERSON = 'in_person';

    public function label(): string
    {
        return match($this) {
            self::ONLINE => 'Online',
            self::IN_PERSON => 'In-Person',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::ONLINE => 'primary',    // أزرق في Filament
            self::IN_PERSON => 'gray',    // رمادي في Filament
        };
    }

    /**
     * أيقونات Heroicon المستخدمة في Dashboard
     */
    public function heroicon(): string
    {
        return match($this) {
            self::ONLINE => 'heroicon-o-globe-alt',
            self::IN_PERSON => 'heroicon-o-store-front',
        };
    }

    /**
     * رموز HTML للعرض في Dashboard timeline
     */
    public function htmlIcon(): string
    {
        return match($this) {
            self::ONLINE => '🌐',
            self::IN_PERSON => '🏪',
        };
    }
}
```

**لماذا هذا الملف؟**
- يوفر مصدر واحد للحقيقة (single source of truth) لقيم booking_source
- يحتوي على دوال مساعدة للعرض (label, badgeColor, heroicon)
- يضمن type safety في كل places اللي تستخدم الحقل

---

### 4.2 إنشاء Migration

**الملف الجديد:** `database/migrations/YYYY_MM_DD_HHMMSS_add_booking_source_to_appointments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('booking_source', 20)
                ->default('in_person')
                ->after('created_status')
                ->comment('online: from API/mobile app, in_person: from staff dashboard or filament admin');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('booking_source');
        });
    }
};
```

**لماذا هذا الملف؟**
- يضيف الحقل الفعلي إلى جدول `appointments`
- قيمة افتراضية `in_person` لأن المسارات الداخلية كانت موجودة قبل API
- يوضع بعد `created_status` تنظيمياً

---

### 4.3 تحديث Appointment Model

**الملف:** `app/Models/Appointment.php`

#### التعديل 1: إضافة `booking_source` إلى `$fillable`
```
السطر: ~37
الإضافة: 'booking_source',
```

```php
protected $fillable = [
    'number',
    'customer_id',
    'provider_id',
    'customer_name',
    'customer_email',
    'customer_phone',
    'appointment_date',
    'start_time',
    'end_time',
    'duration_minutes',
    'subtotal',
    'tax_amount',
    'total_amount',
    'status',
    'payment_method',
    'cancellation_reason',
    'cancelled_at',
    'notes',
    'payment_status',
    'created_status',
    'booking_source', // ← إضافة
];
```

#### التعديل 2: إضافة `booking_source` إلى `$casts`
```
السطر: ~50
الإضافة: 'booking_source' => BookingSource::class,
```

```php
protected $casts = [
    'appointment_date' => 'datetime',
    'start_time' => 'datetime',
    'end_time' => 'datetime',
    'cancelled_at' => 'datetime',
    'subtotal' => 'decimal:2',
    'tax_amount' => 'decimal:2',
    'total_amount' => 'decimal:2',
    'status' => AppointmentStatus::class,
    'payment_status' => PaymentStatus::class,
    'duration_minutes' => 'integer',
    'booking_source' => BookingSource::class, // ← إضافة
];
```

#### التعديل 3: إضافة use statement
```
السطر: ~7 (بعد الـ namespace)
الإضافة: use App\Enum\BookingSource;
```

**لماذا هذه التعديلات؟**
- `$fillable` يسمح بتمرير `booking_source` عند `Appointment::create()`
- `$casts` يضمن تحويل القيمة تلقائياً إلى `BookingSource` enum عند القراءة

---

### 4.4 تحديث BookingService

**الملف:** `app/Services/BookingService.php`

#### التعديل: إضافة `booking_source` إلى `Appointment::create()` داخل transaction

**المكان:** السطر ~90-100 (داخل `DB::transaction` في `createBooking()`)

**الكود الحالي:**
```php
$appointment = Appointment::create([
    'number' => $this->generateAppointmentNumber(),
    'customer_id' => $customer?->id ?? null,
    'provider_id' => $firstService['provider_id'],
    'appointment_date' => $date,
    'start_time' => Carbon::parse($date . ' ' . $firstService['start_time']),
    'end_time' => Carbon::parse($date . ' ' . $preparedServices[count($preparedServices) - 1]['end_time']),
    ...
    'created_status' => $createdStatus,
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
]);
```

**الكود بعد التعديل:**
```php
$appointment = Appointment::create([
    ...
    'created_status' => $createdStatus,
    'booking_source' => $bookingData['booking_source'] ?? 'in_person',
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
]);
```

**المنطق:** نستخرج `booking_source` من `$bookingData` بقيمة افتراضية `in_person` — هكذا تعمل التعديلات بأقل تغيير، وأي كود يستدعي `createBooking()` ولا يمرر `booking_source` سيأخذ القيمة الافتراضية.

---

### 4.5 تحديث API BookingController

**الملف:** `app/Http/Controllers/Api/BookingController.php`

#### التعديل: إضافة `booking_source` إلى البيانات المرسلة لـ `createBooking()`

**المكان:** السطر ~30-33 داخل `store()`

**الكود الحالي:**
```php
$appointment = $this->bookingService->createBooking($customer, $request->validated());
```

**الكود بعد التعديل:**
```php
$bookingData = $request->validated();
$bookingData['booking_source'] = 'online';

$appointment = $this->bookingService->createBooking($customer, $bookingData);
```

**لماذا؟**
- جميع الحجوزات عبر API هي Online مهما كانت `payment_method`
- نضيف الحقل إلى `$validated` array قبل إرسالها إلى `BookingService`

---

### 4.6 تحديث StaffDashboard

**الملف:** `app/Livewire/StaffDashboard.php`

#### التعديل: إضافة `booking_source` إلى `saveBookingFromAlpine()`

**المكان:** السطر ~330 (داخل `saveBookingFromAlpine()` قبل استدعاء `createBooking()`)

**الكود الحالي:**
```php
$bookingData = [
    'services' => $validServices,
    'date' => $this->selectedDate,
    'payment_method' => 'cash',
    'is_confirmed' => true,
    'mark_as_paid' => false,
    'notes' => $data['notes'] ?? '',
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
];
```

**الكود بعد التعديل:**
```php
$bookingData = [
    'services' => $validServices,
    'date' => $this->selectedDate,
    'payment_method' => 'cash',
    'is_confirmed' => true,
    'mark_as_paid' => false,
    'notes' => $data['notes'] ?? '',
    'customer_name' => $customerName,
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'booking_source' => 'in_person', // ← إضافة
];
```

**لماذا؟**
- الحجوزات المنشأة من Staff Dashboard هي دائماً In-Person
- هذا يضمن أن `saveBookingFromAlpine()` ترسل المصدر الصحيح

---

### 4.7 تحديث Filament CreateAppointment

**الملف:** `app/Filament/Resources/Appointments/Pages/CreateAppointment.php`

#### التعديل: إضافة `booking_source` في `mutateFormDataBeforeCreate()`

**المكان:** داخل `mutateFormDataBeforeCreate()` (حيث تُجهّز البيانات قبل إنشاء السجل)

**الكود الحالي (تقريبي):**
```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    // ... تجهيز البيانات
    return $data;
}
```

**الكود بعد التعديل:**
```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['booking_source'] = 'in_person';
    // ... باقي التجهيزات
    return $data;
}
```

**لماذا؟**
- `CreateAppointment.php` لا يستخدم `BookingService`، بل ينشئ الـ record مباشرة
- لذلك نضيف `booking_source` في `mutateFormDataBeforeCreate()` لتكون موجودة وقت `create()`
- الحجوزات من Filament Admin هي In-Person

---

### 4.8 تحديث Web BookingController

**الملف:** `app/Http/Controllers/BookingController.php`

#### التعديل: إضافة `booking_source` إلى البيانات المرسلة لـ `createBooking()`

**المكان:** داخل `create()` method

**الكود الحالي:**
```php
$bookingData = [
    'services' => $request->input('services'),
    'date' => $request->input('date'),
    'notes' => $request->input('notes'),
    'payment_method' => $request->input('payment_method'),
];
```

**الكود بعد التعديل:**
```php
$bookingData = [
    'services' => $request->input('services'),
    'date' => $request->input('date'),
    'notes' => $request->input('notes'),
    'payment_method' => $request->input('payment_method'),
    'booking_source' => 'in_person', // ← إضافة (حجز عن طريق الـ web forms)
];
```

**لماذا؟**
- هذا الـ Controller خاص بالحجوزات عبر واجهة ويب داخلية
- تعتبر In-Person لأنها من موظف/مدير

---

### 4.9 تحديث Filament AppointmentResource

هذا يتضمن 3 ملفات فرعية:

#### 4.9.1 تحديث AppointmentsTable

**الملف:** `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php`

**التعديل:** إضافة عمود `booking_source` بعد عمود `status`

```php
use App\Enum\BookingSource;

// داخل configure():
$table->columns([
    // ... الأعمدة الموجودة
    TextColumn::make('booking_source')
        ->label('Source')
        ->badge()
        ->color(fn (BookingSource $state): string => $state->badgeColor())
        ->formatStateUsing(fn (BookingSource $state): string => $state->label())
        ->sortable()
        ->searchable(false)
        ->after('status'), // ← بعد عمود الحالة
]);
```

**أو إذا كانت Filament 4 تفضل استخدام Enum:**
```php
TextColumn::make('booking_source')
    ->label('Source')
    ->enum(BookingSource::class)
    ->badge()
    ->color(fn (BookingSource $state): string => $state->badgeColor())
    ->sortable()
    ->after('status'),
```

**لماذا؟**
- يوفر indication بصري فوري في قائمة المواعيد
- الـ badge مع الألوان يسهل التمييز السريع
- `sortable` يسمح بترتيب المواعيد حسب المصدر

#### 4.9.2 تحديث AppointmentInfolist

**الملف:** `app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php`

**التعديل:** إضافة حقل `booking_source` في مكان مناسب

```php
TextEntry::make('booking_source')
    ->label('Booking Source')
    ->badge()
    ->color(fn (BookingSource $state): string => $state->badgeColor())
    ->formatStateUsing(fn (BookingSource $state): string => $state->label()),
```

**لماذا؟**
- يظهر مصدر الحجز في صفحة عرض التفاصيل
- يساعد المدير في معرفة من أين جاء الحجز

#### 4.9.3 تحديث AppointmentForm

**الملف:** `app/Filament/Resources/Appointments/Schemas/AppointmentForm.php`

**التعديل:** إضافة حقل `booking_source` للقراءة فقط (أو مخفي)

```php
// إما حقل معطل للقراءة فقط:
Select::make('booking_source')
    ->label('Booking Source')
    ->options([
        'online' => 'Online',
        'in_person' => 'In-Person',
    ])
    ->disabled()
    ->default('in_person'),

// أو حقل Hidden:
Hidden::make('booking_source')
    ->default('in_person'),
```

**لماذا؟**
- عند إنشاء حجز يدوياً من Filament، يجب أن يكون المصدر `in_person`
- لا نريد أن يغير المدير المصدر يدوياً (لذلك `disabled`)
- الحقل المخفي أسهل، لكن المعطل يعطي transparency

---

### 4.10 تحديث DashboardService

**الملف:** `app/Services/DashboardService.php`

#### التعديل 1: تضمين `booking_source` في بيانات appointments

**المكان:** داخل `getAppointmentsForDate()` أو أي method ترجع بيانات appointments للـ timeline

**الكود الحالي (تقريبي):**
```php
$appointments = Appointment::with([...])
    ->whereDate('appointment_date', $date)
    ->whereNotIn('status', [AppointmentStatus::USER_CANCELLED, AppointmentStatus::ADMIN_CANCELLED])
    ->get();
```

**لا يحتاج تعديل** — لأن `booking_source` هو جزء من `$casts` وسيُحمّل تلقائياً مع كل Appointment.

#### التعديل 2: إضافة `booking_source` إلى `getProvidersWithStatus()` أو return shape

**المكان:** التأكد من أن `getProvidersWithStatus()` أو أي method ترجع بيانات appointment تحتوي على `booking_source` في الـ array

إذا كانت `getAppointmentsForDate()` ترجع Eloquent models مباشرة، فـ `booking_source` متاح تلقائياً لأن الـ cast سيعمل.

إذا كانت هناك method تحوّل الـ appointments إلى array يدوياً، يجب إضافة `booking_source` إليها.

**لماذا؟**
- الـ StaffDashboard يعتمد على `DashboardService` في جلب بيانات اليوم
- الحقل يجب أن يصل إلى الـ View لكي نعرض الأيقونة

---

### 4.11 تحديث StaffDashboard Timeline View

**الملف:** `resources/views/livewire/staff-dashboard.blade.php`

#### التعديل: إضافة أيقونة source على بطاقة الموعد (appointment card)

**المكان:** داخل loop عرض appointment cards (حيث `$appointment` data)

**الكود الحالي (تقريبي):**
```blade
<div class="appointment-card" ...>
    <div class="card-header">
        <span class="customer-name">{{ $appointment['customer_name'] }}</span>
        <span class="time">{{ $appointment['start_time'] }}</span>
    </div>
    <div class="card-body">
        <span class="services">{{ $appointment['services'] }}</span>
    </div>
</div>
```

**الكود بعد التعديل:**
```blade
<div class="appointment-card" ...>
    <div class="card-header">
        <span class="customer-name">{{ $appointment['customer_name'] }}</span>
        <span class="time">{{ $appointment['start_time'] }}</span>
        @if(isset($appointment['booking_source']))
            <span class="booking-source-icon" 
                  title="{{ $appointment['booking_source'] === 'online' ? 'Online Booking' : 'In-Person Booking' }}">
                {{ $appointment['booking_source'] === 'online' ? '🌐' : '🏪' }}
            </span>
        @endif
    </div>
    <div class="card-body">
        <span class="services">{{ $appointment['services'] }}</span>
    </div>
</div>
```

**لماذا؟**
- العرض البصري المباشر على الـ timeline هو أسرع طريقة للموظف لمعرفة مصدر الحجز
- الأيقونة صغيرة ولا تزعج readability
- الـ tooltip يعطي توضيح إضافي

#### التعديل المحتمل 2: إضافة Tooltip أو Badge صغير

بدلاً من الأيقونة فقط، يمكن استخدام badge صغير ملون:
```blade
@if(isset($appointment['booking_source']))
    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium 
        {{ $appointment['booking_source'] === 'online' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
        {{ $appointment['booking_source'] === 'online' ? 'Online' : 'In-Shop' }}
    </span>
@endif
```

---

### 4.12 تحديث API AppointmentResource

**الملف:** `app/Http/Resources/AppointmentResource.php`

#### التعديل: إضافة `booking_source` إلى array المرسلة في الـ Response

**المكان:** داخل `toArray()` method

**الكود الحالي (تقريبي):**
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'number' => $this->number,
        // ... بقية الحقول
        'can_cancel' => $this->can_cancel,
    ];
}
```

**الكود بعد التعديل:**
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'number' => $this->number,
        // ... بقية الحقول
        'booking_source' => $this->booking_source?->value, // ← إضافة
        'can_cancel' => $this->can_cancel,
    ];
}
```

**لماذا؟**
- العميل في التطبيق قد يهتم بمعرفة إن كان الحجز تم عبر التطبيق أم في المحل
- مفيد للـ frontend لعرض icon مختلف
- الـ `?->value` ترجع القيمة الخام (`'online'` أو `'in_person'`)

إذا كان هناك Resource collection أو response structure آخر، يجب تحديثه بنفس الطريقة.

---

## 5. ملخص الملفات المتغيرة

### ملفات جديدة (2)

| # | الملف | الوصف |
|---|-------|-------|
| 1 | `app/Enum/BookingSource.php` | Enum جديد بقيمتي `online` و `in_person` مع دوال label, badgeColor, heroicon |
| 2 | `database/migrations/XXXX_XX_XX_XXXXXX_add_booking_source_to_appointments_table.php` | Migration لإضافة حقل `booking_source` من نوع string |

### ملفات معدلة (8)

| # | الملف | نوع التعديل | التفاصيل |
|---|-------|-------------|----------|
| 1 | `app/Models/Appointment.php` | إضافة | `booking_source` إلى `$fillable` + `$casts` + use statement |
| 2 | `app/Services/BookingService.php` | إضافة سطر | `'booking_source' => $bookingData['booking_source'] ?? 'in_person'` داخل transaction |
| 3 | `app/Http/Controllers/Api/BookingController.php` | إضافة 2 سطر | `$bookingData['booking_source'] = 'online'` قبل استدعاء createBooking |
| 4 | `app/Livewire/StaffDashboard.php` | إضافة سطر | `'booking_source' => 'in_person'` في bookingData داخل saveBookingFromAlpine |
| 5 | `app/Filament/Resources/Appointments/Pages/CreateAppointment.php` | إضافة سطر | `$data['booking_source'] = 'in_person'` داخل mutateFormDataBeforeCreate |
| 6 | `app/Http/Controllers/BookingController.php` | إضافة سطر | `'booking_source' => 'in_person'` في bookingData داخل create() |
| 7 | `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php` | إضافة Column | عمود booking_source مع badge و color |
| 8 | `app/Filament/Resources/Appointments/Schemas/AppointmentInfolist.php` | إضافة Entry | حقل عرض booking_source في صفحة التفاصيل |
| 9 | `app/Filament/Resources/Appointments/Schemas/AppointmentForm.php` | إضافة Field | حقل معطل أو مخفي لـ booking_source |
| 10 | `resources/views/livewire/staff-dashboard.blade.php` | تعديل HTML | إضافة أيقونة source على بطاقات المواعيد |
| 11 | `app/Http/Resources/AppointmentResource.php` | إضافة سطر | `'booking_source' => $this->booking_source?->value` |
| 12 | `app/Services/DashboardService.php` | محتمل | التأكد من أن appointment data arrays تحتوي على booking_source |

---

## 6. ملاحظات إضافية

### 6.1 ترتيب التنفيذ المقترح

```
الخطوة 1: إنشاء BookingSource Enum
الخطوة 2: إنشاء Migration
الخطوة 3: تشغيل Migration (php artisan migrate)
الخطوة 4: تحديث Appointment Model
الخطوة 5: تحديث BookingService
الخطوة 6: تحديث API BookingController
الخطوة 7: تحديث StaffDashboard
الخطوة 8: تحديث Filament CreateAppointment
الخطوة 9: تحديث Web BookingController
الخطوة 10: تحديث Filament Table + Infolist + Form
الخطوة 11: تحديث StaffDashboard Blade view
الخطوة 12: تحديث API AppointmentResource
الخطوة 13: تحديث DashboardService (إن لزم)
الخطوة 14: اختبار جميع مسارات الحجز
```

### 6.2 اختبارات يدوية بعد التنفيذ

| # | الاختبار | المتوقع |
|---|---------|---------|
| 1 | إنشاء حجز عبر API مع `payment_method=cash` | `booking_source = online` |
| 2 | إنشاء حجز عبر API مع `payment_method=online` | `booking_source = online` |
| 3 | إنشاء حجز عبر Staff Dashboard | `booking_source = in_person` |
| 4 | إنشاء حجز عبر Filament Admin | `booking_source = in_person` |
| 5 | التأكد من ظهور badge صحيح في Filament table | Online أزرق، In-Person رمادي |
| 6 | التأكد من ظهور أيقونة على بطاقة الموعد في Dashboard | 🌐 أو 🏪 |
| 7 | التأكد من وجود `booking_source` في API JSON response | الحقل موجود بقيمة string |

### 6.3 أسئلة مفتوحة للمستقبل

- هل نريد إضافة Filter في Filament table حسب `booking_source`؟
- هل نريد إضافة Chart/Statistic في Dashboard أو Filament للإحصائيات حسب المصدر؟
- هل نريد إرسال `booking_source` في إشعارات الـ push notification؟
- هل نريد استخدام `booking_source` في تقارير الـ Print/logs؟

---

> **انتهت خطة التنفيذ** — هذه وثيقة تخطيط فقط. تم تحليل النظام وفهم جميع نقاط اللمس (touch points) التي تحتاج تغييرًا لتمييز مصدر الحجز.
