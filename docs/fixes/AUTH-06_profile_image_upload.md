# AUTH-06 — إحكام رفع صورة الملف الشخصي

> **التاريخ:** 29 أغسطس 2026
> **الثغرة:** `AUTH-06` في [`SECURITY_AUDIT_2026-08-29.md`](../../SECURITY_AUDIT_2026-08-29.md) — 🟡 متوسطة
> **الحالة:** ✅ **مُصلحة على مسارات الرفع الثلاثة كلها، لا على المسار المذكور في التقرير وحده**
> **الاختبارات:** 9 اختبارات جديدة تمر · 276 ناجحاً في المجموعة · **صفر تراجعات**

---

## 1. تصحيحان على التقرير الأصلي

### التصحيح الأول: `image` لا تسمح بـ SVG على Laravel 12

قال التقرير إن قاعدة `image` «أوسع مما يجب» ضمنياً بلا تحديد. الدقيق هو:

```php
// vendor/laravel/framework/.../ValidatesAttributes.php:1494
public function validateImage($attribute, $value, $parameters = [])
{
    $mimes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

    if (is_array($parameters) && in_array('allow_svg', $parameters)) {
        $mimes[] = 'svg';
    }

    return $this->validateMimes($attribute, $value, $mimes);
}
```

فـ **SVG مرفوض أصلاً** على هذا الإصدار ما لم يُطلَب `image:allow_svg` صراحةً. الاتساع الحقيقي
هو `gif` و`bmp`. و`gif` هو المهم: صورة GIF متحركة = عدد غير محدود من الإطارات داخل ملف صغير،
وهي نفس مشكلة التضخيم التي تحدّث عنها التقرير، بصيغة أخرى.

### التصحيح الثاني: القاعدة الجديدة كما كُتبت في التقرير تحوي تكراراً

الاقتراح كان:

```php
'image', 'mimes:jpeg,jpg,png,webp',
```

و`image` نفسها ليست إلا `mimes` بقائمة أوسع. الترتيب الصحيح هو: `image` تبقى (لأنها تُصرّح
بالنية وتحرس ضد `allow_svg` مستقبلاً)، و`mimes` تُضيّق قائمتها. وهذا بالضبط ما تبنيه
`File::image()->types(...)`.

---

## 2. لماذا لم يكن `max:2048` كافياً — التمييز الجوهري

| القيد | ما يحدّه فعلاً | ما لا يحدّه |
|---|---|---|
| `max:2048` | **البايتات الواصلة** عبر الشبكة وإلى القرص | تكلفة فكّ الترميز |
| `dimensions:` | **البكسلات** التي تتمدّد إليها تلك البايتات | — |

ملف PNG بحجم 2 ميغابايت يستطيع أن يُصرّح في ترويسته بأنه 20000×20000 بكسل. فكّ ترميزه يكلّف
~1.2 غيغابايت من الذاكرة → **انهيار عامل PHP** (قنبلة فكّ الضغط).

**النقطة اللطيفة:** قاعدة `dimensions` لا تفكّ ترميز شيء. تستدعي `getimagesize()` التي تقرأ
**الترويسة فقط ثم تتوقف**. أي أن الفحص الذي يرفض القنبلة لا يدفع تكلفتها أبداً — وهذا ما يجعل
الحل صحيحاً لا مجرّد «فحص إضافي».

**سقف 4000×4000** = 16 ميغابكسل ≈ 64 ميغابايت بعد فكّ الترميز. أعلى بكثير من أي صورة رمزية
من كاميرا هاتف، وأدنى بكثير من `memory_limit` الافتراضي.

---

## 3. توسيع النطاق: ثلاثة مداخل لا مدخل واحد

التقرير أشار إلى `ProfileController` وحده. لكن للصورة الرمزية **ثلاثة مداخل**:

| المدخل | ما كان عليه |
|---|---|
| `POST /api/profile` | `image\|max:2048` — بلا `mimes` وبلا `dimensions` |
| `UserForm` (Filament) | `maxSize(2048)` + `acceptedFileTypes([...])` — **بلا `dimensions`** |
| `ProviderForm` (Filament) | نفس الشيء — **بلا `dimensions`** |

**قائمةُ سماحٍ تصمد على مدخل واحد من ثلاثة ليست قائمة سماح.** القنبلة كانت تمرّ من لوحة
الإدارة كما تمرّ من الـ API. لذلك صار مصدر الحقيقة واحداً:

```
config/uploads.php  ──►  App\Support\ImageUploadRules  ──┬──►  ProfileController
                                                         ├──►  UserForm
                                                         └──►  ProviderForm
```

تضييق حدّ الآن = تعديل واحد، لا ثلاثة.

---

## 4. الثغرة الجانبية المكتشفة أثناء الإصلاح: الامتداد المخزَّن

في `User::updateProfileImage()`:

```php
$extension = $image->extension() ?: $image->getClientOriginalExtension();
```

`getClientOriginalExtension()` **يختاره المُرسِل**. وهذه الملفات تُكتب على القرص العام
(`storage/app/public`). فحين يعجز `extension()` عن التخمين، الاسم النهائي على القرص يرثه من
المهاجم.

> **إنصافاً:** Laravel نفسها ترفض أسماء `.php` داخل قاعدة `mimes` عبر `shouldBlockPhpUpload()`،
> فالاستغلال المباشر لم يكن مفتوحاً. لكن ذلك أرضيةٌ لا دفاعاً كاملاً — ولا سبب أصلاً لأن
> يُشتقّ اسمٌ على قرص عام من مُدخَل يتحكّم به المُرسِل.

صار الاشتقاق من **بايتات الملف**، ثم يُقارَن بنفس القائمة التي استعملها المُدقِّق:

```php
$extension = ImageUploadRules::safeExtension($image);   // يرمي إن لم يكن ضمن القائمة
```

---

## 5. الملفات

### جديدة

| الملف | ما هو |
|---|---|
| `config/uploads.php` | الأرقام (الصيغ · الحجم · الأبعاد)، مضبوطة من `.env` |
| `app/Support/ImageUploadRules.php` | مصدر الحقيقة الوحيد + `safeExtension()` |
| `tests/Feature/ProfileImageValidationTest.php` | 9 اختبارات تحرس AUTH-06 من التراجع |

### معدَّلة

| الملف | التعديل |
|---|---|
| `app/Http/Controllers/Api/ProfileController.php` | `'image' => ImageUploadRules::profileImage()` |
| `app/Models/User.php` | الامتداد المخزَّن من البايتات لا من اسم العميل |
| `app/Filament/Resources/Users/Schemas/UserForm.php` | نفس السقوف + `dimensions` |
| `app/Filament/Resources/Providers/Schemas/ProviderForm.php` | نفس السقوف + `dimensions` |

---

## 6. الاختبارات

```
✓ an ordinary jpeg avatar is still accepted            ← لا انحدار في المسار السليم
✓ a real webp avatar is accepted                       ← webp حقيقية مولَّدة بـ GD لا مزيَّفة
✓ a gif is rejected even though the image rule allows it
✓ an svg is rejected
✓ an image wider than the ceiling is rejected          ← القنبلة (عرضاً)
✓ an image taller than the ceiling is rejected         ← القنبلة (ارتفاعاً)
✓ a file over the size ceiling is rejected
✓ a non image declaring an image content type is rejected
✓ the stored filename ignores the client supplied extension
```

كل اختبار رفض يؤكّد أيضاً `File::count() === 0` — أي أن الرفض حدث **قبل** أي كتابة على القرص.

> **ملاحظة على قياس الأبعاد:** الاختبار يستعمل `max_width + 1` بارتفاع 8 بكسل (لا 5000×5000)،
> لأن توليد صورة اختبار بحجم القنبلة يكلّف مجموعة الاختبارات ما نحاول منع الخادم من دفعه.
> الحدّ يُختبَر عند حافّته، وهذا ما يهمّ.

### حالة المجموعة

**276 ناجحاً · 17 فاشلاً · صفر تراجعات.**

الـ 17 فاشلاً **سابقة لهذا الإصلاح** — تفشل بنفس الشكل تماماً بعد إخفاء تعديلات AUTH-06 وإعادة
التشغيل (تُحقّق ذلك بالقياس لا بالافتراض): `TaxCalculator` (6) · `Fiskaly` (5) ·
`DeleteAccountTest` · `ExampleTest` · **`ProfileImageUploadTest` (3 — `ModelNotFoundException`
عند حلّ سجل Filament، لا علاقة له بالرفع)**.
