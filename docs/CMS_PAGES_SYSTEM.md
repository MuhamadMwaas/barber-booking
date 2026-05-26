# نظام CMS Pages — التوثيق الكامل

> **Laravel 12 + FilamentPHP 5**  
> آخر تحديث: 2026-05-26

---

## جدول المحتويات

1. [نظرة عامة](#1-نظرة-عامة)
2. [بنية الملفات](#2-بنية-الملفات)
3. [آلية العمل](#3-آلية-العمل)
4. [قاعدة البيانات](#4-قاعدة-البيانات)
5. [الإعدادات — config/cms.php](#5-الإعدادات--configcmsphp)
6. [أنواع البلوكات](#6-أنواع-البلوكات)
7. [مرجع الـ API](#7-مرجع-الـ-api)
8. [لوحة Filament — دليل الاستخدام](#8-لوحة-filament--دليل-الاستخدام)
9. [دليل المطور — إضافة بلوك جديد](#9-دليل-المطور--إضافة-بلوك-جديد)
10. [نظام اللغات والـ Fallback](#10-نظام-اللغات-والـ-fallback)
11. [نظام الكاش](#11-نظام-الكاش)
12. [الأمان](#12-الأمان)
13. [اختبار النظام](#13-اختبار-النظام)

---

## 1. نظرة عامة

نظام CMS Pages يتيح لفريق الإدارة إنشاء صفحات ثابتة للتطبيق الجوال (سياسة الخصوصية، حولنا، الشروط والأحكام...) وتعديلها دون الحاجة لإصدار نسخة جديدة من التطبيق.

### الفكرة الأساسية

```
Admin (Filament) → ينشئ صفحة من بلوكات مرتبة
       ↓
قاعدة البيانات → تخزن البلوكات كـ JSON مع ترجمات جميع اللغات
       ↓
Cache → يحفظ نسخة معالجة لكل صفحة × لغة
       ↓
API   → يرجع JSON نظيف باللغة المطلوبة
       ↓
Mobile App → يبني الصفحة بلوكاً بلوك حسب النوع (type)
```

### لماذا هذا النهج؟

| الميزة | التفاصيل |
|--------|----------|
| لا تحديث مطلوب | تعديل أي صفحة يظهر فوراً في التطبيق |
| متعدد اللغات | كل بلوك يحمل ترجماته داخله |
| قابل للتوسعة | إضافة نوع بلوك جديد = إنشاء كلاس واحد |
| أداء عالٍ | كاش لكل صفحة × لغة (TTL 24 ساعة) |
| آمن | الصفحات المعطلة تُرجع 404 |

---

## 2. بنية الملفات

```
app/
├── Cms/
│   └── Blocks/
│       ├── CmsBlockContract.php        ← Interface للبلوكات
│       ├── AbstractCmsBlock.php        ← Base class (transform + language helpers)
│       ├── AbstractListBlock.php       ← Base class للقوائم
│       ├── HeadingBlock.php
│       ├── ParagraphBlock.php
│       ├── TitleParagraphBlock.php
│       ├── OrderedListBlock.php
│       ├── UnorderedListBlock.php
│       ├── DividerBlock.php
│       ├── LinkBlock.php
│       ├── ImageBlock.php
│       ├── WarningBoxBlock.php
│       └── HtmlBlock.php
│
├── Filament/
│   └── Resources/
│       └── CmsPages/
│           ├── CmsPageResource.php
│           ├── Pages/
│           │   ├── ListCmsPages.php
│           │   ├── CreateCmsPage.php
│           │   └── EditCmsPage.php     ← يحتوي زر "Preview API"
│           ├── Schemas/
│           │   └── CmsPageForm.php     ← الفورم مع API URL Preview
│           └── Tables/
│               └── CmsPagesTable.php
│
├── Http/
│   └── Controllers/
│       └── Api/
│           └── CmsPageController.php
│
├── Models/
│   └── CmsPage.php
│
└── Services/
    └── Cms/
        ├── CmsBlockRegistry.php        ← سجل مركزي لجميع البلوكات
        ├── CmsBlockNormalizer.php      ← تطبيع البيانات قبل الحفظ
        ├── CmsLanguageResolver.php     ← تحديد اللغة من الطلب
        ├── CmsPageTransformer.php      ← تحويل الصفحة لـ API response
        └── CmsPageCacheService.php     ← إدارة الكاش

config/
└── cms.php                             ← إعدادات اللغات، الكاش، الألوان

database/
└── migrations/
    └── 2026_05_26_..._create_cms_pages_table.php

lang/
├── ar/cms.php
├── en/cms.php
└── de/cms.php
```

---

## 3. آلية العمل

### 3.1 عند الحفظ من Filament

```
Admin يضغط Save
    ↓
CmsPage::saving() hook
    ├── توليد slug من name (إذا كان فارغاً)
    └── CmsBlockNormalizer::normalizeBlocks()
            ├── يضيف id (UUID) لكل بلوك يفتقر إليه
            ├── يضبط is_active = true إذا لم يُحدَّد
            └── يضمن وجود translations[lang] = {} لكل لغة
    ↓
حفظ في cms_pages.blocks (JSON)
    ↓
CmsPage::saved() hook
    └── CmsPageCacheService::forgetPage(slug)
            └── يحذف كاش الصفحة لجميع اللغات
```

### 3.2 عند طلب الصفحة عبر API

```
GET /api/pages/{slug}?lang=ar
    ↓
CmsPageController::show()
    ↓
CmsLanguageResolver::resolve()
    ├── يفحص ?lang= query parameter
    ├── يفحص Accept-Language header
    └── يرجع default language إذا لم يجد
    ↓
CmsPageCacheService::remember(slug, language)
    ├── [Cache HIT]  → يرجع البيانات مباشرة
    └── [Cache MISS] → ينفذ الـ callback:
            ↓
        CmsPage::query()->active()->where('slug', slug)->firstOrFail()
            ↓
        CmsPageTransformer::transform(page, language)
            ├── يفلتر البلوكات غير المفعلة (is_active = false)
            ├── لكل بلوك: CmsBlockRegistry::transformerFor(type)
            │       └── BlockClass::transform(block, language, fallback)
            │               ├── يحدد الترجمة المناسبة (مع fallback)
            │               ├── يحسب props.alignment إذا كانت 'auto'
            │               └── يرجع { id, type, props, content }
            └── يبني response array نهائي
    ↓
JSON response → { data: { slug, language, direction, blocks: [...] } }
```

---

## 4. قاعدة البيانات

### الجدول: `cms_pages`

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint unsigned | Primary key |
| `name` | varchar(255) | اسم داخلي للوحة التحكم — لا يظهر في API |
| `slug` | varchar(255) UNIQUE | معرف الصفحة — يُستخدم في URL |
| `is_active` | tinyint(1) | 1 = مفعلة / 0 = معطلة |
| `blocks` | json nullable | مصفوفة البلوكات مع ترجماتها |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

### مثال: قيمة `blocks`

```json
[
  {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "type": "heading",
    "is_active": true,
    "props": {
      "level": "h1",
      "alignment": "auto",
      "color": "default"
    },
    "translations": {
      "ar": { "text": "سياسة الخصوصية" },
      "en": { "text": "Privacy Policy" },
      "de": { "text": "Datenschutzerklärung" }
    }
  },
  {
    "id": "550e8400-e29b-41d4-a716-446655440001",
    "type": "paragraph",
    "is_active": true,
    "props": {
      "alignment": "auto",
      "color": "default"
    },
    "translations": {
      "ar": { "text": "نحن نحترم خصوصيتك." },
      "en": { "text": "We respect your privacy." },
      "de": { "text": "Wir respektieren Ihre Privatsphäre." }
    }
  }
]
```

---

## 5. الإعدادات — `config/cms.php`

```php
return [
    // اللغة الافتراضية للـ fallback
    'default_language' => env('CMS_DEFAULT_LANGUAGE', 'ar'),

    // اللغات المدعومة مع اتجاه النص والمحاذاة الافتراضية
    'supported_languages' => [
        'ar' => ['label' => 'العربية', 'direction' => 'rtl', 'default_alignment' => 'right'],
        'en' => ['label' => 'English',  'direction' => 'ltr', 'default_alignment' => 'left'],
        'de' => ['label' => 'Deutsch',  'direction' => 'ltr', 'default_alignment' => 'left'],
    ],

    // إعدادات الكاش
    'cache' => [
        'ttl'    => env('CMS_PAGE_CACHE_TTL', 86400), // ثانية (24 ساعة)
        'prefix' => 'cms_page',
    ],

    // ألوان متاحة للبلوكات
    'colors' => [
        'default' => 'Default', 'primary' => 'Primary', ...
    ],
];
```

### متغيرات `.env`

```dotenv
CMS_DEFAULT_LANGUAGE=ar
CMS_PAGE_CACHE_TTL=86400
```

---

## 6. أنواع البلوكات

### 6.1 `heading` — عنوان

**حقول Filament:** مستوى العنوان (h1–h4) + نص لكل لغة  
**خيارات العرض (مطوية):** محاذاة، لون

```json
{
  "id": "...",
  "type": "heading",
  "props": { "level": "h1", "alignment": "right", "color": "default" },
  "content": { "text": "سياسة الخصوصية" }
}
```

---

### 6.2 `paragraph` — فقرة نصية

**حقول Filament:** textarea لكل لغة  
**خيارات العرض (مطوية):** محاذاة، لون

```json
{
  "id": "...",
  "type": "paragraph",
  "props": { "alignment": "right", "color": "default" },
  "content": { "text": "نحن نحترم خصوصيتك ونلتزم بحماية بياناتك." }
}
```

---

### 6.3 `title_paragraph` — فقرة مع عنوان

**حقول Filament:** Tabs (واحد لكل لغة) — عنوان + نص  
**خيارات العرض (مطوية):** محاذاة، لون

```json
{
  "id": "...",
  "type": "title_paragraph",
  "props": { "alignment": "right", "color": "default" },
  "content": { "title": "ما البيانات التي نجمعها؟", "text": "نجمع..." }
}
```

---

### 6.4 `ordered_list` — قائمة مرتبة

**حقول Filament:** Tabs (واحد لكل لغة) — Repeater للعناصر

```json
{
  "id": "...",
  "type": "ordered_list",
  "props": { "alignment": "right" },
  "content": { "items": ["العنصر الأول", "العنصر الثاني"] }
}
```

---

### 6.5 `unordered_list` — قائمة غير مرتبة

نفس بنية `ordered_list`.

---

### 6.6 `divider` — فاصل

**حقول Filament:** اتجاه + سماكة + لون (مباشرة، بدون ترجمات)

```json
{
  "id": "...",
  "type": "divider",
  "props": { "orientation": "horizontal", "size": "sm", "color": "default" },
  "content": {}
}
```

---

### 6.7 `link` — رابط

**حقول Filament:** نص الرابط (لكل لغة) + URL + طريقة الفتح

```json
{
  "id": "...",
  "type": "link",
  "props": { "target": "external", "alignment": "right" },
  "content": { "label": "تواصل معنا", "url": "https://example.com/contact" }
}
```

---

### 6.8 `image` — صورة

**حقول Filament:** رفع صورة + alt text لكل لغة

```json
{
  "id": "...",
  "type": "image",
  "props": { "alignment": "center" },
  "content": {
    "url": "https://api.example.com/storage/cms/pages/image.webp",
    "alt": "وصف الصورة"
  }
}
```

---

### 6.9 `warning_box` — صندوق تحذير

**حقول Filament:** Tabs (واحد لكل لغة) — عنوان + نص  
**خيارات العرض (مطوية):** لون الخلفية، لون النص

```json
{
  "id": "...",
  "type": "warning_box",
  "props": { "background_color": "warning", "color": "default" },
  "content": { "title": "تنبيه مهم", "text": "يرجى قراءة هذا بعناية." }
}
```

---

### 6.10 `html` — محتوى HTML

**حقول Filament:** textarea HTML لكل لغة  
**أمان:** يمر عبر `strip_tags()` — يُنصح بـ HTMLPurifier للإنتاج

```json
{
  "id": "...",
  "type": "html",
  "props": { "alignment": "right" },
  "content": { "html": "<p>نص <strong>منسق</strong></p>" }
}
```

> **⚠️ تحذير للتطبيق:** إذا وصل نوع بلوك غير معروف — تجاهله كلياً ولا تكسر الصفحة.

---

## 7. مرجع الـ API

### `GET /api/pages/{slug}`

**Public endpoint — لا يحتاج مصادقة.**

#### Query Parameters

| الاسم | النوع | الوصف |
|-------|-------|-------|
| `lang` | string (اختياري) | رمز اللغة: `ar`, `en`, `de` |

#### تحديد اللغة (بالأولوية)

1. `?lang=xx` في الـ query string
2. `Accept-Language` header
3. اللغة الافتراضية من `config('cms.default_language')` → `ar`

#### استجابة ناجحة `200`

```http
GET /api/pages/privacy-policy?lang=ar
```

```json
{
  "data": {
    "slug": "privacy-policy",
    "language": "ar",
    "fallback_language": "ar",
    "direction": "rtl",
    "blocks": [
      {
        "id": "uuid-1",
        "type": "heading",
        "props": {
          "level": "h1",
          "alignment": "right",
          "color": "default",
          "background_color": "default",
          "size": "default",
          "style": "default"
        },
        "content": {
          "text": "سياسة الخصوصية"
        }
      }
    ]
  }
}
```

#### صفحة غير موجودة أو معطلة `404`

```json
{ "message": "الصفحة غير موجودة." }
```

> **ملاحظة:** الصفحة المعطلة (`is_active = false`) تُرجع 404 أيضاً — لا يُكشف عن وجودها.

#### ملاحظة على `props.alignment`

إذا أدخل المدير قيمة `auto`، يحسبها الباك إند تلقائياً:
- `ar` → `right`
- `en` / `de` → `left`

التطبيق يستقبل دائماً القيمة النهائية (`left` أو `right`) وليس `auto`.

---

## 8. لوحة Filament — دليل الاستخدام

### 8.1 إنشاء صفحة جديدة

1. افتح **لوحة التحكم → المحتوى → صفحات التطبيق**
2. اضغط **إنشاء**
3. أدخل **الاسم الداخلي** (مثل: "سياسة الخصوصية") — يولَّد الـ Slug تلقائياً
4. تحقق من الـ **Slug** — لا تغيره بعد نشر التطبيق
5. راجع **رابط الـ API** المعروض تحت حقل الـ Slug (يتحدث live)
6. ابدأ بإضافة البلوكات

### 8.2 إضافة بلوك

1. اضغط **+ إضافة بلوك**
2. اختر النوع من القائمة (عنوان، فقرة، صورة...)
3. أدخل المحتوى في التبويبات حسب اللغة (أو الأعمدة للحقول البسيطة)
4. خيارات العرض (محاذاة، لون...) موجودة في قسم **"خيارات العرض"** المطوي — افتحه فقط إذا احتجت تخصيصاً

### 8.3 ترتيب البلوكات

اسحب البلوك من المقبض على اليسار وأفلته في المكان المطلوب.

### 8.4 تعطيل بلوك مؤقتاً

داخل البلوك، أطفئ التبديل **"مفعل"** — سيُخفى من التطبيق دون حذفه.

### 8.5 معاينة الـ API (صفحة التعديل)

في صفحة تعديل أي صفحة، يوجد زر **"معاينة الـ API"** في الرأس — يفتح الرابط مباشرة في متصفح جديد.

---

## 9. دليل المطور — إضافة بلوك جديد

### مثال: إضافة بلوك `video`

#### الخطوة 1: إنشاء الكلاس

```php
// app/Cms/Blocks/VideoBlock.php
namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class VideoBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'video';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label('فيديو')
            ->icon('heroicon-o-play-circle')
            ->schema([
                Toggle::make('is_active')->label('مفعل')->default(true)->inline(),

                TextInput::make('url')
                    ->label('رابط الفيديو')
                    ->url()
                    ->required()
                    ->columnSpanFull(),

                // عنوان الفيديو — يستخدم Language model تلقائياً
                static::transTextGrid('title'),

                static::propsSection([
                    // أي props إضافية هنا
                ]),
            ])
            ->columns(1);
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        $url = $block['url'] ?? null;
        if (blank($url)) {
            return null;
        }

        $content = $this->translation($block, $language, $fallbackLanguage);

        return array_merge($this->baseResponse($block, $language), [
            'content' => [
                'url'   => $url,
                'title' => $content['title'] ?? '',
            ],
        ]);
    }
}
```

#### الخطوة 2: تسجيله في الـ Registry

```php
// app/Services/Cms/CmsBlockRegistry.php
public function classes(): array
{
    return [
        // ... الكلاسات الموجودة ...
        VideoBlock::class, // ← أضفه هنا
    ];
}
```

**هذا كل شيء!** الـ Filament Builder سيضيف الخيار تلقائياً، والـ API سيعالجه تلقائياً.

---

## 10. نظام اللغات والـ Fallback

### 10.1 اللغات من قاعدة البيانات

تُقرأ اللغات المتاحة من جدول `languages` (model: `Language`) عند بناء فورم كل بلوك:

```php
Language::where('is_active', true)->orderBy('order')->get()
```

**ميزة مهمة:** إذا أضفت لغة جديدة من لوحة التحكم، ستظهر تلقائياً في جميع البلوكات دون أي تعديل كودي.

### 10.2 اللغات المدعومة في الـ API

تُقرأ من `config('cms.supported_languages')` — هذه يجب أن تتطابق مع ما في جدول `languages`.

### 10.3 Fallback Logic

```
المستخدم يطلب lang=en
    ↓
هل يوجد translations.en.text ؟
    ├── نعم → استخدمه ✓
    └── لا  → هل يوجد translations.ar.text (fallback) ؟
                ├── نعم → استخدمه (المستخدم يرى العربية)
                └── لا  → تُحذف البلوك من الاستجابة
```

### 10.4 إضافة لغة جديدة

1. أضف اللغة من **الإعدادات → اللغات** في Filament
2. أضفها إلى `config/cms.php` → `supported_languages`
3. أضف ملف ترجمة في `lang/{code}/cms.php`
4. الصفحات القديمة ستظهر بلوكاتها بدون الترجمة الجديدة (fallback يعمل تلقائياً)

---

## 11. نظام الكاش

### 11.1 مفاتيح الكاش

```
cms_page:{slug}:{language}
```

مثال:
```
cms_page:privacy-policy:ar
cms_page:privacy-policy:en
cms_page:privacy-policy:de
```

### 11.2 مدة الكاش

افتراضياً **86400 ثانية (24 ساعة)**. قابل للتغيير من `.env`:

```dotenv
CMS_PAGE_CACHE_TTL=3600  # ساعة واحدة
```

### 11.3 حذف الكاش

**تلقائي:** عند حفظ أو حذف أي صفحة، تُحذف جميع نسخ الكاش لتلك الصفحة:

```php
// CmsPage::booted() hooks:
static::saved(fn ($page)   => app(CmsPageCacheService::class)->forgetPage($page->slug));
static::deleted(fn ($page) => app(CmsPageCacheService::class)->forgetPage($page->slug));
```

**يدوي (في حال الحاجة):**

```php
app(\App\Services\Cms\CmsPageCacheService::class)->forgetPage('privacy-policy');

// أو كل الصفحات:
\Illuminate\Support\Facades\Cache::flush();
```

### 11.4 ملاحظة: Cache Tags

إذا تحول المشروع إلى Redis، يمكن استخدام Cache Tags للحذف الجماعي:

```php
Cache::tags(['cms_pages', "cms_page:{$slug}"])->flush();
```

> ⚠️ Cache Tags لا تعمل مع driver `file` أو `database`.

---

## 12. الأمان

### 12.1 HTML Block

البلوك الوحيد الذي يقبل HTML حر. الكود الحالي يستخدم `strip_tags()` للتنظيف الأساسي.

**للإنتاج، يُنصح بـ HTMLPurifier:**

```bash
composer require ezyang/htmlpurifier
```

```php
// في HtmlBlock::sanitize():
$config = \HTMLPurifier_Config::createDefault();
$purifier = new \HTMLPurifier($config);
return $purifier->purify($html);
```

### 12.2 Image Block

- الصور تُحفظ في `storage/app/public/cms/pages/`
- الحجم الأقصى: 2MB
- الصيغ المسموحة: `image/jpeg`, `image/png`, `image/webp`

### 12.3 Slug Injection

حقل الـ Slug يستخدم `->alphaDash()` validation — يقبل فقط أحرف، أرقام، شرطات.

### 12.4 صفحات معطلة

لا تُكشف — ترجع 404 مثلها مثل الصفحات غير الموجودة.

---

## 13. اختبار النظام

### اختبار الـ API يدوياً

```bash
# صفحة موجودة باللغة العربية
curl "http://localhost/api/pages/privacy-policy?lang=ar"

# نفس الصفحة بالإنجليزية
curl "http://localhost/api/pages/privacy-policy?lang=en"

# صفحة غير موجودة → 404
curl "http://localhost/api/pages/does-not-exist?lang=ar"

# اللغة من الـ Header
curl -H "Accept-Language: de" "http://localhost/api/pages/privacy-policy"
```

### إنشاء صفحة تجريبية عبر Tinker

```php
php artisan tinker

$page = new \App\Models\CmsPage([
    'name'      => 'سياسة الخصوصية',
    'slug'      => 'privacy-policy',
    'is_active' => true,
    'blocks'    => [
        [
            'type'         => 'heading',
            'is_active'    => true,
            'props'        => ['level' => 'h1', 'alignment' => 'auto'],
            'translations' => [
                'ar' => ['text' => 'سياسة الخصوصية'],
                'en' => ['text' => 'Privacy Policy'],
                'de' => ['text' => 'Datenschutzerklärung'],
            ],
        ],
    ],
]);
$page->save();
```

### Unit Tests المقترحة

```php
// tests/Feature/CmsPageApiTest.php

test('returns active page with correct language', function () {
    $page = CmsPage::factory()->create([
        'slug'      => 'test',
        'is_active' => true,
        'blocks'    => [/* ... */],
    ]);

    getJson('/api/pages/test?lang=ar')
        ->assertOk()
        ->assertJsonPath('data.slug', 'test')
        ->assertJsonPath('data.direction', 'rtl');
});

test('inactive page returns 404', function () {
    CmsPage::factory()->create(['slug' => 'hidden', 'is_active' => false]);
    getJson('/api/pages/hidden')->assertNotFound();
});

test('fallback works when translation missing', function () {
    // Block has AR but no EN translation
    // → API should return AR content when requested in EN
});
```

---

## ملاحظات هامة للمطور

> 1. **لا تغير `type` بلوك موجود** — التطبيق الجوال يعتمد عليه. أي تغيير = breaking change.
> 2. **لا تحذف بلوكات من الكود** إلا بعد التأكد أن التطبيق لا يستخدمها.
> 3. **الـ Slug مثل المفتاح الأبدي** — بعد نشر التطبيق، لا يجب تغيير slug أي صفحة.
> 4. **اللغات في `config/cms.php` يجب أن تطابق** جدول `languages` في قاعدة البيانات.
> 5. **بلوكات جديدة** يجب أن يتجاهلها التطبيق القديم (graceful degradation).

---

*Generated by Claude Code — BarberBooking CMS System*
