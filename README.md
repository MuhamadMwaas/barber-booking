# BarberBooking — Beauty Salon Management System

> **نظام إدارة صالون تجميل** — Laravel 12 + Filament 4 + PostgreSQL  
> العملاء يحجزون مواعيد مع مزودين، يدفعون نقدًا في المحل، تُطبع فواتير مطابقة للضريبة الألمانية.

---

## 📚 ابدأ من هنا — التوثيق الشامل

> **المدخل الموحد لكل التوثيق:** [`docs/README.md`](docs/README.md)

التوثيق موزّع على **10 أقسام**، كل قسم مجلد فيه `README.md` + ملفات تفصيلية. أي مهندس يقرأه يفهم الفكرة والآلية والبنية والكود بعمق.

| # | القسم | ماذا فيه | الرابط |
|---|-------|----------|--------|
| 01 | **نظرة عامة** | فكرة النظام، نموذج العمل، الأدوار | [`docs/01-overview/README.md`](docs/01-overview/README.md) |
| 02 | **البنية والمعمارية** | التقنيات، هيكل المجلدات، دورة الطلب، القرارات التصميمية | [`docs/02-architecture/README.md`](docs/02-architecture/README.md) |
| 03 | **النماذج والبيانات** | 46 Model + 74 Migration + 8 Enums + ERD | [`docs/03-data-models/README.md`](docs/03-data-models/README.md) |
| 04 | **طبقة الخدمات** | 71 Service — القلب الحقيقي للنظام | [`docs/04-services/README.md`](docs/04-services/README.md) |
| 05 | **واجهات الـ API** | كل الـ Endpoints + Auth + Availability + Booking | [`docs/05-api/README.md`](docs/05-api/README.md) |
| 06 | **تدفق الحجز بعمق** | التزامن، الإجازات، العميل الحر، إضافة خدمة | [`docs/06-booking-flow/README.md`](docs/06-booking-flow/README.md) |
| 07 | **لوحة الإدارة Filament** | 18 Resource + Staff Dashboard + Livewire | [`docs/07-filament-admin/README.md`](docs/07-filament-admin/README.md) |
| 08 | **الفوترة والطباعة** | DRAFT→PAID + القوالب + الطباعة | [`docs/08-invoicing-printing/README.md`](docs/08-invoicing-printing/README.md) |
| 09 | **الأمان والامتثال** | OTP + Rate Limiting + Roles + TSE | [`docs/09-security/README.md`](docs/09-security/README.md) |
| 10 | **التشغيل والإعداد** | Config + Seeders + Deployment | [`docs/10-operations/README.md`](docs/10-operations/README.md) |

### مراجع سريعة

| الملف | لمن |
|-------|-----|
| [`Agent.md`](Agent.md) | مرجع AI شامل (1000+ سطر) — للـ AI Agents |
| [`API.md`](API.md) | مرجع API عربي مفصل (1700+ سطر) — للموبايل/الفرونت |
| [`docs/BOOKING_FLOW.md`](docs/BOOKING_FLOW.md) | تدفق الحجز المفصل (1191 سطر) — المرجع الأدق للحجز |

---

## ⚡ التشغيل السريع

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

اقرأ [`docs/10-operations/deployment.md`](docs/10-operations/deployment.md) للتفصيل.

### بيانات دخول افتراضية

| الدور | Email | Password |
|-------|-------|----------|
| Admin | `admin@elitebeauty.ae` | `password` |
| Customer | `hala.alhashimi@gmail.com` | `password` |

---

## 🏗️ التقنيات

| الطبقة | التقنية |
|--------|---------|
| Framework | Laravel 12 (PHP 8.2) |
| Admin | Filament 4.0 |
| DB | PostgreSQL (Neon) |
| Auth | Sanctum + Spatie Permissions |
| Frontend | Vite + TailwindCSS 4 |
| Notifications | OneSignal + Mail + SMS (Seven.io/Vonage) |

التفصيل في [`docs/02-architecture/tech-stack.md`](docs/02-architecture/tech-stack.md).

---

## 📐 القرارات التصميمية الكبرى

- **GROSS pricing** — الأسعار شاملة الضريبة، تُستخرج عكسيًا — `docs/02-architecture/design-decisions.md`
- **bcmath** — كل حسابات المال بـ bcmath لا float
- **Two-stage invoicing** — مسودة عند الحجز → مدفوعة عند الدفع
- **BookingLockService** — `SELECT FOR UPDATE` على `users` لمنع الحجز المزدوج

---

## 🤝 المساهمة في التوثيق

التوثيق يعيش مع الكود — أي تغيير في `BookingService` أو `InvoiceService` يجب أن يُحدَّث في `docs/04-services/` و `docs/06-booking-flow/` و `docs/08-invoicing-printing/`.

---

## 📄 الرخصة

MIT — Laravel Framework
