# AUTHZ-01 — موظف مفصول يحتفظ بالوصول الكامل للوحة الموظفين

> **التاريخ:** 29 أغسطس 2026
> **الثغرة:** `AUTHZ-01` في [`SECURITY_AUDIT_2026-08-29.md`](../../SECURITY_AUDIT_2026-08-29.md) — 🔴 Critical (مانع إطلاق)
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بتنفيذ فعلي**
> **الاختبارات:** 13 اختباراً جديداً تمر · `DailyReportTest` + `RefreshTokenRotationTest` (25 اختباراً) بلا تراجعات

---

## 1. تحقّق من التقرير أولاً — ما صحّ وما لم يصح

قبل كتابة سطر واحد، فُحص كل ادّعاء في التقرير مقابل الكود:

| الادّعاء في التقرير | النتيجة |
|---|---|
| `EnsureStaffDashboardAccess` لا يفحص `is_active` إطلاقاً | ✅ **صحيح** |
| لوحة الموظفين ليست مسار Filament panel، فـ`canAccessPanel()` لا تُستدعى | ✅ **صحيح** — `route:list` يؤكد أن المسارات تحمل `web` + هذا الـ middleware فقط |
| الجلسة القديمة تبقى صالحة | ✅ **صحيح** |
| **«وتسجيل الدخول الجديد يمر أيضاً»** | ❌ **غير صحيح** — انظر أدناه |
| «صالحة لمدة `SESSION_LIFETIME=120` دقيقة» | ⚠️ **مُقلَّل من شأنه** — انظر أدناه |
| — | 🔴 **التقرير فاته أخطر نصف الثغرة: `POST /livewire/update`** |

### أ) تسجيل الدخول الجديد لا يمر

[`app/Filament/Pages/Auth/Login.php:49-59`](../../app/Filament/Pages/Auth/Login.php#L49-L59) يفحص `is_active` بعد التحقق من كلمة المرور ويرفض بإشعار واضح. فالموظف المفصول **لا يستطيع** فتح جلسة جديدة.

هذا التصحيح ليس تنقيصاً من خطورة الثغرة — بل **تحديد دقيق لسطح الهجوم**، وهو ما يحدد شكل الإصلاح: المشكلة ليست في بوابة الدخول، بل في **كل ما بعدها**. أي إصلاح يركّز على شاشة تسجيل الدخول يكون قد أصلح ما لم يكن مكسوراً.

### ب) الـ 120 دقيقة ليست حدّاً على شيء

`SESSION_LIFETIME` في Laravel هو **مهلة خمول** (`last_activity`)، لا عمراً مطلقاً. ولوحة الموظفين تعمل بـ Livewire وتحمل `->spa()` و`wire:navigate`، فكل نقرة وكل تحديث دوري يُنعش `last_activity`. **تبويب مفتوح لا تنتهي جلسته أبداً.** أضِف إليها خانة «تذكّرني» في شاشة الدخول ([`Login.php:115`](../../app/Filament/Pages/Auth/Login.php#L115)): كوكي الـ recaller يُعيد تسجيل الدخول تلقائياً حتى بعد إغلاق المتصفح ومسح الجلسة.

### ج) 🔴 ما فات التقرير: أفعال اللوحة لا تمر بهذا الـ middleware أصلاً

هذه أخطر من الثغرة الموصوفة، ولو طُبِّق حل التقرير حرفياً **لبقيت مفتوحة**.

الـ middleware مربوط بمسارات اللوحة، ومسارات اللوحة تُخدَم عند **تحميل الصفحة فقط**. أما كل فعل داخلها — `processPayment`، `deleteAppointment`، بحث `CustomerLookup` — فهو نداء `POST /livewire/update`، وهو **مسار مختلف تماماً** لا يحمل سوى مجموعة `web`: بلا `auth`، بلا فحص صلاحية، بلا `is_active`.

Livewire يُعيد تشغيل الـ middleware عبر طلبات التحديث **فقط إذا أُعلن persistent** ([`PersistentMiddleware.php:16-24`](../../vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php#L16-L24)) — والقائمة الافتراضية لا تحتوي على أي middleware من المشروع.

الحماية الوحيدة القائمة كانت أن الـ snapshot موقَّع بـ HMAC، فلا يمكن **تلفيقه** من العدم. لكن snapshot **يملكه المتصفح بالفعل** يبقى صالحاً للعمل — وهذه بالضبط الحالة موضوع الثغرة: الموظف الذي عُطِّل حسابه وتبويبه مفتوح.

والحارس الأخير — `dashDeny()` في [`InteractsWithDashboardPermissions`](../../app/Livewire/Concerns/InteractsWithDashboardPermissions.php) — يفحص **الصلاحيات فقط**. والصلاحيات تأتي من الدور لا من حالة الحساب، فهي تمر بنجاح لحساب معطّل.

**الخلاصة: الثغرة ثلاث طبقات لا واحدة.**

```
1. تحميل الصفحة   GET /                  → middleware بلا فحص is_active   ← التقرير رصدها
2. فعل اللوحة     POST /livewire/update  → لا يمر بالـ middleware إطلاقاً   ← التقرير فاتته
3. حارس الفعل     dashDeny()             → صلاحيات فقط، لا حالة حساب       ← التقرير فاتته
```

---

## 2. القرار التصميمي: مصدر واحد للحقيقة، وفحصان مختلفان للردّ

اقترح التقرير `isActiveStaff()` كمصدر وحيد — وهذا صحيح واعتُمد. لكن **نسخ الشرط حرفياً إلى الـ middleware يُنتج ردّاً خاطئاً**:

> `isActiveStaff()` يدمج سببين مختلفين تماماً للرفض: **الحساب معطّل** و**الدور غير مناسب**. وهذان يستحقان ردّين مختلفين.

| السبب | الردّ الصحيح | لماذا |
|---|---|---|
| `is_active = false` | **تسجيل خروج + تدمير الجلسة** | 403 يترك الكوكي حيّاً. الموظف ينتظر فقط أن يُعاد تفعيله، أو يبحث عن سطح آخر نسي الفحص. الإبطال يجب أن يكون **فورياً وتاماً** |
| دور غير مناسب (عميل مثلاً) | **403 فقط** | إخراج عميل من جلسته لأنه طرق باباً ليس له سلوك عدواني وخاطئ |

لذلك: `isActiveStaff()` هو الجواب **المركّب** المُستخدَم في `canAccessPanel()` وفي أي سطح مستقبلي، بينما الـ middleware يفكّه إلى نصفيه ليختار الردّ المناسب. والنصفان معرّفان في النموذج أيضاً (`is_active` و`hasStaffRole()`)، فلا نسخة ثانية للقاعدة في أي مكان.

### لماذا `hasStaffRole()` وليست `isStaffAccount()` الموجودة أصلاً؟

`User::isStaffAccount()` موجودة وتبدو مناسبة — **لكنها تُسقط `SuperAdmin` عمداً**، لأنها تخدم سؤالاً آخر تماماً: هل يحتاج هذا الحساب تحقق OTP في الـ API. إعادة استخدامها هنا كانت ستدمج مسألتين لا علاقة بينهما، وأي تعديل مستقبلي لإحداهما كان سيكسر الأخرى بصمت. لذا أُضيفت `hasStaffRole()` مستقلة فوق ثابت `User::STAFF_ROLES`.

---

## 3. الإصلاح — أربع طبقات

### الطبقة 1 — مصدر واحد للحقيقة · [`app/Models/User.php`](../../app/Models/User.php)

```php
public const STAFF_ROLES = ['SuperAdmin', 'admin', 'manager', 'provider'];

public function hasStaffRole(): bool
{
    return $this->hasAnyRole(self::STAFF_ROLES);
}

public function isActiveStaff(): bool
{
    return $this->is_active && $this->hasStaffRole();
}

public function canAccessPanel(Panel $panel): bool
{
    return $this->isActiveStaff();     // نفس المصدر، لا نسخة ثانية
}
```

### الطبقة 2 — طرد لا رفض · [`EnsureStaffDashboardAccess.php`](../../app/Http/Middleware/EnsureStaffDashboardAccess.php)

```php
if (! $user->is_active) {
    $auth->logout();                    // يُدوّر remember_token أيضاً
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    // إشعار Filament — نفس رسالة شاشة الدخول
    return redirect()->route('filament.admin.auth.login');
}

if (! $user->hasStaffRole())               { abort(403); }
if (! $user->can('StaffDashboard:access')) { abort(403); }
```

ترتيب الأسطر مقصود: `invalidate()` يمسح الجلسة قبل ترحيلها، فالإشعار يُومَض **بعده** وإلا ضاع.

وفحص الدور دفاع في العمق: العميل الذي يحمل الصلاحية بلا دور موظف يُرفض. وهذا ليس افتراضياً — مسار `GET /grant-view-stats` المحذوف كان يمنح صلاحيات `StaffDashboard` لكل الأدوار بلا مصادقة، وأي أثر متبقٍّ منه في قاعدة بيانات الإنتاج يمرّ من فحص الصلاحية وحده.

### الطبقة 3 — 🔴 تغطية أفعال Livewire · [`AppServiceProvider.php`](../../app/Providers/AppServiceProvider.php)

```php
Livewire::addPersistentMiddleware(EnsureStaffDashboardAccess::class);
```

سطر واحد، وهو **ما يجعل الإصلاح إصلاحاً فعلياً** بدل إغلاق الباب الأمامي وترك باب الخدمة مفتوحاً.

كيف يعمل: عند `dehydrate` يحفظ Livewire المسار والطريقة الأصليين في الـ memo؛ وعند التحديث التالي يعيد بناء ذلك الطلب، يطابق المسار، ويمرّره عبر ما يظهر من middlewareاته في قائمة persistent. و`Utils::applyMiddleware` يحوّل أي `RedirectResponse` من الأنبوب إلى `abort` — و JS الخاص بـ Livewire يتبع التحويل، فيصل الموظف المعطّل إلى شاشة الدخول كتنقّل حقيقي لا كخطأ صامت.

### الطبقة 4 — التعطيل مفتاح قتل لا راية · [`app/Observers/UserObserver.php`](../../app/Observers/UserObserver.php)

الـ middleware يحكم على **الطلب التالي** وعلى الأسطح التي تتذكّر أن تسأل. أما ما يحمله الحساب بالفعل فيبقى صالحاً. لذلك، لحظة انقلاب `is_active` إلى `false` — أو حذف الحساب — يُدمَّر كل شيء في مكان واحد:

| ما يُبطَل | كيف | لماذا لا يكفي غيابه |
|---|---|---|
| توكنات Sanctum | `$user->tokens()->delete()` | تطبيق الموبايل يبقى يعمل بلا حد زمني |
| توكنات التحديث | `RefreshToken::revokeAllFor(..., REASON_ACCOUNT_DISABLED)` | يتجدد الوصول للأبد بلا مرور بشاشة دخول |
| جلسات الويب | حذف صفوف `sessions` بـ `user_id` | الكوكي الحيّ = اللوحة تعمل |
| كوكي «تذكّرني» | `remember_token = null` | **ينجو من حذف الجلسة ويُعيد الدخول تلقائياً** |

أُضيف سبب إبطال جديد `RefreshToken::REASON_ACCOUNT_DISABLED` بدل إعادة استخدام `LOGOUT`، لأن حقل `revoked_reason` هو **مُدخَل كشف إعادة الاستخدام** في `AUTH-04` لا مجرد سجل: «انتهت بقرار إداري» و«انتهت بقرار المستخدم» حدثان مختلفان تماماً في أي تحقيق لاحق.

الـ Observer مربوط بـ `#[ObservedBy(UserObserver::class)]` على النموذج، ويلتقط `updated` و`deleted` (الحذف الناعم والصلب معاً).

**حدّ معروف ومقصود:** الـ Observer يعتمد أحداث Eloquent، فلا يعمل مع `User::query()->update(...)` أو SQL خام أو استيراد قاعدة بيانات. **فرع `is_active` في الـ middleware هو شبكة الأمان لتلك الحالات** — ولهذا بقي الفحصان معاً بدل الاكتفاء بالـ Observer.

---

## 4. التحقق

```
tests/Feature/StaffDashboardAccessTest.php ................ 13 ✓
tests/Feature/DailyReportTest.php .......................... ✓ (بلا تراجع)
tests/Feature/RefreshTokenRotationTest.php ................. ✓ (بلا تراجع)
```

ما تغطّيه الاختبارات:

- مزوّد **نشط** يدخل اللوحة (لم يُكسر الاستخدام الطبيعي)
- مزوّد **معطّل** يُحوَّل لشاشة الدخول — **و`assertGuest()`**، أي أن الجلسة دُمّرت لا رُفضت فقط
- مدير معطّل كذلك
- **عميل يحمل الصلاحية** → 403 (بوابة الدور)
- موظف نشط **بلا الصلاحية** → 403
- `isActiveStaff()` يتطلب نصفيه معاً
- **`canAccessPanel()` و`isActiveStaff()` لا يفترقان** — هذا الاختبار هو ما يمنع عودة الانحراف الأصلي
- التعطيل يُبطل توكنات API + التحديث + كوكي «تذكّرني»
- **إعادة التفعيل لا تُبطل شيئاً** (لا إبطال جانبي غير مقصود)
- حذف الحساب يُبطل توكناته

> **ملاحظة على حدود الاختبار:** الطبقة 3 لا يمكن تأكيدها داخل PHPUnit — `Livewire::test()` لا يمر بمسار `livewire/update` الحقيقي (`isLivewireRoute()` تعود `false`)، فآلية persistent middleware لا تعمل أصلاً في بيئة الاختبار. يُتحقَّق منها بأن التسجيل موجود فعلاً في القائمة، ويدوياً كما في القسم 5.

---

## 5. قبل النشر

```bash
# 1) لا ميجريشن لهذا الإصلاح — تغييرات كود فقط
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache

# 2) تأكد أن الـ middleware مُسجَّل كـ persistent (يجب أن يطبع true)
php artisan tinker --execute="dump(in_array(App\Http\Middleware\EnsureStaffDashboardAccess::class, Livewire\Livewire::getPersistentMiddleware(), true));"

# 3) اختبارات الحماية
php artisan test --filter=StaffDashboardAccessTest
```

### الفحص اليدوي الذي لا يغني عنه اختبار (الطبقة 3)

1. سجّل دخول موظف على `dashboard.lookupfriseur.com` واترك التبويب **مفتوحاً**.
2. من `/admin/users` اضبط `is_active = false`.
3. في التبويب المفتوح، اضغط أي زر فعل (فتح نافذة الدفع مثلاً) **بلا إعادة تحميل**.
4. **المتوقع:** تنقّل فوري إلى شاشة الدخول مع إشعار «الحساب معطّل». **الخطأ:** أن يعمل الزر.

> **ملاحظة:** `SESSION_DRIVER=database` مطلوب ليتمكن الـ Observer من حذف الجلسات فوراً؛ وهو الإعداد الحالي. على `file` أو `redis` لا يوجد `user_id` نستهدفه، فيصير الطرد عند الطلب التالي عبر الـ middleware بدل أن يكون فورياً.

---

## 6. ما لم يُلمَس عمداً

| البند | السبب |
|---|---|
| `AUTHZ-02` (`CustomerLookup` بلا صلاحية خاصة) | ثغرة منفصلة. هذا الإصلاح يمنع **الموظف السابق** من الوصول، ولا يضبط ما يراه الموظف **الحالي** |
| `MON-02` (مبلغ الدفع يحدده المستخدم) | ثغرة منفصلة في منطق الفوترة |
| `User::isStaffAccount()` (تُسقط `SuperAdmin`) | تخدم تحقق OTP في الـ API؛ تغييرها هنا يخلط مسألتين. **تستحق مراجعة مستقلة** |
| TSE / Fiskaly | مُوقَف عمداً (`FISKALY_ENABLED=false`) — خارج نطاق هذا الإصلاح |
