# DEL-01 — زر حذف الموعد في لوحة Filament يسقط بخطأ 1451

> **التاريخ:** 25 سبتمبر 2026
> **المصدر:** خطأ 500 عند الضغط على "حذف" في `/admin/appointments/9/edit`
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بالاختبارات** (لم يُجرَّب الزر في المتصفح بعد)
> **الاختبارات:** 14 اختباراً جديداً تمر (47 assertion) في `tests/Feature/Booking/AppointmentDeletionTest.php`

---

## 1. الخطأ

```
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row:
a foreign key constraint fails (`appointment_services`, CONSTRAINT
`appointment_services_appointment_id_foreign` ...) SQL: delete from `appointments` where `id` = 9
```

## 2. السبب

زر الحذف في [`EditAppointment.php`](../../app/Filament/Resources/Appointments/Pages/EditAppointment.php) كان `DeleteAction::make()` بلا تخصيص، فكان ينفّذ `$record->delete()` مباشرة. لكن الموعد له سجلات تابعة محمية بقيود **RESTRICT**:

| الجدول | عند الحذف |
|---|---|
| `appointment_services.appointment_id` | **RESTRICT** ← الخطأ الظاهر |
| `invoices.appointment_id` | **RESTRICT** ← كان سيظهر مباشرة بعد الأول |
| `invoice_items.invoice_id` | **RESTRICT** |
| `appointment_reminders` / `appointment_colors` | CASCADE |
| `appointments.parent_appointment_id` | SET NULL |

الداشبورد (`StaffDashboard::deleteAppointment`) كان المسار الوحيد الصحيح: يتحقق من القواعد ثم يحذف التوابع داخل Transaction. أما Filament فلم يفعل أيّاً من ذلك.

### مشاكل مرافقة اكتُشفت أثناء التحليل

1. **لا Transaction:** `DeleteAction` لا يفتح Transaction افتراضياً، فكان hook الـ `deleting` يلغي تذكيرات الموعد ثم يفشل الحذف، **فيبقى الموعد بلا تذكير**.
2. **الحذف الجماعي في الجدول لم يعمل أصلاً:** `Action` عادي يقرأ `$records` دون `accessSelectedRecords()`، فيرمي `LogicException` في Filament 4 قبل الوصول لقاعدة البيانات. ولو وصل لسقط بنفس الخطأ 1451.
3. **صلاحية `:delete` لم تكن مُطبَّقة على أي زر حذف في اللوحة:** Filament 4 لا يستدعي `canDelete()` / `canDeleteAny()` لتفويض `DeleteAction` و`DeleteBulkAction`، بل يستدعي `getDeleteAuthorizationResponse()` و`getDeleteAnyAuthorizationResponse()`، وهاتان تعودان للـ Policy. المشروع بلا Policies، فكان كل من يدخل اللوحة يرى زر الحذف، مثلاً المدير (manager) لا يملك `Appointment:delete` لكنه كان يرى الزر.

## 3. الإصلاح

### 3.1 خدمة واحدة للحذف: `AppointmentDeletionService`

[`app/Services/AppointmentDeletionService.php`](../../app/Services/AppointmentDeletionService.php) أصبح **المسار الوحيد** لحذف موعد، ويستدعيه:
- زر الحذف في صفحة تعديل الموعد (Filament).
- الحذف الجماعي في جدول المواعيد (Filament).
- `StaffDashboard::deleteAppointment()`.

**القواعد** هي نفس قواعد الداشبورد السابقة:
- المدفوع لا يُحذف (`payment_status` = 1 أو 2 أو 3).
- المكتمل لا يُحذف (`COMPLETED`).
- الأب الذي له ابن غير ملغى لا يُحذف (`canBeCancelledOrDeleted()`).

**إضافة واحدة:** الموعد الذي فاتورته ليست `DRAFT` يُعامل كمدفوع حتى لو كان `payment_status` غير متسق معه، لأن حذف فاتورة مرقّمة يترك فجوة في الترقيم المتسلسل.

**الترتيب داخل Transaction واحدة:** `invoice_items` ← `payments` الفاتورة ← `invoice` ← `appointment_services` ← `appointment`. التذكيرات والألوان تُحذف تلقائياً بالـ CASCADE.

**القفل:** تُقفل صفوف مجموعة الموعد (الأب أولاً ثم المجموعة مرتبة بالـ id) بنفس ترتيب `InvoiceFinalizationService::finalizeAppointmentPayment()`، ثم تُفحص القواعد **على الصفوف المقفلة**. بذلك لا يمكن أن يُحذف موعد دُفع للتو.

**بعد الـ commit:** إن كان المحذوف ابناً تُعاد بناء فاتورة الأب المجمّعة، ويُسجَّل الفشل في السجل دون رميه، كما كان الداشبورد يفعل.

### 3.2 الحذف الجماعي: الكل أو لا شيء

إن كان في التحديد موعد واحد ممنوع، **لا يُحذف أي موعد**، ويظهر إشعار يسرد كل موعد ممنوع برقمه وسببه. الاستثناء `AppointmentNotDeletableException` يحمل قائمة الممنوعات كاملة.

> ملاحظة: تحديد أب مع أبنائه النشطين في نفس الطلب يُرفض أيضاً، لأن الأب له أبناء نشطون. احذف الأبناء أولاً، أو ألغِهم ثم احذف الأب.

### 3.3 الصلاحيات: `NavigationDefaultAccess`

أُضيفت إلى [`app/Traits/NavigationDefaultAccess.php`](../../app/Traits/NavigationDefaultAccess.php):
- `canDelete(Model $record)`، ويتحقق من `<Resource>:delete`.
- `getDeleteAuthorizationResponse()` و`getDeleteAnyAuthorizationResponse()`، وتمرّان عبر `canDelete()` / `canDeleteAny()`.

**هذا يمس كل الـ Resources التي تستخدم الـ trait**: أصبح زر الحذف (الفردي والجماعي) مخفياً عمّن لا يملك صلاحية `:delete` لذلك الـ Resource. حسب الصلاحيات المزروعة حالياً، `:delete` ممنوحة لـ `SuperAdmin` و`admin` فقط، وأيضاً للـ `manager` في `ProviderScheduledWork`.

`RoleResource` يعرّف `canDelete()` خاصاً به (حماية الأدوار الأساسية)، وهذا الـ override أصبح فعّالاً الآن. **لكنه لا يفحص `Role:delete`**، وهذا كان الحال قبل الإصلاح أيضاً.

## 4. ما لم يتغير عمداً

- **قيود RESTRICT باقية كما هي.** هي شبكة الأمان التي تمنع أي مسار مستقبلي من حذف فاتورة بصمت. الاختبار `it still refuses a bare model delete` يثبت ذلك.
- **الحذف ما زال حذفاً نهائياً** (لا SoftDeletes على `appointments`).

## 5. الملفات

| الملف | التغيير |
|---|---|
| `app/Services/AppointmentDeletionService.php` | جديد |
| `app/Exceptions/AppointmentNotDeletableException.php` | جديد |
| `app/Filament/Resources/Appointments/Pages/EditAppointment.php` | `DeleteAction->using()` عبر الخدمة |
| `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php` | الحذف الجماعي عبر الخدمة + `authorize` + `accessSelectedRecords` |
| `app/Livewire/StaffDashboard.php` | `deleteAppointment()` يستدعي الخدمة |
| `app/Traits/NavigationDefaultAccess.php` | تفويض الحذف الفعلي |
| `lang/{ar,en,de}/resources.php` | `delete_blocked_title`, `bulk_delete_blocked_title`, `bulk_deleted` |
| `lang/{ar,en,de}/dashboard.php` | `appointment_modal.cannot_delete_completed` |
| `tests/Feature/Booking/AppointmentDeletionTest.php` | 14 اختباراً |

## 6. التحقق

- `php artisan test tests/Feature/Booking/AppointmentDeletionTest.php`: 14/14.
- المجموعة الكاملة: 514 نجاح و18 فشلاً.
  - 12 منها هي الفشل المعروف مسبقاً (Fiskaly، رفع الصورة، حذف الحساب، الصفحة الترحيبية، عرض كلمة المرور، rate limit).
  - 6 في `PhoneNumberValidationTest` سببها تعديل غير مُلتزَم على `app/Rules/PhoneNumber.php` خارج هذا الإصلاح.
- SQLite في الاختبارات يطبّق قيود المفاتيح الأجنبية، فالخطأ الأصلي يتكرر فيها. لكن `lockForUpdate()` لا يعمل على SQLite، لذا القفل نفسه غير مُختبَر.
