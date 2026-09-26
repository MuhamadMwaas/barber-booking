# RM-01 — النقر على مزوّد في صفحة الخدمة يعطي `Call to a member function isEmpty() on null`

> **التاريخ:** 25 سبتمبر 2026
> **المصدر:** خطأ 500 في `/admin/services/1` عند النقر على صف مزوّد في جدول "مزودو الخدمة"
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بالاختبارات** (أُعيد إنتاج الخطأ نفسه على الكود القديم، السطر `ServiceInfolist.php:145`)
> **الاختبارات:** 6 اختبارات جديدة في `tests/Feature/Filament/RelationManagerRecordClickTest.php` (13 assertion)

---

## 1. العَرَض

- صفحة عرض الخدمة نفسها تعمل.
- عند **النقر على صف مزوّد** في جدول المزوّدين أسفل الصفحة، يرسل Livewire طلباً لفتح نافذة (modal)، فينهار:

```
Call to a member function isEmpty() on null
app/Filament/Resources/Services/Schemas/ServiceInfolist.php:145
```

- سجلّ الاستعلامات في صفحة الخطأ يكشف السبب: السجلّ الذي فُتحت له النافذة هو **`users.id = 5`** (المزوّد)، ثم تُبنى خيارات **ServiceForm** (`service_categories` + المستخدمون بدور `provider`)، ثم يُرسم **ServiceInfolist** على هذا المستخدم.

---

## 2. السبب الجذري

في [`ProvidersRelationManager.php`](../../app/Filament/Resources/Services/RelationManagers/ProvidersRelationManager.php) كان:

```php
protected static ?string $relatedResource = ServiceResource::class;
```

`$relatedResource` في Filament 4 معناه: "سجلات هذا الجدول تتبع هذا الـ Resource". لكن صفوف هذا الجدول **مزوّدون (`User`)**، وليست خدمات.

### السلسلة خطوة بخطوة (من كود Filament)

| # | المكان | ما يحدث |
|---|--------|---------|
| 1 | `InteractsWithRelationshipTable::makeTable()` سطر 188 | يستدعي `ServiceResource::configureTable($table)`، فيُطبَّق `ServicesTable` على جدول المزوّدين، ومعه `ViewAction` و`EditAction` و`Restore` و`ForceDelete`. |
| 2 | `HasRecordActions::recordActions()` | الـ RM يستدعي `->recordActions([...])` الخاصة به. هذا **يفرّغ القائمة الظاهرة فقط**، و`cacheAction()` يبقى محتفظاً بـ `ViewAction`. النتيجة إجراء View **مخفي لكنه مسجَّل**. |
| 3 | `makeTable()` → `recordAction` سطر 114 | عند النقر على الصف يبحث Filament عن `view` ثم `edit`، فيجد الـ View المخفي. لا يُبنى له رابط لأن موديل الجدول `User` ≠ `Service`، فيُفتح كنافذة. |
| 4 | `RelationManager::getDefaultActionSchemaResolver()` سطر 367 | محتوى النافذة = `infolist(form(...))`، وكلاهما من **ServiceResource**. |
| 5 | `ServiceInfolist.php:145` | `$record->providers` على `User` قيمته `null` (لا توجد علاقة بهذا الاسم)، فيحدث الانهيار. |

> ملاحظة: على صفحات **View** يكون الـ Relation Manager للقراءة فقط افتراضياً (`isReadOnly()`)، لذلك أزرار Edit/Attach/Detach التي عرّفناها لا تظهر هناك، وهذا مقصود ولم يتغيّر.

### نفس الخطأ في مكانين آخرين

| الملف | الصفوف الفعلية | `$relatedResource` القديم | النتيجة عند النقر |
|-------|----------------|---------------------------|-------------------|
| `Users/RelationManagers/ServicesRelationManager.php` | Services | `UserResource` | UserInfolist على Service |
| `Users/RelationManagers/CustomerAppointmentsRelationManager.php` | Appointments | `UserResource` | UserInfolist على Appointment |

---

## 3. الإصلاح

في الملفات الثلاثة:

1. **حذف `$relatedResource`.** هذا يوقف حقن الجدول والفورم والـ infolist من Resource خاطئ.
2. **`recordUrl` صريح:** النقر على الصف ينتقل إلى صفحة السجل في الـ Resource الصحيح:
   - مزوّد ← `ProviderResource` view، **فقط** إذا كان للمستخدم دور `provider` (غير ذلك سيعطي 404، لأن `ProviderResource::getEloquentQuery()` يفلتر بـ `role('provider')`) **وإذا** كان يملك `Provider:view`.
   - خدمة ← `ServiceResource` view إذا كان يملك `Service:view`.
   - موعد ← `AppointmentResource` view إذا كان يملك `Appointment:view`.
   - إذا لم يكن يملك الصلاحية فلا يكون الصف رابطاً (بدل أن يفتح صفحة ممنوعة).
3. **`modelLabel` / `pluralModelLabel`** من الـ Resource الصحيح، لأنها كانت تأتي من `$relatedResource`.
4. **عنوان تبويب المزوّدين:** `getTitle()` يُرجع `resources.service.providers`. كان يعرض اسم "الخدمات" (من `$relatedResource`)، وبدون تعريف صريح كان سيعرض كلمة `Providers` الإنجليزية.

لم يتغيّر `ServiceInfolist`. هو صحيح لسجل `Service`، وإضافة null-safe هناك كانت ستُخفي أخطاء إعداد مستقبلية بدل أن تكشفها.

---

## 4. التحقق

- **6 اختبارات جديدة** تنجح: لا يوجد إجراء `view` مخفي، ورابط الصف صحيح لكل RM، ولا يوجد رابط بدون صلاحية أو لمستخدم ليس مزوّداً.
- **أُعيد إنتاج الخطأ** بإرجاع السطر القديم مؤقتاً: `mountTableAction('view', $provider)` ← `Call to a member function isEmpty() on null` في `ServiceInfolist.php:145` تماماً. ثم أُعيد الإصلاح.
- أُضيف `Feature/Filament` إلى `tests/Pest.php`.
- المجموعة الكاملة: 520 نجاح و18 فشلاً. 12 منها هي الـ baseline المعروف، و6 في `PhoneNumberValidationTest` تخص عمل أرقام الهاتف غير المحفوظ في الشجرة، ولا علاقة لها بهذا التعديل.

---

## 5. مشكلة معروفة لم تُصلَح هنا

`ServicesRelationManager` (جدول المزوّد) فيه `CreateAction::make()` في `headerActions`. قبل الإصلاح كان يفتح **فورم المستخدم** (من `UserResource`) لإنشاء خدمة، وبعد الإصلاح يفتح **فورماً فارغاً**. في الحالتين الزر لا يعمل بشكل صحيح. يظهر فقط في صفحة Edit للمستخدم. يحتاج قراراً: `AttachAction` (ربط خدمة موجودة، كما في صفحة الخدمة) أو حذفه.

---

## 6. القاعدة للمستقبل

> **لا تضع `$relatedResource` في Relation Manager إلا إذا كانت صفوف جدوله هي نفس موديل ذلك الـ Resource.**
> هذا الإعداد يحقن `configureTable()` و`form()` و`infolist()` الخاصة بالـ Resource، والإجراءات المحقونة تبقى مسجّلة حتى لو استبدلتَ `recordActions()`.
