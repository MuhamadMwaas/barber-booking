# CMS & Landing API

> **الملفات:** `app/Http/Controllers/Api/CmsPageController.php:1`، `app/Http/Controllers/LandingController.php:1`، `app/Services/Cms/CmsPageCacheService.php:1`

---

## 1. `GET /api/pages/{slug}` — صفحة CMS

```
GET /api/pages/privacy?lang=ar
→ 200 { slug, title, blocks:[{type, data}], lang }
→ 404 غير موجود
```

- Cache per `slug+lang` — `CmsPageCacheService`.
- `CmsLanguageResolver` — `?lang` > `Accept-Language` > default.

## 2. `GET /api/sliders/{key}` — سلايدر

```
GET /api/sliders/home?lang=ar
→ 200 { key, items:[{image, title, subtitle, link}] }
```

`SliderController.php:1` — `Slider` + `SliderItem` + `SliderItemTranslation`.

## 3. `GET /about-us` — من نحن

```
GET /api/about-us?lang=ar
→ 200 { page, team:[{name, role, avatar}] }
```

## 4. Web Landing — `routes/web.php:1`

```
GET /          → LandingController@index (hero, features, gallery, services, CTA)
GET /app       → صفحة التطبيق
GET /page/{slug} → Legal (TOC + anchored blocks) — LandingContent + LegalDocument
GET /language/{code} → switch locale
```

`LandingController.php:1` + `LandingContent.php` + `LegalDocument.php` — `resources/views/landing/`.

---

*التالي: [`print.md`](print.md)*
