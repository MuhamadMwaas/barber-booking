# Provider Self-Service Dashboard + Attendance Tracking — Implementation Reference

> **التاريخ:** 2026-06-03
> **النطاق:** تمكين مقدّم الخدمة (`role = provider`) من تسجيل الدخول واستخدام `StaffDashboard`، مع
> فلتر "حجوزاتي"، تنبيه ملكية الحجز، نظام صلاحيات دقيق لكل زر، وتتبّع دوام كامل (Check-in/Check-out)
> مع واجهة مراجعة للأدمن في Filament.
>
> هذه الوثيقة تشرح **كل ملف أُضيف أو عُدِّل**، **ماذا فيه**، و**كيف يخدم المهمة** — بالترتيب المنطقي
> للتنفيذ. اقرأها مع [docs/STAFF_DASHBOARD.md](STAFF_DASHBOARD.md) (المرجع العميق للوحة) و[Agent.md](../Agent.md).

---

## 0. الهدف وملخص القرارات

### الهدف الوظيفي
تحويل `StaffDashboard` من أداة **admin-only** إلى منصة تشغيل للطاقم: المقدّم يسجّل دخوله، يرى جدوله،
يصفّي على حجوزاته، ينفّذ الأكشنات المسموح بها له، ويسجّل حضوره وانصرافه — بينما يراجع الأدمن سجلات
الدوام من Filament.

### القرارات المتفق عليها (من جلسة الأسئلة)
| # | القرار |
|---|--------|
| دخول | المقدّم يدخل من نفس شاشة Filament login، وبعد الدخول يُوجَّه إلى `/dashboard`. |
| Filament | المقدّم **يستطيع** أيضًا دخول `/admin`؛ ما يراه محكوم بصلاحياته. |
| رؤية | يرى **الفريق كله** افتراضيًا؛ زر **"حجوزاتي"** يضيّق العرض على عموده. |
| ملكية | فتح حجز ليس له → **تنبيه** "ليس حجزك"، لكنه يقدر ينفّذ كل شيء (حسب صلاحية `edit_others`). |
| صلاحيات | **كل زر/أكشن** له صلاحية قابلة للضبط من شاشة الأدوار (RoleResource). |
| الدوام | **جلسات متعددة باليوم** مسموحة؛ خارج الدوام مسموح **مع تحذير**؛ نسيان الانصراف يترك الجلسة مفتوحة **بلا job**. |
| التنبيه | "لم تسجّل حضورك اليوم" يُحسب **عند تحميل الصفحة فقط** (لا polling/live). |
| الأدمن | لا يملك Check-in؛ يرى سجلات الدوام في **Filament** (جدول عام + سجل لكل موظف من صفحته). |
| الفرع | الدوام **منسوب لفرع المقدّم** (`branch_id`). |

---

## 1. نموذج الصلاحيات (Permissions Catalog)

النظام يولّد الصلاحيات تلقائيًا من [PermissionsSeeder](../database/seeders/PermissionsSeeder.php)
وتظهر تلقائيًا كتابات في فورم الدور ([RoleForm](../app/Filament/Resources/Roles/Schemas/RoleForm.php)).
أضفنا مجموعتين:

### 1.1 `StaffDashboard:*` (15 صلاحية — كل واحدة = زر/أكشن)
| الصلاحية | تحكم |
|----------|------|
| `access` | الدخول للصفحة (تتحقّق منها الـ middleware). |
| `create_booking` | زر "Add Booking" + حفظ الحجز. |
| `add_service` | زر "Add Service" + إضافة خدمة لحجز قائم. |
| `edit_appointment` | زر "Save Changes" (تعديل وقت/مدة الموعد). |
| `edit_others` | **الشرط الإضافي** للتصرّف في حجز يخص مقدّمًا آخر. |
| `cancel_appointment` | زر "Cancel Appointment". |
| `delete_appointment` | زر "Delete". |
| `take_payment` | زر "Pay" + تنفيذ الدفع وإنهاء الفاتورة. |
| `print_invoice` | زر "Print Invoice". |
| `print_ticket` | زر "Print Order Ticket". |
| `manage_timeoff` | زر "Add Time Off" + حفظ الإجازة. |
| `manage_colors` | إضافة/حذف ألوان داخل الموعد. |
| `edit_notes` | حفظ ملاحظات العميل + ملاحظات المقدّم. |
| `post_message` | محرّر لوحة الرسائل (Bulletin composer). |
| `view_team` | رؤية أعمدة بقية المقدّمين؛ بدونها يُحصر المقدّم في عموده فقط (server-side). |

### 1.2 `ProviderAttendance:*` (6 صلاحيات — مولّدة آليًا من الـ Resource)
`access, view, create, edit, delete, force_delete` — لمراجعة/تصحيح سجلات الدوام في Filament.
تُمنح للـ admin/SuperAdmin (all) و`manager` و`provider` (access/view/edit فقط). المقدّم لا يحتاجها للداشبورد
(الحضور self-service)، لكنها متاحة لو رغبتَ بفتح الـ Resource له.

### 1.3 التوزيع الافتراضي على الأدوار
- **SuperAdmin / admin:** كل الصلاحيات (sync all).
- **manager:** كل `StaffDashboard:*` + `ProviderAttendance: access/view/edit`.
- **provider:** كل `StaffDashboard:*` (افتراضيًا — والأدمن يقلّص لاحقًا من شاشة الأدوار). **24 صلاحية إجمالًا** بعد إعادة الـ seed.

> **آلية مهمة:** بما أن الصلاحيات تُجمَّع في الفورم حسب البادئة قبل `:`، فإن إضافة أي ability جديدة إلى
> `PAGE_ABILITIES['StaffDashboard']` تظهر **تلقائيًا** كـ checkbox في تاب "StaffDashboard" — لا كود إضافي.

---

## 2. الملفات الجديدة (New Files)

### 2.1 `app/Filament/Auth/StaffLoginResponse.php` — توجيه ما بعد الدخول
- **ماذا:** يطبّق عقد `Filament\Auth\Http\Responses\Contracts\LoginResponse`. لو المستخدم `provider` خالص
  (بلا أي دور admin/manager/SuperAdmin) → `redirect()->to(route('staff.dashboard'))`؛ غير ذلك السلوك
  الافتراضي (`redirect()->intended(Filament::getUrl())`).
- **كيف يخدم المهمة:** يحقّق "عند دخول المقدّم يروح على StaffDashboard" دون شاشة دخول ثانية، ودون كسر
  توجيه الأدمن/المدير.

### 2.2 `app/Livewire/Concerns/InteractsWithDashboardPermissions.php` — طبقة التفويض
- **ماذا:** Trait يوفّر:
  - `isCurrentUserProvider()` / `currentProviderId()` — هوية المقدّم الحالي.
  - `dashCan($ability)` — فحص `StaffDashboard:$ability` مع تجاوز SuperAdmin.
  - `dashDeny($ability)` — حارس server-side يُرجع true ويُطلق toast خطأ عند المنع.
  - `canActOnAppointment($appt)` — قاعدة الملكية (المالك دائمًا؛ غير المالك يحتاج `edit_others`؛ الأدمن/المدير غير مقيَّدين).
  - `dashDenyOnAppointment($ability, $appt)` — يدمج فحص الصلاحية + الملكية.
- **كيف يخدم المهمة:** المصدر الوحيد للحقيقة في التفويض — يُستخدم في Blade (لإخفاء الأزرار) وفي كل
  method (للمنع الفعلي)، فلا يصبح إخفاء الزر هو خط الدفاع الوحيد.

### 2.3 `app/Models/ProviderAttendance.php` — موديل الجلسة
- **ماذا:** صف واحد = **جلسة دوام** (check-in → check-out). casts للتواريخ، علاقات `provider()`/`branch()`،
  scopes (`forUser`, `onDate`, `open`)، و accessors (`is_open`, `duration_minutes`).
- **كيف يخدم المهمة:** يمثّل وحدة الدوام؛ غياب unique على اليوم يسمح بجلسات متعددة (شفتين/استراحة).

### 2.4 `app/Services/AttendanceService.php` — منطق الدوام
- **ماذا:**
  - `checkIn($provider)` — يمنع جلسة ثانية مفتوحة **اليوم**، ينشئ صفًّا جديدًا، ويُرجع `outside_shift`
    (هل خارج الدوام المجدوَل؟).
  - `checkOut($provider)` — يغلق أحدث جلسة مفتوحة (مع حماية ضد انحراف الساعة).
  - `todayState($provider)` — `{status: none|open|closed, is_work_day, sessions_count, since, last_out}`.
  - `isScheduledWorkDay()` / `isWithinScheduledShift()` — يقرآن `ProviderScheduledWork`.
- **كيف يخدم المهمة:** كل قواعد الدوام في مكان واحد؛ المكوّن يستدعيه فقط. يدعم "جلسات متعددة"،
  "تحذير خارج الدوام"، و"يبقى مفتوحًا بلا انصراف".

### 2.5 `database/migrations/2026_06_03_000002_create_provider_attendances_table.php`
- **ماذا:** جدول `provider_attendances`: `user_id` (FK→users, cascade)، `branch_id` (FK→**`branchs`**, nullable,
  nullOnDelete)، `work_date` (date)، `check_in_at`، `check_out_at` (nullable)، `source`، `notes`، timestamps،
  فهارس `(user_id, work_date)` و`work_date`. **لا** unique على اليوم.
- **كيف يخدم المهمة:** التخزين القابل للتدقيق للدوام، جاهز للفرع.
- **ملاحظة حرجة (gotcha):** جدول الفروع اسمه **`branchs`** (خطأ إملائي تاريخي في المشروع)، لذا الـ FK
  مكتوب صراحة `->constrained('branchs')`؛ خلاف ذلك يفشل بـ `errno 150`.

### 2.6 Filament Resource: `app/Filament/Resources/ProviderAttendances/`
| الملف | الدور |
|-------|-------|
| `ProviderAttendanceResource.php` | Resource (group `staff`)، يستعمل `NavigationDefaultAccess` + `ResourceTranslation`. |
| `Schemas/ProviderAttendanceForm.php` | فورم تصحيح يدوي (provider/date/in/out/notes). |
| `Tables/ProviderAttendancesTable.php` | جدول: مقدّم/تاريخ/دخول/خروج/مدة/فرع + فلاتر (مقدّم، مدى تاريخ، جلسات مفتوحة/مغلقة). |
| `Pages/{List,Create,View,Edit}ProviderAttendance.php` | صفحات CRUD القياسية. |
- **كيف يخدم المهمة:** يحقّق "المحتوى يكون في Filament للأدمن، يُعرض كل يوم بيومه في جدول".

### 2.7 `app/Filament/Resources/Providers/RelationManagers/AttendancesRelationManager.php`
- **ماذا:** Relation manager على `attendances` يظهر داخل صفحة المقدّم (تاريخ/دخول/خروج/مدة + فلاتر +
  تعديل/حذف).
- **كيف يخدم المهمة:** "لما يفوت على موظف يعرف سجلات دوامه" — السجل في سياق الموظف نفسه.

### 2.8 الترجمات الجديدة
- `lang/{en,ar,de}/dashboard.php`: مفاتيح `my_bookings`, `permission_denied`, `not_your_booking`,
  `not_your_booking_denied`, وقسم `attendance.*` كامل.
- `lang/{en,ar,de}/resources.php`: قسم `provider_attendance.*` (عناوين/أعمدة/فلاتر الـ Resource).

---

## 3. الملفات المعدّلة (Modified Files)

### 3.1 `app/Models/User.php`
- **canAccessPanel()**: من `hasRole('admin')` → `hasAnyRole(['SuperAdmin','admin','manager','provider']) && is_active`.
  - *لماذا:* لفتح Filament للطاقم (القرار 1.2)؛ المحتوى يبقى محكومًا بالصلاحيات في كل Resource.
- **isProvider()** (جديدة): اختصار `hasRole('provider')` تستهلكه طبقة التفويض.
- **attendances()** (علاقة جديدة): `hasMany(ProviderAttendance)` — تغذّي RelationManager وtodayState.

### 3.2 `app/Providers/AppServiceProvider.php`
- ربط `LoginResponse::class → StaffLoginResponse::class` في `register()`.
  - *لماذا:* تفعيل التوجيه المخصّص بعد الدخول.

### 3.3 `app/Livewire/StaffDashboard.php` (التغيير الأكبر)
- **use** للـ trait الجديد + `public bool $onlyMine` + `public array $attendanceState`.
- **mount()**: يستدعي `refreshAttendanceState()` — حساب حالة الدوام **مرة واحدة عند التحميل** (لا في render/poll).
- **getTimelineDataFromProviders()**:
  - فلترة scoping موحّدة: لو المقدّم بلا `view_team` يُحصر في عموده (server-side)؛ وإلا فالـ toggle `onlyMine` يضيّق.
  - إضافة `is_owned` لكل بطاقة موعد.
- **حُرّاس server-side** على كل أكشن (المنع الفعلي + رسالة):
  `saveBookingFromAlpine/saveBooking → create_booking` · `updateAppointment → edit_appointment` ·
  `updateNotes/updateProviderNotes → edit_notes` · `addColor/removeColor → manage_colors` ·
  `cancelAppointment → cancel_appointment` · `deleteAppointment → delete_appointment` ·
  `processPayment → take_payment` · `saveTimeOff/saveTimeOffFromAlpine → manage_timeoff` (+ تقييد
  `user_id` لذات المقدّم) · `openAddServiceModal/confirmAddService → add_service` ·
  `printInvoiceForAppointment → print_invoice` · `printAppointmentTicket → print_ticket` ·
  `addMessage → post_message`. الأكشنات التي تخص موعدًا تمر أيضًا بفحص الملكية.
- **checkIn() / checkOut() / refreshAttendanceState()** (جديدة): تستدعي `AttendanceService` وتحدّث الـ snapshot.
- *كيف يخدم المهمة:* يطبّق فعليًا الفلترة، الملكية، الصلاحيات، والدوام على مستوى الخادم.

### 3.4 `resources/views/partials/staff-nav.blade.php`
- زرّا **Check-in/Check-out** (للمقدّم، حيث تتوفّر حالة الدوام) بجانب زر اللغة.
- تحويل **دائرة الأفتار** إلى dropdown card: صورة/اسم/إيميل + **حالة دوام اليوم** + زر **Logout**
  (POST إلى `filament.admin.auth.logout`).
- *كيف يخدم المهمة:* يحقّق "أزرار check-in/out فوق بجانب زر اللغة" و"كارد عند الضغط على الدائرة فيه
  الحالة وزر تسجيل الخروج".

### 3.5 `resources/views/livewire/staff-dashboard.blade.php`
- تمرير `attendanceState` للـ partial.
- **toggle "حجوزاتي"** في شريط أدوات التاريخ (للمقدّم الذي يملك `view_team`).
- **بانر "لم تسجّل حضورك اليوم"** أعلى الـ timeline (يوم عمل + بلا حضور)، قابل للإغلاق، من الـ snapshot (لا polling).
- **بانر ملكية** "ليس حجزك" أعلى مودال الموعد للمقدّم على حجز غير مملوك.
- **تغليف كل الأزرار** بـ `@if($this->dashCan(...))` (+ الملكية حيث يلزم): cancel/delete/add-service/print-invoice/
  print-ticket/pay/save-changes/save-notes/save-provider-notes/colors/Add Booking/Add Time Off/Bulletin composer.

### 3.6 `app/Filament/Resources/Providers/ProviderResource.php`
- تسجيل `AttendancesRelationManager` ضمن `getRelations()`.

### 3.7 `app/Filament/Resources/Roles/Schemas/RoleForm.php`
- أيقونتان لمجموعتي `StaffDashboard` و`ProviderAttendance` في فورم الأدوار (تجميل التابات).

### 3.8 `database/seeders/PermissionsSeeder.php`
- توسيع `PAGE_ABILITIES['StaffDashboard']` إلى الـ 15 ability (القسم 1.1).

### 3.9 `database/seeders/RoleSeeder.php`
- منح `provider` و`manager` صلاحيات `StaffDashboard:*` الجديدة، و`provider`/`manager` صلاحيات
  `ProviderAttendance: access/view/edit`.

---

## 4. التدفّقات (Flows)

### 4.1 الدخول
`/dashboard` ← (غير مسجّل) → `EnsureStaffDashboardAccess` يحوّل إلى Filament login → المصادقة تنجح لأن
`canAccessPanel` صار يسمح للطاقم → `StaffLoginResponse` يوجّه المقدّم إلى `/dashboard`.

### 4.2 "حجوزاتي" + الملكية
- المقدّم يرى الفريق (لأنه يملك `view_team`)، وزر "حجوزاتي" يضبط `onlyMine` → الـ timeline يعرض عموده فقط.
- بلا `view_team` → يُحصر في عموده دائمًا (server-side، لا يُلتف عليه من العميل).
- فتح حجز غير مملوك → بانر تنبيه؛ الأكشنات تظهر/تُنفَّذ فقط لو يملك `edit_others`.

### 4.3 الدوام
- **Check-in:** زر الحضور يفتح **نافذة تأكيد** تعرض **الوقت الحالي** و**آخر انصراف** قبل التأكيد
  (`openCheckInModal` → `confirmCheckIn`). ينشئ جلسة (مع تحذير لو خارج الدوام). جلسة مفتوحة أخرى اليوم تمنع تسجيلًا ثانيًا.
- **Check-out:** زر الانصراف يفتح **نافذة تأكيد** تعرض **وقت آخر حضور** و**مدة الشفت الناتجة** قبل التأكيد
  (`openCheckOutModal` → `confirmCheckOut`). يغلق أحدث جلسة مفتوحة. نسيانه يتركها مفتوحة ("Open").
- **سجل الدوام (popup):** زر بأيقونة ساعة في الـ nav (`openAttendanceHistoryModal`) يفتح نافذة بآخر **30 جلسة**
  (تاريخ/حضور/انصراف/مدة)، مصدرها `AttendanceService::recentSessions()`.
- **جلسات متعددة:** بعد الانصراف يمكن تسجيل دخول جديد بنفس اليوم (صف ثانٍ).
- **البانر:** يظهر لو `status=none` و`is_work_day`، محسوب عند التحميل فقط؛ زرّه يفتح نافذة تأكيد الحضور.
- **الأدمن:** يراجع/يصحّح من `ProviderAttendanceResource` ومن RelationManager داخل صفحة المقدّم.

> الخدمة أُضيف إليها: `openSession()` / `lastCheckOut()` / `recentSessions($limit=30)`. والمكوّن أُضيف إليه:
> `showCheckInModal/showCheckOutModal/showAttendanceHistoryModal` + `checkInPreview/checkOutPreview/attendanceHistory`
> + الدوال `open*Modal` / `confirm*` / `close*` / `formatAttendanceMinutes`. النوافذ في
> [staff-dashboard.blade.php](../resources/views/livewire/staff-dashboard.blade.php) وأزرارها في
> [staff-nav.blade.php](../resources/views/partials/staff-nav.blade.php).

---

## 5. تشغيل/إعادة التهيئة (Run / Re-seed)

```bash
php artisan migrate                              # ينشئ provider_attendances
php artisan db:seed --class=PermissionsSeeder    # ينشئ StaffDashboard:* و ProviderAttendance:*
php artisan db:seed --class=RoleSeeder           # يوزّعها على الأدوار
php artisan view:clear && php artisan view:cache # (اختياري) تجميع الـ blades
```
> أي صلاحية جديدة تضيفها مستقبلًا في `PAGE_ABILITIES` تتطلّب إعادة `PermissionsSeeder` ثم `RoleSeeder`.

---

## 6. نتائج التحقق (Verification — تم فعليًا)

| فحص | النتيجة |
|-----|---------|
| `php -l` على كل ملفات PHP الجديدة/المعدّلة | ✅ بلا أخطاء |
| `php artisan migrate` | ✅ `provider_attendances` أُنشئ (بعد إصلاح FK لـ `branchs`) |
| `PermissionsSeeder` | ✅ 142 صلاحية (StaffDashboard: 15، ProviderAttendance: 6) |
| `RoleSeeder` | ✅ provider = 24 صلاحية |
| `php artisan view:cache` | ✅ كل الـ blades تُجمَّع بنجاح (لا أخطاء Blade) |
| `AttendanceService` (اختبار مُتراجَع عنه) | ✅ check-in/out، منع الجلسة المزدوجة، جلسات متعددة (count=2)، todayState |
| LoginResponse binding | ✅ → `StaffLoginResponse` |
| `provider->canAccessPanel()` | ✅ YES |
| Filament routes | ✅ `admin/provider-attendances{,/create,/{record},/{record}/edit}` |

---

## 7. الحدود والاستثناءات (Out of Scope / Limitations)

- **لا auto-checkout job** عند إغلاق الصالون — الجلسة المنسيّة تبقى "Open" (قرار مقصود).
- **لا فرض privacy صارم** على رؤية بيانات الزملاء — التحكم بالصلاحيات (`view_team` / `edit_others`)
  لا بحجب البيانات تمامًا. (قابل للتشديد لاحقًا.)
- **لا workflow موافقة** على الإجازات؛ المقدّم يضيف إجازته لنفسه مباشرة.
- **الأدمن/المدير بلا Check-in** (الدوام للمقدّمين فقط).
- أزرار الحضور تظهر في تاب Calendar (حيث يُحسب `attendanceState`)؛ كارد الأفتار + Logout متاحان في كل التابات.

## 8. أفكار مستقبلية (Future Work)
- Auto-checkout job + تنبيه عند الجلسات المفتوحة الطويلة.
- تقرير دوام تجميعي (ساعات/شهر) + تصدير.
- فتح `ProviderAttendance` Resource للمقدّم ليرى أرشيفه الكامل.
- ربط الحضور بحساب الأجور/العمولات.

---

## 9. خريطة المصدر السريعة (Source Map)
1. `app/Livewire/Concerns/InteractsWithDashboardPermissions.php` — التفويض
2. `app/Livewire/StaffDashboard.php` — التنسيق + الحُرّاس + الدوام
3. `app/Services/AttendanceService.php` — منطق الدوام
4. `app/Models/ProviderAttendance.php` + migration — التخزين
5. `resources/views/partials/staff-nav.blade.php` — أزرار الحضور + الكارد + Logout
6. `resources/views/livewire/staff-dashboard.blade.php` — toggle + بانرات + تغليف الأزرار
7. `app/Filament/Resources/ProviderAttendances/**` + `RelationManagers/AttendancesRelationManager.php` — واجهة الأدمن
8. `app/Filament/Auth/StaffLoginResponse.php` + `AppServiceProvider` + `User::canAccessPanel` — الدخول
9. `database/seeders/{Permissions,Role}Seeder.php` — الصلاحيات
