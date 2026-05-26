# دليل المطور الأمامي — نظام صفحات CMS
> **Look Up App — Frontend Rendering Guide**  
> آخر تحديث: 2026-05-26

---

## 📋 جدول المحتويات

1. [الفكرة العامة](#1-الفكرة-العامة)
2. [كيف تطلب الصفحة](#2-كيف-تطلب-الصفحة)
3. [بنية الـ Response](#3-بنية-الـ-response)
4. [الـ Direction و RTL/LTR](#4-الـ-direction-و-rtlltr)
5. [أنواع البلوكات — الدليل الكامل](#5-أنواع-البلوكات--الدليل-الكامل)
6. [كيف تبني Renderer عام](#6-كيف-تبني-renderer-عام)
7. [الصفحات المتاحة حالياً](#7-الصفحات-المتاحة-حالياً)
8. [حالات الخطأ](#8-حالات-الخطأ)
9. [أفضل الممارسات](#9-أفضل-الممارسات)

---

## 1. الفكرة العامة

الباك إند يخزن محتوى الصفحات (سياسة الخصوصية، الشروط، Impressum...) كـ **مصفوفة من البلوكات** المرتبة.

كل بلوك = قطعة محتوى واحدة لها **نوع محدد** (`type`) وبيانات (`props` + `content`).

مهمتك كمطور فرونت إند:
1. استدعاء الـ API بالـ slug واللغة
2. قراءة مصفوفة `blocks`
3. رندر كل بلوك حسب نوعه (`type`)

```
API Response
     │
     └── blocks: [
              { type: "heading",   props: {...}, content: {...} },
              { type: "paragraph", props: {...}, content: {...} },
              { type: "divider",   props: {...}, content: {} },
              ...
         ]
```

> ⚠️ **قاعدة مهمة:** إذا وصلك `type` لا تعرفه — **تجاهل البلوك كلياً** ولا تكسر الصفحة.  
> هذا يضمن أن إضافة بلوك جديد من الباك إند لا تؤثر على الإصدارات القديمة.

---

## 2. كيف تطلب الصفحة

### Endpoint

```
GET /api/pages/{slug}
```

### Query Parameters

| الاسم | مطلوب | الوصف |
|-------|-------|-------|
| `lang` | لا | رمز اللغة: `ar` / `en` / `de` (افتراضي: `ar`) |

### أمثلة

```http
GET /api/pages/privacy-policy?lang=ar
GET /api/pages/privacy-policy?lang=de
GET /api/pages/terms-conditions?lang=en
GET /api/pages/impressum?lang=ar
```

### تحديد اللغة — الأولوية

الباك إند يحدد اللغة بهذا الترتيب:
1. `?lang=xx` في الـ URL ← **الأعلى أولوية**
2. `Accept-Language` header
3. اللغة الافتراضية (`ar`) ← **الأدنى أولوية**

**نصيحة:** مرر اللغة دائماً عبر `?lang=` لضمان الدقة.

---

## 3. بنية الـ Response

### استجابة ناجحة `200 OK`

```json
{
  "data": {
    "slug":              "privacy-policy",
    "language":          "ar",
    "fallback_language": "ar",
    "direction":         "rtl",
    "blocks": [
      {
        "id":    "550e8400-e29b-41d4-a716-446655440000",
        "type":  "heading",
        "props": {
          "level":            "h1",
          "alignment":        "right",
          "color":            "default",
          "background_color": "default",
          "size":             "default",
          "style":            "default"
        },
        "content": {
          "text": "سياسة الخصوصية"
        }
      },
      {
        "id":    "550e8400-e29b-41d4-a716-446655440001",
        "type":  "paragraph",
        "props": {
          "alignment": "right",
          "color":     "default"
        },
        "content": {
          "text": "نحن في Look up OHG نولي حماية البيانات..."
        }
      }
    ]
  }
}
```

### الحقول على مستوى الـ `data`

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `slug` | string | معرف الصفحة |
| `language` | string | اللغة المُرجعة فعلياً |
| `fallback_language` | string | لغة الـ fallback (دائماً `ar`) |
| `direction` | `"rtl"` \| `"ltr"` | اتجاه النص ← **ضعه على الـ container الرئيسي** |
| `blocks` | array | مصفوفة البلوكات المرتبة |

### الحقول على مستوى كل `block`

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `id` | string (UUID) | معرف فريد للبلوك |
| `type` | string | نوع البلوك ← **هذا ما تستخدمه لتحديد الـ widget** |
| `props` | object | خصائص العرض (محاذاة، لون...) |
| `content` | object | المحتوى الفعلي (النصوص، الروابط...) |

---

## 4. الـ Direction و RTL/LTR

### قراءة الاتجاه

```json
"direction": "rtl"   ← عربي
"direction": "ltr"   ← إنجليزي / ألماني
```

### تطبيق الاتجاه

ضع `direction` على أعلى Container في الصفحة:

```dart
// Flutter مثال
Directionality(
  textDirection: data.direction == 'rtl'
      ? TextDirection.rtl
      : TextDirection.ltr,
  child: PageContent(blocks: data.blocks),
)
```

```jsx
// React Native مثال
<View style={{ direction: data.direction }}>
  {data.blocks.map(block => <BlockRenderer block={block} />)}
</View>
```

### الـ `props.alignment`

الباك إند **يحسب** المحاذاة تلقائياً — أنت تستقبل دائماً:

| القيمة | المعنى |
|--------|--------|
| `"left"` | محاذاة يسار |
| `"right"` | محاذاة يمين |
| `"center"` | توسيط |
| `"justify"` | ضبط |

> **ملاحظة:** لن تصلك قيمة `"auto"` أبداً — الباك إند يحولها لـ `left` أو `right` حسب اللغة.

---

## 5. أنواع البلوكات — الدليل الكامل

---

### 📌 `heading` — عنوان

**الاستخدام:** عناوين الأقسام والصفحات.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "heading",
  "props": {
    "level":     "h1",
    "alignment": "right",
    "color":     "primary"
  },
  "content": {
    "text": "سياسة الخصوصية"
  }
}
```

**حقول `props`:**

| الحقل | القيم الممكنة | الوصف |
|-------|--------------|-------|
| `level` | `"h1"` `"h2"` `"h3"` `"h4"` | مستوى الأهمية — اضبط الـ font size بناءً عليه |
| `alignment` | `"left"` `"right"` `"center"` `"justify"` | محاذاة النص |
| `color` | `"default"` `"primary"` `"muted"` | لون النص |

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `text` | string | نص العنوان |

**مقترح font sizes:**

| level | حجم مقترح |
|-------|-----------|
| `h1` | 26–28 sp |
| `h2` | 20–22 sp |
| `h3` | 17–18 sp |
| `h4` | 15–16 sp |

---

### 📌 `paragraph` — فقرة نصية

**الاستخدام:** النصوص العادية والشرح.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "paragraph",
  "props": {
    "alignment": "right",
    "color":     "default"
  },
  "content": {
    "text": "نحن في Look up OHG نولي حماية البيانات الشخصية أهمية كبيرة."
  }
}
```

**حقول `props`:**

| الحقل | القيم | الوصف |
|-------|-------|-------|
| `alignment` | `left` `right` `center` `justify` | محاذاة |
| `color` | `default` `primary` `muted` | لون النص |

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `text` | string | قد يحتوي على `\n` للأسطر الجديدة — تعامل معها |

> ⚡ **تنبيه:** النص قد يحتوي `\n` — استخدم `Text` يدعم الأسطر المتعددة.

---

### 📌 `title_paragraph` — عنوان فرعي + فقرة

**الاستخدام:** الأقسام الفرعية التي تحتوي عنوان صغير ونص شرح.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "title_paragraph",
  "props": {
    "alignment": "right",
    "color":     "default"
  },
  "content": {
    "title": "٣.١ إنشاء الحساب",
    "text":  "عند إنشاء حساب داخل التطبيق، قد يتم جمع البيانات التالية:"
  }
}
```

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `title` | string | العنوان الصغير — bold, 16–17 sp |
| `text` | string | النص الشارح — regular, 13–14 sp |

---

### 📌 `ordered_list` — قائمة مرقّمة

**الاستخدام:** خطوات مرتبة أو عناصر ذات تسلسل.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "ordered_list",
  "props": {
    "alignment": "right"
  },
  "content": {
    "items": [
      "الاسم الأول واسم العائلة",
      "البريد الإلكتروني",
      "رقم الهاتف"
    ]
  }
}
```

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `items` | `string[]` | مصفوفة نصوص — كل عنصر سطر مستقل |

**طريقة الرندر:** رقم (1, 2, 3...) بجانب كل عنصر بلون مميز (ذهبي مثلاً).

---

### 📌 `unordered_list` — قائمة نقطية

**الاستخدام:** قوائم غير مرتبة.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "unordered_list",
  "props": {
    "alignment": "right"
  },
  "content": {
    "items": [
      "تقديم معلومات صحيحة عند التسجيل",
      "الحفاظ على سرية بيانات الدخول",
      "عدم استخدام حسابات الآخرين"
    ]
  }
}
```

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `items` | `string[]` | نفس `ordered_list` لكن بدون ترقيم |

**طريقة الرندر:** نقطة صغيرة (•) أو دائرة ملونة بجانب كل عنصر.

---

### 📌 `divider` — فاصل

**الاستخدام:** خط فاصل بين الأقسام.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "divider",
  "props": {
    "orientation": "horizontal",
    "size":        "sm",
    "color":       "default",
    "alignment":   "left"
  },
  "content": {}
}
```

**حقول `props`:**

| الحقل | القيم | الوصف |
|-------|-------|-------|
| `orientation` | `"horizontal"` `"vertical"` | اتجاه الفاصل |
| `size` | `"sm"` `"md"` `"lg"` | سماكة الخط |
| `color` | `"default"` `"primary"` | لون الخط |

**سماكات مقترحة:**

| size | سماكة |
|------|-------|
| `sm` | 1 px |
| `md` | 2 px |
| `lg` | 3 px |

> `content` فارغ دائماً لهذا النوع — لا تتوقع فيه شيئاً.

---

### 📌 `link` — رابط / زر

**الاستخدام:** روابط خارجية أو أزرار قابلة للضغط.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "link",
  "props": {
    "target":    "external",
    "alignment": "center"
  },
  "content": {
    "label": "تواصل معنا",
    "url":   "https://example.com/contact"
  }
}
```

**حقول `props`:**

| الحقل | القيم | الوصف |
|-------|-------|-------|
| `target` | `"same"` `"external"` | طريقة الفتح |

| target | السلوك المطلوب |
|--------|----------------|
| `"same"` | افتح داخل WebView في التطبيق |
| `"external"` | افتح في متصفح خارجي (`launch_url`) |

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `label` | string | نص الزر / الرابط |
| `url` | string | الرابط الكامل |

---

### 📌 `image` — صورة

**الاستخدام:** صور توضيحية داخل الصفحة.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "image",
  "props": {
    "alignment": "center"
  },
  "content": {
    "url": "https://api.example.com/storage/cms/pages/image.webp",
    "alt": "وصف الصورة للقارئ الآلي"
  }
}
```

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `url` | string | رابط الصورة الكامل — يشمل domain |
| `alt` | string | نص بديل (accessibility) — قد يكون فارغاً |

> ⚡ **تنبيه:** الـ `url` كامل ويشمل الـ domain — لا تحتاج إضافة base URL.

---

### 📌 `warning_box` — صندوق تحذير

**الاستخدام:** ملاحظات مهمة تستحق إبرازاً بصرياً.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "warning_box",
  "props": {
    "background_color": "default",
    "color":            "default",
    "alignment":        "right"
  },
  "content": {
    "text": "يسمح باستخدام التطبيق فقط للأشخاص الذين أتموا 16 عامًا."
  }
}
```

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `text` | string | نص التحذير |

**مقترح للتصميم:** خلفية صفراء/برتقالية فاتحة + أيقونة ⚠️ + نص داكن.

---

### 📌 `html` — محتوى HTML

**الاستخدام:** محتوى منسق بـ HTML بسيط.

**JSON:**
```json
{
  "id":   "uuid",
  "type": "html",
  "props": {
    "alignment": "right"
  },
  "content": {
    "html": "<p>نص <strong>منسق</strong> مع <a href=\"...\">رابط</a></p>"
  }
}
```

**حقول `content`:**

| الحقل | النوع | الوصف |
|-------|-------|-------|
| `html` | string | HTML نظيف (تم تعقيمه من الباك إند) |

**للرندر:** استخدم `flutter_html` أو `WebView` أو `HtmlWidget` حسب الفريموورك.

> ⚡ **الـ HTML مُعقَّم** من الباك إند ولا يحتوي scripts — آمن للعرض.

---

## 6. كيف تبني Renderer عام

### المنطق الأساسي

```dart
// Flutter — مثال
Widget buildBlock(Map block) {
  switch (block['type']) {
    case 'heading':
      return HeadingWidget(block);
    case 'paragraph':
      return ParagraphWidget(block);
    case 'title_paragraph':
      return TitleParagraphWidget(block);
    case 'ordered_list':
      return OrderedListWidget(block);
    case 'unordered_list':
      return UnorderedListWidget(block);
    case 'divider':
      return DividerWidget(block);
    case 'link':
      return LinkButtonWidget(block);
    case 'image':
      return ImageWidget(block);
    case 'warning_box':
      return WarningBoxWidget(block);
    case 'html':
      return HtmlWidget(block);
    default:
      return SizedBox.shrink(); // ← تجاهل الأنواع غير المعروفة
  }
}
```

```js
// React Native — مثال
const BlockRenderer = ({ block }) => {
  switch (block.type) {
    case 'heading':       return <HeadingBlock block={block} />;
    case 'paragraph':     return <ParagraphBlock block={block} />;
    case 'title_paragraph': return <TitleParagraphBlock block={block} />;
    case 'ordered_list':  return <OrderedListBlock block={block} />;
    case 'unordered_list': return <UnorderedListBlock block={block} />;
    case 'divider':       return <DividerBlock block={block} />;
    case 'link':          return <LinkBlock block={block} />;
    case 'image':         return <ImageBlock block={block} />;
    case 'warning_box':   return <WarningBoxBlock block={block} />;
    case 'html':          return <HtmlBlock block={block} />;
    default:              return null; // ← تجاهل الأنواع المجهولة
  }
};
```

### بناء الصفحة الكاملة

```dart
// Flutter
class CmsPage extends StatelessWidget {
  final CmsPageData data;

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: data.direction == 'rtl'
          ? TextDirection.rtl
          : TextDirection.ltr,
      child: ListView.builder(
        padding: EdgeInsets.all(16),
        itemCount: data.blocks.length,
        itemBuilder: (ctx, i) => Padding(
          padding: EdgeInsets.only(bottom: 12),
          child: buildBlock(data.blocks[i]),
        ),
      ),
    );
  }
}
```

---

## 7. الصفحات المتاحة حالياً

| الاسم | الـ Slug | الوصف |
|-------|---------|-------|
| سياسة الخصوصية | `privacy-policy` | 17 قسم — DSGVO compliant |
| شروط الاستخدام | `terms-conditions` | 15 قسم — AGB |
| بيانات النشر | `impressum` | Impressum قانوني — TMG § 5 |

### روابط جاهزة

```
/api/pages/privacy-policy?lang=ar
/api/pages/privacy-policy?lang=de
/api/pages/privacy-policy?lang=en

/api/pages/terms-conditions?lang=ar
/api/pages/terms-conditions?lang=de
/api/pages/terms-conditions?lang=en

/api/pages/impressum?lang=ar
/api/pages/impressum?lang=de
/api/pages/impressum?lang=en
```

---

## 8. حالات الخطأ

### `404 Not Found` — صفحة غير موجودة أو معطلة

```json
{
  "message": "الصفحة غير موجودة."
}
```

**متى يحدث:**
- الـ slug غلط
- الصفحة موجودة لكن `is_active = false`

**التعامل معه:** اعرض رسالة خطأ مناسبة أو ارجع للصفحة السابقة.

### `500 Server Error`

**التعامل معه:** اعرض رسالة خطأ عامة + زر "إعادة المحاولة".

### جدول سلوكيات مهمة

| الحالة | ما يحدث |
|--------|---------|
| `lang` غير موجودة في النظام | يُرجع محتوى اللغة الافتراضية (`ar`) |
| بلوك لا يحتوي ترجمة للغة المطلوبة | يُرجع ترجمة العربية كـ fallback |
| بلوك معطل (`is_active = false`) | **لا يظهر** في الـ response أبداً |
| `content` فارغ لبلوك | البلوك لا يظهر في الـ response |

---

## 9. أفضل الممارسات

### ✅ Cache الاستجابة محلياً

الصفحات لا تتغير كثيراً — احفظها locally:

```dart
// مثال: cache لـ 24 ساعة
final cached = await storage.get('cms_page_${slug}_${lang}');
if (cached != null && !isExpired(cached)) {
  return cached;
}
final fresh = await api.getCmsPage(slug, lang);
await storage.set('cms_page_${slug}_${lang}', fresh, ttl: Duration(hours: 24));
return fresh;
```

### ✅ تمرير اللغة دائماً صراحةً

```dart
// ✅ صح
api.getCmsPage('privacy-policy', lang: currentLanguage);

// ❌ خطأ — لا تترك اللغة للـ header فقط
api.getCmsPage('privacy-policy');
```

### ✅ تطبيق `direction` على أعلى مستوى

لا تفترض الاتجاه — اقرأه دائماً من الـ response:

```dart
// ✅ صح
final direction = response.data.direction; // 'rtl' أو 'ltr'

// ❌ خطأ
final direction = currentLang == 'ar' ? 'rtl' : 'ltr'; // لا تحدد بنفسك
```

### ✅ تجاهل الأنواع المجهولة بصمت

```dart
// ✅ صح
default: return SizedBox.shrink(); // لا شيء

// ❌ خطأ
default: throw Exception('Unknown block type');
```

### ✅ تعامل مع `\n` في النصوص

```dart
// Flutter
Text(block['content']['text'].replaceAll('\\n', '\n'))

// React Native — whitespace: 'pre-line' في الـ style
<Text style={{ whiteSpace: 'pre-line' }}>{block.content.text}</Text>
```

### ✅ `content` قد يكون فارغاً للـ `divider`

```dart
final content = block['content'] as Map? ?? {};
final text = content['text'] as String? ?? '';
```

---

## جدول سريع — كل نوع بلوك دفعة واحدة

| `type` | حقول `content` | ملاحظة |
|--------|----------------|--------|
| `heading` | `text` | `props.level`: h1–h4 |
| `paragraph` | `text` | قد يحتوي `\n` |
| `title_paragraph` | `title` + `text` | عنوان صغير + فقرة |
| `ordered_list` | `items: string[]` | مع أرقام 1, 2, 3... |
| `unordered_list` | `items: string[]` | مع نقاط • |
| `divider` | `{}` (فارغ) | `props.orientation`: h/v |
| `link` | `label` + `url` | `props.target`: same/external |
| `image` | `url` + `alt` | URL كامل مع domain |
| `warning_box` | `text` | صندوق تحذير مميز |
| `html` | `html` | HTML نظيف |

---

## مثال كامل — Response لصفحة بسيطة

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
        "props": { "level": "h1", "alignment": "right", "color": "primary" },
        "content": { "text": "سياسة الخصوصية" }
      },
      {
        "id": "uuid-2",
        "type": "paragraph",
        "props": { "alignment": "right", "color": "default" },
        "content": { "text": "نحن في Look up OHG نولي حماية البيانات أهمية كبيرة." }
      },
      {
        "id": "uuid-3",
        "type": "divider",
        "props": { "orientation": "horizontal", "size": "sm", "color": "default" },
        "content": {}
      },
      {
        "id": "uuid-4",
        "type": "heading",
        "props": { "level": "h2", "alignment": "right", "color": "default" },
        "content": { "text": "١. مقدمة" }
      },
      {
        "id": "uuid-5",
        "type": "title_paragraph",
        "props": { "alignment": "right", "color": "default" },
        "content": {
          "title": "٣.١ إنشاء الحساب",
          "text": "عند إنشاء حساب داخل التطبيق، قد يتم جمع البيانات التالية:"
        }
      },
      {
        "id": "uuid-6",
        "type": "unordered_list",
        "props": { "alignment": "right" },
        "content": {
          "items": ["الاسم الأول واسم العائلة", "البريد الإلكتروني", "رقم الهاتف"]
        }
      },
      {
        "id": "uuid-7",
        "type": "warning_box",
        "props": { "alignment": "right" },
        "content": { "text": "يسمح باستخدام التطبيق فقط للأشخاص الذين أتموا 16 عامًا." }
      },
      {
        "id": "uuid-8",
        "type": "link",
        "props": { "target": "external", "alignment": "center" },
        "content": { "label": "تواصل معنا", "url": "mailto:info@lookupfriseur.de" }
      }
    ]
  }
}
```

---

*وثيقة تقنية — Look Up App — نظام CMS Pages*  
*Backend: Laravel 12 + FilamentPHP 5*
