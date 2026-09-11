# 05 — واجهات الـ API

> **الملفات:** `routes/api.php:1` (412 سطر)، `routes/web.php:1` (215 سطر)، `app/Http/Controllers/Api/` (21 controller)

---

## 1. فهرس كل الـ Routes

### Public — بلا توثيق

| Method | Path | Controller | الغرض | Throttle |
|--------|------|-----------|-------|----------|
| GET | `/api/services` | `ServicesController@index` | قائمة الخدمات | — |
| GET | `/api/services/{id}` | `ServicesController@show` | تفاصيل خدمة | — |
| GET | `/api/providers` | `ProvidersController@index` | قائمة المزودين | — |
| GET | `/api/providers/{id}` | `ProvidersController@show` | تفاصيل مزود | — |
| GET | `/api/availability/service` | `AvailabilityController@getServiceAvailability` | كل المزودين المتاحين لخدمة في يوم | 40/min |
| GET | `/api/availability/provider` | `AvailabilityController@getProviderAvailability` | Slots مزود محدد | 40/min |
| GET | `/api/availability/calendar` | `AvailabilityController@getAvailabilityCalendar` | تقويم 31 يوم | 30/min |
| GET | `/api/pages/{slug}` | `CmsPageController@show` | صفحة CMS | — |
| GET | `/api/sliders/{key}` | `SliderController@show` | سلايدر | — |
| GET | `/api/about-us` | `AboutUsPageController@show` | من نحن | — |

### Auth — `prefix('auth')`

| Method | Path | Throttle | الغرض |
|--------|------|----------|-------|
| POST | `/api/auth/register` | `auth-register` | تسجيل |
| POST | `/api/auth/login` | `auth-login` | دخول |
| POST | `/api/auth/refresh` | `auth-refresh` | تحديث token |
| POST | `/api/auth/logout` | `auth:sanctum` | خروج |
| POST | `/api/auth/request-otp` | `otp-send` | طلب OTP |
| POST | `/api/auth/verify-otp` | `otp-verify` | تحقق OTP |
| POST | `/api/auth/verify-email-otp` | `otp-verify` | تحقق بريد (wrapper) |
| POST | `/api/auth/resend-verification-otp` | `otp-send` | إعادة إرسال |
| POST | `/api/auth/forgot-password` | `5,1` | نسيت كلمة المرور |
| POST | `/api/auth/password/verify-otp` | `10,1` | تحقق OTP للـ reset |
| POST | `/api/auth/reset-password` | `10,1` | إعادة تعيين |
| POST | `/api/auth/google` | `auth-social` | Google OAuth |
| POST | `/api/auth/google/mobile` | `auth-social` | Google Mobile |

### Authenticated — `auth:sanctum + verified.customer`

| Method | Path | الغرض |
|--------|------|-------|
| GET/POST | `/api/profile` | عرض/تحديث الملف |
| POST | `/api/profile/change-password` | تغيير كلمة المرور |
| DELETE | `/api/profile` | حذف الحساب |
| GET | `/api/settings` | كتالوج الإعدادات |
| PATCH | `/api/settings/{key}` | تحديث إعداد واحد |
| POST | `/api/register-device` | تسجيل جهاز push |
| POST | `/api/deregister-device` | إلغاء جهاز |
| GET | `/api/appointments` | قائمة المواعيد (paginated+filtered) |
| GET | `/api/appointments/statistics` | إحصائيات |
| GET | `/api/appointments/upcoming` | القادمة |
| GET | `/api/appointments/past` | السابقة |
| GET | `/api/appointments/search` | بحث |
| GET | `/api/appointments/{id}` | تفاصيل |
| POST | `/api/appointments/{id}/cancel` | إلغاء |
| GET | `/api/appointments/reminders/options` | خيارات التذكير (60/min) |
| POST | `/api/appointments/reminders` | حفظ تذكير (30/min) |
| GET | `/api/appointments/{id}/reminders` | عرض تذكير (60/min) |
| DELETE | `/api/appointments/{id}/reminders` | حذف تذكير (30/min) |
| GET | `/api/bookings` | قائمة الحجوزات |
| POST | `/api/bookings` | إنشاء حجز |
| GET | `/api/bookings/{id}` | تفاصيل حجز |
| POST | `/api/bookings/{id}/cancel` | إلغاء حجز |
| POST | `/api/invoice/{id}/print` | طباعة فاتورة |
| POST | `/api/invoices/print-batch` | طباعة دفعية |

### Web — `routes/web.php:1`

| Method | Path | الغرض |
|--------|------|-------|
| GET | `/` | Landing (`LandingController@index`) |
| GET | `/app` | صفحة التطبيق |
| GET | `/admin` | Filament |
| GET | `/invoice/{id}/print` | طباعة (web, auth) |
| GET | `/invoice-template/{id}/preview` | معاينة قالب |

---

## 2. Headers المطلوبة

```
Accept: application/json                    // لكل الطلبات
Authorization: Bearer {access_token}        // للمحمية
Content-Type: application/json              // لـ JSON body
Content-Type: multipart/form-data           // لرفع الصور
Accept-Language: ar  أو  ?lang=ar           // للغة (lang أعلى أولوية)
```

---

## 3. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`authentication.md`](authentication.md) | التسجيل، الدخول، OTP، Google، Refresh |
| [`availability.md`](availability.md) | `/availability/*` + Rate Limiting + reason_code |
| [`booking.md`](booking.md) | `POST /bookings` + الحجوزات + الإلغاء |
| [`appointment.md`](appointment.md) | `AppointmentController` + `AppointmentReminderController` |
| [`profile-and-settings.md`](profile-and-settings.md) | Profile + Settings + Devices |
| [`cms-and-landing.md`](cms-and-landing.md) | CMS + Sliders + Landing |
| [`print.md`](print.md) | طباعة الفواتير |

> **المرجع الكامل للـ API بالعربية مع أمثلة Request/Response:** [`API.md`](../../API.md) (1700+ سطر)

---

*التالي: [`authentication.md`](authentication.md)*
