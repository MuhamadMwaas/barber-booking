<div dir="rtl">

# خطة اختبار API الشاملة

## 1. الهدف

هذه الوثيقة هي خطة اختبار API شاملة لتطبيق BarberBooking، وهدفها:

- ضمان عدم نسيان أي endpoint أو سيناريو أساسي قبل الاختبار أو قبل الإصدار.
- توفير مرجع تنفيذي واضح لمطور Backend أو من يقوم باختبار الـ API يدوياً.
- تغطية التدفقات الرئيسية والحدودية وقواعد العمل والنتائج المتوقعة لكل endpoint فعلي موجود في النظام.
- توفير Checklist عملية قابلة للاستخدام أثناء الاختبار الفعلي.

## 2. نطاق الخطة

تشمل هذه الخطة جميع endpoints الفعلية الموجودة في API حالياً، باستثناء endpoint الاختباري الخاص بالإشعارات.

### داخل النطاق

- GET /api/user
- Auth API
- Google Mobile Auth فقط
- OTP flows
- Profile API
- Providers API
- Services API
- Availability API
- Bookings API
- Appointments API
- Appointment Reminder creation
- Devices API
- Print API

### خارج النطاق حالياً

- Guest booking
- حالات المستخدم غير الموثق بالبريد
- حالات المستخدم غير النشط
- Multi-branch scenarios
- Locale testing للخدمات والترجمات
- Notification test endpoint: POST /api/noticifation/test-send-to-all
- Google Web Redirect/Callback

## 3. الافتراضات

- بيئة الاختبار الحالية Local.
- قيمة Base URL ستحدد لاحقاً، وسيشار لها في هذه الخطة بالمتغير `{{base_url}}`.
- تم تشغيل seeders الأساسية.
- يوجد حساب admin وحساب customer وحسابات providers seeded مسبقاً.
- يوجد appointments وفواتير وطابعات seeded مسبقاً.
- قيمة `book_buffer` الحالية هي `0`.
- التطبيق يعمل على فرع واحد فقط حالياً.
- الاختبار يركز على السلوك الحالي الفعلي في الكود.

## 4. بيئة التنفيذ المقترحة

### Headers الأساسية

```http
Accept: application/json
```

### Headers للطلبات المحمية

```http
Authorization: Bearer {access_token}
Accept: application/json
```

### ملاحظات تنفيذية

- استخدم Postman Environment أو متغيرات مماثلة لحفظ `base_url`, `access_token`, `refresh_token`, `customer_id`, `provider_id`, `service_id`, `appointment_id`, `invoice_id`, `printer_id`.
- لا تعتمد على IDs ثابتة للحجوزات أو الخدمات أو المزودين، بل استخرجها من استجابات endpoints سابقة أثناء التنفيذ.
- يمكن الاعتماد على البريد الإلكتروني وكلمة المرور للحسابات المرجعية لأنها seeded بشكل ثابت.

## 5. الحسابات المرجعية

| النوع | البريد الإلكتروني | كلمة المرور | الاستخدام |
|---|---|---|---|
| Admin | admin@elitebeauty.ae | password | اختبارات الإدارة والطباعة والحساب المرجعي |
| Customer | hala.alhashimi@gmail.com | password | اختبارات customer الأساسية |

### بيانات مرجعية ديناميكية يجب استخراجها أثناء التنفيذ

- `provider_id`: من GET /api/providers
- `service_id`: من GET /api/services
- `appointment_id`: من GET /api/appointments أو من نتيجة إنشاء الحجز
- `invoice_id`: من بيانات الطباعة أو من قاعدة البيانات/استجابة مرتبطة بحجز مكتمل
- `printer_id`: من بيانات Print API أو من قاعدة البيانات إذا لزم

## 6. استراتيجية بيانات الاختبار

### بيانات يجب تجهيزها أو استخراجها قبل تنفيذ السيناريوهات

1. Customer صالح لتسجيل الدخول.
2. Admin صالح لتسجيل الدخول.
3. Provider يقدم خدمة فعالة واحدة على الأقل.
4. Service فعالة لديها provider واحد على الأقل.
5. Appointment مستقبلية قابلة للإلغاء.
6. Appointment مكتملة.
7. Appointment ملغاة.
8. Invoice مرتبطة بموعد أو حجز صالح للطباعة.
9. Device ID تجريبي مثل `device-qa-001`.
10. ملف صورة صالح لاختبار رفع صورة الملف الشخصي.

### أمثلة بيانات طلبات جاهزة

#### تسجيل مستخدم جديد

```json
{
  "first_name": "API",
  "last_name": "Tester",
  "email": "api.tester+001@example.com",
  "phone": "+971500000001",
  "password": "Password1@",
  "password_confirmation": "Password1@"
}
```

#### تسجيل الدخول

```json
{
  "email": "hala.alhashimi@gmail.com",
  "password": "password"
}
```

#### إنشاء حجز خدمة واحدة

```json
{
  "date": "{{booking_date}}",
  "payment_method": "cash",
  "notes": "QA booking",
  "services": [
    {
      "service_id": {{service_id}},
      "provider_id": {{provider_id}},
      "start_time": "10:00"
    }
  ]
}
```

#### إنشاء حجز متعدد الخدمات

```json
{
  "date": "{{booking_date}}",
  "payment_method": "cash",
  "notes": "QA multi service booking",
  "services": [
    {
      "service_id": {{service_id_1}},
      "provider_id": {{provider_id}},
      "start_time": "10:00"
    },
    {
      "service_id": {{service_id_2}},
      "provider_id": {{provider_id}},
      "start_time": "10:30"
    }
  ]
}
```

#### تسجيل جهاز

```json
{
  "device_id": "device-qa-001",
  "device_token": "fcm-token-001",
  "platform": "android",
  "os_version": "14",
  "app_version": "1.0.0",
  "meta": {
    "brand": "Google",
    "model": "Pixel"
  }
}
```

## 7. مصفوفة الأولويات

| الأولوية | المعنى |
|---|---|
| P0 | لا يمكن الإصدار بدون نجاحها |
| P1 | مهمة جداً ويجب تنفيذها في نفس دورة الاختبار إن أمكن |
| P2 | تغطية إضافية وتوسعة مهمة ولكن ليست مانعة للإصدار |

## 8. معايير الشدة Severity

| الشدة | المعنى |
|---|---|
| Critical | تعطل تدفق أساسي أو أمان أو مصادقة أو حجز رئيسي |
| High | تؤثر على وظيفة رئيسية لكن يوجد بديل جزئي |
| Medium | خلل وظيفي غير قاتل أو بيانات غير دقيقة |
| Low | خلل ثانوي أو عرضي |

## 9. خريطة الـ API المغطاة

| القسم | Endpoints |
|---|---|
| User | GET /api/user |
| Auth | register, login, refresh, logout, forgot-password, reset-password, google/mobile, verify-email-otp, resend-verification-otp, request-otp, verify-otp |
| Profile | profile show, update, change-password |
| Providers | index, show |
| Services | index, show |
| Availability | provider, calendar |
| Bookings | index, store, show, cancel |
| Appointments | index, statistics, upcoming, past, search, show, cancel |
| Reminder | POST /api/appointments/reminders |
| Devices | register-device, deregister-device |
| Print | print, print-batch, print-url, printer test, statistics, logs |

## 10. سيناريوهات الاختبار التفصيلية

## 10.1 User Endpoint

### GET /api/user

1. TC-USER-001: جلب بيانات المستخدم الحالي بنجاح
Priority: P1
Severity: High
Preconditions: تسجيل دخول ناجح والحصول على `access_token`.
Expected: حالة 200 وإرجاع بيانات المستخدم الحالي المرتبطة بالتوكن.

2. TC-USER-002: رفض الطلب بدون توكن
Priority: P0
Severity: Critical
Preconditions: لا يوجد.
Expected: حالة 401.

3. TC-USER-003: رفض الطلب بتوكن غير صالح
Priority: P0
Severity: Critical
Preconditions: توكن عشوائي أو منتهي.
Expected: حالة 401.

## 10.2 Authentication API

### POST /api/auth/register

1. TC-AUTH-REG-001: إنشاء حساب جديد بنجاح
Priority: P0
Severity: Critical
Preconditions: Email وPhone غير مستخدمين مسبقاً.
Expected: حالة 201 وإرجاع `user`, `access_token`, `refresh_token`, `token_type`, `otp`.

2. TC-AUTH-REG-002: رفض التسجيل عند تكرار البريد الإلكتروني
Priority: P0
Severity: High
Preconditions: استخدام بريد seeded معروف.
Expected: حالة 422 مع خطأ validation على `email`.

3. TC-AUTH-REG-003: رفض التسجيل عند تكرار الهاتف
Priority: P1
Severity: High
Preconditions: استخدام رقم هاتف seeded معروف.
Expected: حالة 422 مع خطأ validation على `phone`.

4. TC-AUTH-REG-004: رفض التسجيل عند كلمة مرور ضعيفة
Priority: P0
Severity: High
Preconditions: كلمة مرور لا تطابق policy.
Expected: حالة 422.

5. TC-AUTH-REG-005: رفض التسجيل عند عدم تطابق `password_confirmation`
Priority: P0
Severity: High
Preconditions: كلمة المرور والتأكيد مختلفان.
Expected: حالة 422.

### POST /api/auth/login

1. TC-AUTH-LOGIN-001: تسجيل الدخول بنجاح
Priority: P0
Severity: Critical
Preconditions: حساب seeded صالح.
Expected: حالة 200 وإرجاع `user`, `access_token`, `refresh_token`, `token_type`.

2. TC-AUTH-LOGIN-002: رفض كلمة مرور خاطئة
Priority: P0
Severity: Critical
Preconditions: بريد صحيح وكلمة مرور خاطئة.
Expected: حالة 401 ورسالة `Invalid credentials`.

3. TC-AUTH-LOGIN-003: رفض بريد غير موجود
Priority: P0
Severity: High
Preconditions: بريد غير موجود.
Expected: حالة 401.

### POST /api/auth/refresh

1. TC-AUTH-REFRESH-001: تحديث access token بنجاح
Priority: P0
Severity: Critical
Preconditions: Refresh token صالح.
Expected: حالة 200 وإرجاع `access_token`, `access_expires_at`.

2. TC-AUTH-REFRESH-002: رفض refresh token غير صالح
Priority: P0
Severity: Critical
Preconditions: Token غير صالح.
Expected: حالة 401 ورسالة `Invalid or expired refresh token`.

3. TC-AUTH-REFRESH-003: رفض الطلب عند غياب `refresh_token`
Priority: P1
Severity: High
Preconditions: لا يوجد.
Expected: حالة 422.

### POST /api/auth/logout

1. TC-AUTH-LOGOUT-001: تسجيل الخروج بنجاح
Priority: P0
Severity: Critical
Preconditions: Access token صالح.
Expected: حالة 200 ورسالة `Logged out`.

2. TC-AUTH-LOGOUT-002: التأكد من إبطال access token بعد logout
Priority: P0
Severity: Critical
Preconditions: تنفيذ logout ثم إعادة استخدام نفس التوكن.
Expected: الطلبات المحمية اللاحقة تعود 401.

3. TC-AUTH-LOGOUT-003: رفض logout بدون توكن
Priority: P0
Severity: Critical
Preconditions: لا يوجد.
Expected: حالة 401.

### POST /api/auth/forgot-password

1. TC-AUTH-FORGOT-001: إرسال OTP بنجاح إلى بريد موجود
Priority: P0
Severity: Critical
Preconditions: بريد مستخدم موجود.
Expected: حالة 200 وإرجاع `message` و`otp` في بيئة الاختبار الحالية.

2. TC-AUTH-FORGOT-002: رفض الطلب عند بريد غير صالح صياغياً
Priority: P1
Severity: High
Preconditions: بريد بصيغة خاطئة.
Expected: حالة 422.

3. TC-AUTH-FORGOT-003: التعامل مع بريد غير موجود
Priority: P1
Severity: Medium
Preconditions: بريد غير موجود.
Expected: حسب التنفيذ الحالي قد تعاد 404 بسبب `firstOrFail`; يجب توثيق النتيجة الفعلية أثناء التنفيذ.

### POST /api/auth/reset-password

1. TC-AUTH-RESET-001: إعادة تعيين كلمة المرور بنجاح باستخدام OTP صحيح
Priority: P0
Severity: Critical
Preconditions: OTP صالح غير منتهي.
Expected: حالة 200 ورسالة `Password reset successful`.

2. TC-AUTH-RESET-002: رفض OTP غير صالح أو منتهي
Priority: P0
Severity: High
Preconditions: OTP خاطئ.
Expected: حالة 422 ورسالة `Invalid or expired OTP`.

3. TC-AUTH-RESET-003: رفض كلمة مرور لا تطابق policy
Priority: P1
Severity: High
Preconditions: كلمة مرور ضعيفة.
Expected: حالة 422.

4. TC-AUTH-RESET-004: التحقق من إمكانية تسجيل الدخول بكلمة المرور الجديدة بعد reset
Priority: P0
Severity: Critical
Preconditions: نجاح reset password.
Expected: login ناجح بكلمة المرور الجديدة.

### POST /api/auth/google/mobile

1. TC-AUTH-GM-001: تسجيل الدخول عبر Google Mobile بنجاح
Priority: P1
Severity: High
Preconditions: Google id token صالح في البيئة المتاحة.
Expected: حالة نجاح وإرجاع access token وبيانات المستخدم.

2. TC-AUTH-GM-002: رفض Google token غير صالح
Priority: P1
Severity: High
Preconditions: Token خاطئ أو منتهي.
Expected: خطأ 4xx مناسب.

### POST /api/auth/verify-email-otp

1. TC-AUTH-VEMAIL-001: التحقق من البريد عبر OTP بنجاح
Priority: P1
Severity: High
Preconditions: OTP صالح لحساب جديد.
Expected: حالة 200 مع `email_verified: true` أو نتيجة نجاح مكافئة.

2. TC-AUTH-VEMAIL-002: رفض OTP خاطئ
Priority: P1
Severity: High
Preconditions: OTP غير صحيح.
Expected: 422 أو خطأ business/validation مناسب.

### POST /api/auth/resend-verification-otp

1. TC-AUTH-RESEND-001: إعادة إرسال OTP بنجاح
Priority: P1
Severity: Medium
Preconditions: حساب غير محقق وجاهز للاختبار عند الحاجة.
Expected: 200 ورسالة نجاح.

2. TC-AUTH-RESEND-002: رفض إعادة الإرسال لحالة غير مناسبة
Priority: P2
Severity: Low
Preconditions: حساب محقق أو بريد غير موجود.
Expected: خطأ مناسب حسب السلوك الفعلي.

### POST /api/auth/request-otp

1. TC-AUTH-REQOTP-001: طلب OTP بريد بنجاح
Priority: P1
Severity: Medium
Preconditions: Email موجود أو صالح حسب سلوك التنفيذ.
Expected: 200 ورسالة نجاح.

2. TC-AUTH-REQOTP-002: رفض نوع OTP غير صحيح
Priority: P1
Severity: Medium
Preconditions: `type` غير مدعوم.
Expected: 422.

### POST /api/auth/verify-otp

1. TC-AUTH-VERIFYOTP-001: التحقق من OTP بنجاح
Priority: P1
Severity: Medium
Preconditions: OTP صالح.
Expected: 200 ورسالة نجاح.

2. TC-AUTH-VERIFYOTP-002: رفض OTP غير صالح
Priority: P1
Severity: Medium
Preconditions: OTP خاطئ أو منتهي.
Expected: 422.

## 10.3 Profile API

### GET /api/profile

1. TC-PROFILE-GET-001: جلب الملف الشخصي بنجاح
Priority: P0
Severity: High
Preconditions: توكن صالح.
Expected: 200 مع `success=true`, `message`, `data`.

2. TC-PROFILE-GET-002: رفض بدون توكن
Priority: P0
Severity: Critical
Expected: 401.

### POST /api/profile

1. TC-PROFILE-UPD-001: تحديث الاسم والهاتف والعنوان بنجاح
Priority: P0
Severity: High
Preconditions: توكن صالح.
Expected: 200 مع بيانات المستخدم المحدثة.

2. TC-PROFILE-UPD-002: رفع صورة شخصية بنجاح
Priority: P1
Severity: Medium
Preconditions: ملف صورة صالح أقل من 2MB.
Expected: 200 وتحديث `avatar_url` أو مرجع الصورة في البيانات.

3. TC-PROFILE-UPD-003: تحديث جزئي لحقل واحد فقط
Priority: P1
Severity: Medium
Preconditions: إرسال حقل واحد فقط مثل `city`.
Expected: 200 وعدم تغيير الحقول الأخرى.

4. TC-PROFILE-UPD-004: رفض الطلب بدون توكن
Priority: P0
Severity: Critical
Expected: 401.

### POST /api/profile/change-password

1. TC-PROFILE-PASS-001: تغيير كلمة المرور بنجاح
Priority: P0
Severity: Critical
Preconditions: معرفة كلمة المرور الحالية.
Expected: 200 ورسالة `Password updated`.

2. TC-PROFILE-PASS-002: رفض كلمة المرور الحالية الخاطئة
Priority: P0
Severity: High
Preconditions: `current_password` غير صحيحة.
Expected: 422 ورسالة `Current password incorrect`.

3. TC-PROFILE-PASS-003: رفض كلمة مرور جديدة ضعيفة
Priority: P1
Severity: High
Preconditions: كلمة مرور جديدة لا تطابق policy.
Expected: 422.

4. TC-PROFILE-PASS-004: التحقق من إبطال التوكنات القديمة بعد تغيير كلمة المرور
Priority: P0
Severity: Critical
Preconditions: نجاح تغيير كلمة المرور ثم إعادة استخدام التوكن القديم.
Expected: الطلبات المحمية بالتوكن القديم تعود 401.

## 10.4 Providers API

### GET /api/providers

1. TC-PROVIDERS-LIST-001: جلب قائمة المزودين بنجاح
Priority: P1
Severity: High
Expected: 200 مع قائمة paginated من providers.

2. TC-PROVIDERS-LIST-002: التحقق من `per_page`
Priority: P1
Severity: Medium
Expected: عدد العناصر في الصفحة يطابق المطلوب ضمن الحدود المدعومة.

3. TC-PROVIDERS-LIST-003: التحقق من `sort_by` و`sort_direction`
Priority: P1
Severity: Medium
Expected: النتائج مرتبة فعلاً حسب الحقل المطلوب.

4. TC-PROVIDERS-LIST-004: التحقق من البحث `search`
Priority: P1
Severity: Medium
Expected: إرجاع المزودين المطابقين فقط أو الأقرب.

### GET /api/providers/{id}

1. TC-PROVIDERS-SHOW-001: جلب مزود موجود بنجاح
Priority: P1
Severity: High
Expected: 200 مع بيانات المزود والخدمات والحجوزات إن كانت معادة.

2. TC-PROVIDERS-SHOW-002: طلب ID غير موجود
Priority: P1
Severity: Medium
Expected: 404.

3. TC-PROVIDERS-SHOW-003: طلب ID نصي أو غير صالح
Priority: P1
Severity: Medium
Expected: 404 أو 422 حسب سلوك التطبيق الفعلي.

## 10.5 Services API

### GET /api/services

1. TC-SERVICES-LIST-001: جلب قائمة الخدمات بنجاح
Priority: P1
Severity: High
Expected: 200 مع pagination وبيانات الفئة والمزودين إن كانت معادة.

2. TC-SERVICES-LIST-002: التحقق من الفلترة حسب `category_id`
Priority: P1
Severity: Medium
Expected: إرجاع خدمات الفئة المطلوبة فقط.

3. TC-SERVICES-LIST-003: التحقق من `featured`
Priority: P2
Severity: Low
Expected: إرجاع الخدمات المميزة فقط عند الطلب.

4. TC-SERVICES-LIST-004: التحقق من الترتيب والبحث
Priority: P1
Severity: Medium
Expected: النتائج مرتبة ومفلترة بشكل صحيح.

### GET /api/services/{id}

1. TC-SERVICES-SHOW-001: جلب خدمة موجودة بنجاح
Priority: P1
Severity: High
Expected: 200 مع بيانات الخدمة والفئة والمزودين و`reviews` إن وجدت.

2. TC-SERVICES-SHOW-002: طلب خدمة غير موجودة
Priority: P1
Severity: Medium
Expected: 404.

3. TC-SERVICES-SHOW-003: طلب ID نصي أو غير صالح
Priority: P1
Severity: Medium
Expected: 404 بسبب قيود route أو خطأ مناسب.

## 10.6 Availability API

### GET /api/availability/provider

1. TC-AVAIL-PROV-001: جلب الأوقات المتاحة لخدمة ومزود صالحين في تاريخ صالح
Priority: P0
Severity: Critical
Preconditions: مزود يقدم الخدمة ويعمل في التاريخ المختار.
Expected: 200 مع `available_slots` و`working_hours`.

2. TC-AVAIL-PROV-002: مزود لا يعمل في ذلك اليوم
Priority: P0
Severity: High
Preconditions: اختيار يوم غير دوام للمزود.
Expected: رسالة عمل مناسبة أو عدم وجود slots.

3. TC-AVAIL-PROV-003: Full day time off
Priority: P0
Severity: High
Preconditions: اختيار مزود بتاريخ عليه إجازة يوم كامل من البيانات المزروعة.
Expected: لا توجد slots أو رسالة توضح الإجازة.

4. TC-AVAIL-PROV-004: Hourly time off
Priority: P0
Severity: High
Preconditions: اختيار مزود وتاريخ ونافذة تتقاطع مع إجازة ساعية.
Expected: استبعاد الفترة المحجوبة فقط من slots.

5. TC-AVAIL-PROV-005: وقت خارج ساعات العمل
Priority: P0
Severity: High
Expected: رفض أو عدم تضمين الوقت خارج الدوام.

6. TC-AVAIL-PROV-006: تاريخ ماضٍ
Priority: P0
Severity: High
Expected: 4xx مناسب.

7. TC-AVAIL-PROV-007: خدمة غير مرتبطة بالمزود
Priority: P0
Severity: High
Expected: رفض منطقي مع رسالة مناسبة.

8. TC-AVAIL-PROV-008: أول slot في اليوم
Priority: P1
Severity: Medium
Expected: احتسابه بشكل صحيح إذا كان متاحاً.

9. TC-AVAIL-PROV-009: آخر slot يلامس نهاية الدوام تماماً
Priority: P1
Severity: Medium
Expected: يظهر إذا كانت المدة تنتهي عند نهاية الدوام تماماً.

10. TC-AVAIL-PROV-010: خدمة طويلة المدة
Priority: P1
Severity: Medium
Expected: تقليل slots المتاحة بما يتناسب مع المدة.

11. TC-AVAIL-PROV-011: يوم بدون جدول عمل
Priority: P1
Severity: Medium
Expected: لا توجد slots.

### GET /api/availability/calendar

1. TC-AVAIL-CAL-001: جلب تقويم التوفر لنطاق صالح
Priority: P0
Severity: High
Expected: 200 مع تواريخ تحتوي `available` و`slots_count`.

2. TC-AVAIL-CAL-002: رفض نطاق أكبر من 31 يوماً
Priority: P0
Severity: High
Expected: 400 أو 422 حسب التنفيذ.

3. TC-AVAIL-CAL-003: تاريخ بداية بعد تاريخ نهاية
Priority: P1
Severity: Medium
Expected: 422 أو 400.

4. TC-AVAIL-CAL-004: التحقق من تطابق وجود slots مع endpoint provider على نفس التاريخ
Priority: P0
Severity: High
Expected: إذا كان `slots_count > 0` يجب أن يمكن استخراج slots فعلية في endpoint المزود لنفس التاريخ.

## 10.7 Bookings API

### GET /api/bookings

1. TC-BOOKINGS-LIST-001: جلب قائمة حجوزات المستخدم الموثق
Priority: P0
Severity: High
Preconditions: Customer مسجل الدخول.
Expected: 200 مع الحجوزات الخاصة بالمستخدم فقط.

2. TC-BOOKINGS-LIST-002: الفلترة حسب الحالة
Priority: P1
Severity: Medium
Expected: إرجاع الحجوزات المطابقة فقط.

3. TC-BOOKINGS-LIST-003: رفض بدون توكن
Priority: P0
Severity: Critical
Expected: 401.

### POST /api/bookings

1. TC-BOOKINGS-CREATE-001: إنشاء حجز خدمة واحدة بنجاح
Priority: P0
Severity: Critical
Expected: 201 مع بيانات الحجز الكاملة و`status`, `payment_status`, `provider`, `services_details`.

2. TC-BOOKINGS-CREATE-002: إنشاء حجز متعدد الخدمات المتسلسلة بنجاح
Priority: P0
Severity: Critical
Expected: 201 مع أكثر من عنصر في `services_details` وترتيب `sequence_order` صحيح.

3. TC-BOOKINGS-CREATE-003: رفض عند عدم تسلسل أوقات الخدمات
Priority: P0
Severity: High
Expected: 422 أو business error مناسب.

4. TC-BOOKINGS-CREATE-004: رفض عند حجز وقت محجوز مسبقاً للمزود نفسه
Priority: P0
Severity: Critical
Expected: 422 ورسالة تفيد بأن الوقت محجوز.

5. TC-BOOKINGS-CREATE-005: رفض عند محاولة duplicate booking بنفس العميل والوقت والخدمات
Priority: P0
Severity: High
Expected: رفض business rule.

6. TC-BOOKINGS-CREATE-006: رفض تاريخ ماضٍ
Priority: P0
Severity: High
Expected: 422.

7. TC-BOOKINGS-CREATE-007: رفض تاريخ بعيد أكثر من `max_booking_days`
Priority: P0
Severity: High
Expected: 422 أو business error مناسب.

8. TC-BOOKINGS-CREATE-008: رفض عندما لا يقدم المزود الخدمة
Priority: P0
Severity: High
Expected: 422 أو business error مناسب.

9. TC-BOOKINGS-CREATE-009: رفض الوقت خارج ساعات الدوام
Priority: P0
Severity: High
Expected: 422 أو business error مناسب.

10. TC-BOOKINGS-CREATE-010: رفض عند وجود full-day أو hourly time off متعارضة
Priority: P0
Severity: High
Expected: 422.

11. TC-BOOKINGS-CREATE-011: التحقق من إنشاء draft invoice بشكل غير مباشر بعد نجاح الحجز
Priority: P1
Severity: Medium
Expected: ظهور بيانات مرتبطة لاحقاً في التدفقات أو قاعدة البيانات وفق السلوك الحالي.

12. TC-BOOKINGS-CREATE-012: التحقق من `created_status` بشكل غير مباشر من خلال تأثير الحجز على availability
Priority: P1
Severity: Medium
Expected: الحجز المؤكد حسب السلوك الحالي يجب أن يؤثر على availability بما يتسق مع نوع الدفع المستخدم.

13. TC-BOOKINGS-CREATE-013: اختبار التزامن على نفس الـ slot
Priority: P0
Severity: Critical
Preconditions: إرسال طلبين متقاربين جداً لنفس المزود والخدمة والوقت.
Expected: نجاح طلب واحد فقط ورفض الآخر أو منع التضارب.

### GET /api/bookings/{id}

1. TC-BOOKINGS-SHOW-001: جلب تفاصيل حجز يخص المستخدم الحالي
Priority: P0
Severity: High
Expected: 200 مع التفاصيل الكاملة.

2. TC-BOOKINGS-SHOW-002: منع الوصول إلى حجز لا يخص المستخدم
Priority: P0
Severity: Critical
Expected: 403.

3. TC-BOOKINGS-SHOW-003: طلب حجز غير موجود
Priority: P1
Severity: Medium
Expected: قد تكون 500 وفق الملاحظة المعروفة، ويجب توثيق النتيجة الفعلية.

### POST /api/bookings/{id}/cancel

1. TC-BOOKINGS-CANCEL-001: إلغاء حجز Pending مستقبلي بنجاح
Priority: P0
Severity: Critical
Expected: 200 وتحديث `status` و`cancelled_at`.

2. TC-BOOKINGS-CANCEL-002: رفض إلغاء حجز غير Pending
Priority: P0
Severity: High
Expected: 422.

3. TC-BOOKINGS-CANCEL-003: منع إلغاء حجز لا يخص المستخدم
Priority: P0
Severity: Critical
Expected: 403.

4. TC-BOOKINGS-CANCEL-004: التحقق من انعكاس الإلغاء على قائمة الحجوزات أو قاعدة البيانات
Priority: P1
Severity: Medium
Expected: تغير الحالة وعدم استمرار الحجز كموعد فعال إذا كان السلوك يقتضي ذلك.

## 10.8 Appointments API

### GET /api/appointments

1. TC-APPTS-LIST-001: جلب جميع حجوزات المستخدم الحالية
Priority: P0
Severity: High
Expected: 200 مع pagination وبيانات مناسبة.

2. TC-APPTS-LIST-002: الفلترة حسب `status`
Priority: P0
Severity: High
Expected: النتائج المطابقة فقط.

3. TC-APPTS-LIST-003: الفلترة حسب `payment_status`
Priority: P1
Severity: Medium
Expected: النتائج المطابقة فقط.

4. TC-APPTS-LIST-004: الفلترة حسب تاريخ من وإلى
Priority: P1
Severity: Medium
Expected: النتائج ضمن النطاق فقط.

5. TC-APPTS-LIST-005: الفلترة حسب النوع `upcoming/past`
Priority: P1
Severity: Medium
Expected: النتائج متوافقة مع الزمن والحالة.

6. TC-APPTS-LIST-006: الترتيب حسب التاريخ أو الإنشاء أو المبلغ
Priority: P1
Severity: Medium
Expected: ترتيب صحيح.

### GET /api/appointments/statistics

1. TC-APPTS-STATS-001: جلب الإحصائيات بنجاح
Priority: P0
Severity: High
Expected: 200 مع `total`, `pending`, `completed`, `cancelled`, `total_spent`, `upcoming_count` أو ما يكافئها.

2. TC-APPTS-STATS-002: التحقق من منطقية القيم مقارنة ببيانات القائمة
Priority: P1
Severity: Medium
Expected: الأرقام لا تتعارض بوضوح مع نتائج `GET /api/appointments`.

### GET /api/appointments/upcoming

1. TC-APPTS-UPCOMING-001: جلب الحجوزات القادمة بنجاح
Priority: P0
Severity: High
Expected: 200 ونتائج مستقبلية فقط.

2. TC-APPTS-UPCOMING-002: التحقق من فلتر `days`
Priority: P1
Severity: Medium
Expected: النتائج ضمن عدد الأيام المحدد.

### GET /api/appointments/past

1. TC-APPTS-PAST-001: جلب الحجوزات السابقة بنجاح
Priority: P0
Severity: High
Expected: 200 ونتائج ماضية فقط.

2. TC-APPTS-PAST-002: التحقق من `limit`
Priority: P1
Severity: Medium
Expected: عدد النتائج لا يتجاوز limit.

### GET /api/appointments/search

1. TC-APPTS-SEARCH-001: البحث بنجاح باستخدام رقم الحجز أو اسم المزود
Priority: P1
Severity: Medium
Expected: 200 ونتائج مطابقة.

2. TC-APPTS-SEARCH-002: رفض query أقصر من الحد الأدنى
Priority: P1
Severity: Medium
Expected: 422.

### GET /api/appointments/{id}

1. TC-APPTS-SHOW-001: جلب تفاصيل appointment يخص المستخدم الحالي
Priority: P0
Severity: High
Expected: 200 مع بيانات الموعد والمزود والخدمات والحالات.

2. TC-APPTS-SHOW-002: منع الوصول إلى appointment لا يخص المستخدم
Priority: P0
Severity: Critical
Expected: 403.

3. TC-APPTS-SHOW-003: طلب appointment غير موجود
Priority: P1
Severity: Medium
Expected: 404.

4. TC-APPTS-SHOW-004: التحقق من عرض قيم status وpayment_status بشكل صحيح
Priority: P1
Severity: Medium
Expected: القيمة الرقمية والنصية متناسقتان مع البيانات المزروعة.

### POST /api/appointments/{id}/cancel

1. TC-APPTS-CANCEL-001: إلغاء appointment Pending مستقبلي بنجاح
Priority: P0
Severity: Critical
Expected: 200 وتحديث الحالة إلى `USER_CANCELLED`.

2. TC-APPTS-CANCEL-002: رفض إلغاء appointment مكتمل أو ملغى مسبقاً
Priority: P0
Severity: High
Expected: 422.

3. TC-APPTS-CANCEL-003: رفض إلغاء appointment لا يخص المستخدم
Priority: P0
Severity: Critical
Expected: 403.

## 10.9 Appointment Reminder API

### POST /api/appointments/reminders

1. TC-REMINDER-001: إنشاء تذكير بنجاح لموعد مستقبلي يخص المستخدم
Priority: P1
Severity: High
Preconditions: Appointment مستقبلية ومملوكة للمستخدم.
Expected: 201 مع `success=true`, وبيانات `reminder_id`, `appointment_id`, `remind_at`, `status`.

2. TC-REMINDER-002: رفض تذكير لموعد لا يخص المستخدم
Priority: P0
Severity: Critical
Expected: 404 أو رفض مناسب بسبب ownership check.

3. TC-REMINDER-003: رفض تذكير لموعد ملغى
Priority: P1
Severity: High
Expected: 422 business error.

4. TC-REMINDER-004: رفض تذكير لموعد ماضٍ
Priority: P1
Severity: High
Expected: 422 أو validation مناسب.

5. TC-REMINDER-005: رفض قيمة `remind_at` غير منطقية
Priority: P1
Severity: Medium
Expected: 422.

## 10.10 Devices API

### POST /api/register-device

1. TC-DEVICE-REG-001: تسجيل جهاز جديد بنجاح
Priority: P1
Severity: High
Expected: 201 مع `message` و`data` للجهاز.

2. TC-DEVICE-REG-002: إعادة تسجيل نفس `device_id` للمستخدم نفسه وتحديث البيانات
Priority: P1
Severity: High
Expected: لا يتم إنشاء سجل مكرر، ويتم تحديث السجل الحالي وفق `updateOrCreate`.

3. TC-DEVICE-REG-003: رفض غياب `device_id`
Priority: P1
Severity: Medium
Expected: 422.

4. TC-DEVICE-REG-004: رفض `platform` غير مدعوم
Priority: P1
Severity: Medium
Expected: 422.

5. TC-DEVICE-REG-005: رفض بدون توكن
Priority: P0
Severity: Critical
Expected: 401.

### POST /api/deregister-device

1. TC-DEVICE-DEREG-001: إلغاء تسجيل جهاز موجود بنجاح
Priority: P1
Severity: High
Expected: 200 ورسالة `Device unregistered successfully`.

2. TC-DEVICE-DEREG-002: محاولة إلغاء تسجيل جهاز غير موجود
Priority: P1
Severity: Medium
Expected: 404 ورسالة `Device not found`.

3. TC-DEVICE-DEREG-003: رفض غياب `device_id`
Priority: P1
Severity: Medium
Expected: 422.

## 10.11 Print API

### POST /api/invoice/{invoice}/print

1. TC-PRINT-001: طباعة فاتورة واحدة بنجاح
Priority: P1
Severity: High
Preconditions: `invoice_id` صالح ووجود printer/template افتراضيين من البيانات المزروعة.
Expected: 200 مع `success=true`, وبيانات `print_log_id`, `print_number`, `copy_label`, `print_url`.

2. TC-PRINT-002: الطباعة مع `copies` صالحة
Priority: P2
Severity: Low
Expected: نجاح الطلب مع احترام عدد النسخ ضمن الحد المسموح.

3. TC-PRINT-003: رفض `copies` أقل من 1 أو أكبر من 10
Priority: P1
Severity: Medium
Expected: 422.

4. TC-PRINT-004: رفض `invoice_id` غير موجود
Priority: P1
Severity: Medium
Expected: 404.

### POST /api/invoices/print-batch

1. TC-PRINT-BATCH-001: طباعة مجموعة فواتير بنجاح
Priority: P1
Severity: High
Expected: 200 مع `success=true` وملخص عدد الناجح والإجمالي.

2. TC-PRINT-BATCH-002: رفض مصفوفة `invoice_ids` فارغة
Priority: P1
Severity: Medium
Expected: 422.

3. TC-PRINT-BATCH-003: رفض وجود ID غير صالح داخل المصفوفة
Priority: P1
Severity: Medium
Expected: 422.

### GET /api/invoice/{invoice}/print-url

1. TC-PRINT-URL-001: جلب رابط الطباعة بنجاح
Priority: P1
Severity: Medium
Expected: 200 مع `success=true` و`url` صالح.

2. TC-PRINT-URL-002: رفض `invoice_id` غير موجود
Priority: P1
Severity: Medium
Expected: 404.

### POST /api/printer/{printer}/test

1. TC-PRINTER-TEST-001: اختبار طابعة موجودة بنجاح على مستوى API response
Priority: P2
Severity: Low
Expected: 200 مع `success`, `message`, `data`.

2. TC-PRINTER-TEST-002: طلب طابعة غير موجودة
Priority: P1
Severity: Medium
Expected: 404.

### GET /api/print/statistics

1. TC-PRINT-STATS-001: جلب إحصائيات الطباعة بنجاح
Priority: P2
Severity: Low
Expected: 200 مع `success=true` و`data`.

### GET /api/print/logs

1. TC-PRINT-LOGS-001: جلب سجل الطباعة بنجاح
Priority: P2
Severity: Low
Expected: 200 مع `success=true` و`data`.

2. TC-PRINT-LOGS-002: التحقق من `limit` إن استُخدم
Priority: P2
Severity: Low
Expected: عدد السجلات المرتجعة لا يتجاوز القيمة المطلوبة.

## 11. سيناريوهات End-to-End المقترحة

1. E2E-001: Register -> Verify Email OTP -> Login -> Get Profile -> Logout
Priority: P0
Expected: اكتمال دورة إنشاء الحساب والمصادقة الأساسية بدون أخطاء.

2. E2E-002: Login Customer -> Services -> Providers -> Availability -> Create Booking -> Show Booking -> Show Appointment -> Cancel Booking
Priority: P0
Expected: اكتمال دورة الحجز الكاملة وإمكانية استعراض النتائج ثم الإلغاء.

3. E2E-003: Login -> Forgot Password -> Reset Password -> Login with new password
Priority: P0
Expected: اكتمال دورة استرجاع الوصول للحساب بنجاح.

4. E2E-004: Login -> Register Device -> Update same Device -> Deregister Device
Priority: P1
Expected: التحقق من سلوك `updateOrCreate` وتعطيل الجهاز بنجاح.

5. E2E-005: Login -> Get Upcoming Appointments -> Create Reminder for future appointment
Priority: P1
Expected: إنشاء تذكير صالح لموعد مستقبلي يخص المستخدم.

6. E2E-006: Login Admin -> Print invoice -> Get print URL -> Batch print -> View print statistics/logs
Priority: P1
Expected: نجاح دورة الطباعة الوظيفية على مستوى API responses.

## 12. Checklist تنفيذية سريعة

### P0

- [ ] login ناجح
- [ ] login فاشل بكلمة مرور خاطئة
- [ ] register ناجح
- [ ] refresh token ناجح
- [ ] logout وإبطال التوكن
- [ ] forgot password وإرجاع OTP
- [ ] reset password ناجح
- [ ] profile show/update/change-password
- [ ] get user endpoint
- [ ] availability provider صالح
- [ ] availability provider مع إجازة أو يوم عدم دوام
- [ ] availability calendar صالح
- [ ] create booking خدمة واحدة
- [ ] create booking متعدد الخدمات
- [ ] رفض duplicate booking
- [ ] رفض slot محجوز
- [ ] concurrency على نفس slot
- [ ] bookings list/show/cancel
- [ ] appointments list/show/cancel
- [ ] ownership protection في bookings وappointments
- [ ] statistics/upcoming/past/search

### P1

- [ ] verify-email-otp
- [ ] resend-verification-otp
- [ ] request-otp / verify-otp
- [ ] google mobile auth
- [ ] providers list/show مع sorting/search
- [ ] services list/show مع filters/search
- [ ] reminder creation
- [ ] register device / update existing device / deregister device
- [ ] print single invoice
- [ ] print batch
- [ ] print url

### P2

- [ ] printer test
- [ ] print statistics
- [ ] print logs
- [ ] featured filter في services
- [ ] copy count validation في الطباعة

## 13. Risks / Known Issues

1. يوجد تعارض مساري محتمل في `POST /api/auth/reset-password` لأنه معرّف مرة داخل مجموعة Auth ومرة أخرى خارجها، ما قد يسبب التباساً عند التنفيذ أو التوثيق.
2. بيانات appointments seeded جزئياً بشكل عشوائي، لذلك لا يجب ربط الخطة بأرقام IDs ثابتة.
3. قيمة `book_buffer` الحالية تساوي 0، وبالتالي لن تظهر فائدة عملية لاختبارات minimum advance booking في البيئة الحالية إلا إذا تغيرت الإعدادات لاحقاً.
4. النظام يعمل بفرع واحد فقط حالياً، لذلك لا توجد قيمة فعلية لاختبارات branch-based behavior الآن.
5. بعض سلوكيات الأخطاء قد تختلف بين 404 و422 و500 بحسب التنفيذ الفعلي لبعض الحالات غير المتناسقة بالكامل مع التوثيق، ويجب تثبيت النتيجة الواقعية أثناء أول جولة اختبار.
6. طباعة الفواتير قابلة للاختبار على مستوى API response، لكن غياب جهاز طباعة فعلي قد يمنع التحقق النهائي من التنفيذ الفيزيائي للطباعة.
7. يوجد ملاحظة معروفة في التوثيق أن `GET /api/bookings/{id}` قد يعيد 500 بدلاً من 404 في بعض حالات عدم الوجود، ويجب التعامل معها كحالة known issue إذا تأكدت أثناء التنفيذ.

## 14. ملاحظات ختامية

- يفضل تنفيذ هذه الخطة على مرحلتين: مرحلة P0 أولاً، ثم P1 وP2.
- يفضل حفظ نتائج كل حالة باختصار: Passed, Failed, Blocked، مع رابط request/response أو لقطات من Postman عند الحاجة.
- عند اكتشاف أي اختلاف بين السلوك الفعلي والتوقعات في هذه الوثيقة، يجب تحديث الخطة أو فتح Bug واضح بدلاً من تعديل التوقعات بشكل غير موثق.
