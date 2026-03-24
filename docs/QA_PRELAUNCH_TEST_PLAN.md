# خطة اختبار شاملة قبل الإطلاق

## 1. الهدف من الخطة

هذه الخطة ليست للتأكد أن النظام يعمل في المسارات الطبيعية فقط، بل لاكتشاف كيف يمكن أن يفشل النظام قبل الإطلاق الفعلي.

الهدف الرئيسي:

- كسر النظام من خلال سيناريوهات واقعية وعدائية
- كشف التناقضات بين الواجهة وواجهة البرمجة والمنطق الخلفي
- اكتشاف أخطاء الحجز والتعارضات الزمنية
- اكتشاف الأخطاء المالية المتعلقة بالضرائب والتقريب والفواتير
- التحقق من سلامة البيانات بين الجداول المرتبطة
- الوصول إلى قرار واضح: جاهز للإطلاق أو غير جاهز

هذه الخطة مبنية على:

- قراءة Agent.md
- مراجعة منطق الحجز والتوفر والفواتير والدفع في الكود
- تحليل نقاط الهشاشة وليس افتراض صحة النظام

---

## 2. ملخص المخاطر الحرجة المكتشفة من التحليل

| المنطقة | الخطر | التأثير |
|---|---|---|
| Booking مقابل Availability | منطق إخفاء المواعيد في التوفر لا يطابق منطق منع الحجز | احتمال ظهور موعد متاح ثم رفضه عند الحجز أو العكس |
| created_status | يستخدم بطريقة حرجة في منع التعارضات، لكن ليس بنفس الطريقة في كل المسارات | خطر حجز مزدوج أو حجب مواعيد بدون داعٍ |
| Guest Booking | موثّق أنه مدعوم، لكن المسارات الحالية تشير إلى أنه قد لا يكون متاحاً فعلياً | Feature أساسية قد تكون غير قابلة للاستخدام قبل الإطلاق |
| الضرائب والفواتير | بعض المسارات تستخدم bcmath وبعضها يستخدم float | احتمال اختلاف الأرقام بين الحجز والفاتورة والطباعة |
| Finalize Invoice | قد تصبح الفاتورة مدفوعة بدون Payment record كامل | خلل مالي ومحاسبي |
| Buffer Logic | التوفر لا يطبق نفس buffer الخاص بالحجز | المستخدم قد يرى slot صالحاً بينما النظام يرفضه |
| Custom Duration | يبدو أنه غير مطبق فعلياً في الحسابات الأساسية | مدة الخدمة الظاهرة قد لا تطابق المدة الفعلية |

---

## 3. استراتيجية الاختبار

### فلسفة الاختبار

- نبدأ من أعلى المخاطر وليس من الأسهل.
- كل سيناريو يجب أن يراجع على 4 مستويات:
  - الواجهة
  - API
  - قاعدة البيانات
  - Logs أو Exceptions
- أي اختلاف بين هذه المستويات يعتبر Bug حتى لو بدا النظام شغالاً.
- لا يتم الاعتماد على Success Message فقط.

### أنواع الاختبار المستخدمة

| النوع | الغرض |
|---|---|
| Manual UI Testing | اختبار سلوك المستخدم الفعلي على الواجهة |
| Manual API Testing | اختبار الـ validation و business rules بدقة |
| Database Verification | التأكد من عدم وجود تلف أو تناقض في البيانات |
| Admin Workflow Testing | اختبار العمليات التي ينفذها فريق الصالون أو الإدارة |
| Exploratory Testing | كسر السيناريوهات غير المتوقعة |
| E2E Testing | تثبيت السيناريوهات الحرجة القابلة للتكرار بعد نجاح الاختبار اليدوي |

### أولوية التنفيذ

| الأولوية | ما الذي يجب اختباره أولاً |
|---|---|
| P0 | Availability, Booking, created_status, Invoices, Taxes, Payment finalization |
| P1 | Authentication, Admin panel operations, Cancellation, Printing |
| P2 | Notifications, localization, formatting |

---

## 4. بيئة التنفيذ المطلوبة

### البيئة

- Local environment
- قاعدة بيانات تحتوي على بيانات اختبار واضحة وثابتة
- Admin user جاهز
- Customer user verified جاهز
- Customer user غير verified جاهز
- Provider واحد على الأقل لديه schedule واضح وخدمات مربوطة

### الأدوات المطلوبة

| الأداة | الاستخدام |
|---|---|
| Postman أو Bruno | تنفيذ طلبات API |
| المتصفح | اختبار الواجهة الأمامية ولوحة الإدارة |
| Database client أو pgAdmin | مراجعة الجداول مباشرة |
| Laravel logs | مراجعة الأخطاء والاستثناءات |
| Screenshots أو Screen Recording | توثيق النتائج والمشاكل |

### بيانات الاختبار المقترحة

استخدم بيانات ثابتة طوال الجلسة لتجنب الفوضى:

- Provider A
  - يعمل من 09:00 إلى 17:00
  - لا يوجد time off في البداية
- Service 1
  - السعر الإجمالي: 19.99
  - المدة: 30 دقيقة
- Service 2
  - السعر الإجمالي: 29.99
  - المدة: 45 دقيقة
- Service 3
  - السعر الإجمالي: 9.99
  - المدة: 20 دقيقة
- tax_rate = 19
- book_buffer = 60

### جداول يجب مراجعتها أثناء الاختبار

- appointments
- appointment_services
- invoices
- invoice_items
- payments
- provider_scheduled_works
- provider_time_offs

---

## 5. طريقة توثيق كل اختبار

لكل Test Case يجب توثيق التالي:

- رقم الاختبار
- اسم الاختبار
- الهدف
- الشروط المسبقة
- البيانات المستخدمة
- خطوات التنفيذ
- كيف تنفذ الاختبار عملياً
- النتيجة المتوقعة
- ما الذي يجب فحصه في قاعدة البيانات
- ماذا يعني الفشل
- درجة الخطورة

قالب التوثيق أثناء التنفيذ:

| الحقل | القيمة |
|---|---|
| Test ID |  |
| Tester |  |
| Date |  |
| Build/Branch |  |
| Result | Pass / Fail / Blocked |
| Evidence | Screenshot / Response / SQL |
| Notes |  |

---

## 6. خريطة النظام المطلوب اختبارها

| Module | مستوى الخطورة | مستوى العمق المطلوب |
|---|---|---|
| Authentication | عالي | عميق |
| Booking Flow | حرج جداً | عميق جداً |
| Availability | حرج جداً | عميق جداً |
| Guest Booking | حرج جداً | عميق جداً |
| Payments | حرج جداً | عميق جداً |
| Invoices & Tax | حرج جداً | عميق جداً |
| Admin Panel | عالي | عميق |
| Cancellation | عالي | عميق |
| Printing | عالي | عميق |
| Notifications & Reminders | متوسط | متوسط |

---

## 7. حالات الاختبار التفصيلية

## A. اختبارات Authentication

### AUTH-01 تسجيل مستخدم جديد ثم تفعيل الحساب ثم تسجيل الدخول

**الهدف**

- التأكد أن دورة التسجيل الأساسية تعمل بالكامل.

**الشروط المسبقة**

- بريد جديد غير مستخدم
- OTP system يعمل

**البيانات المستخدمة**

- first_name: Test
- last_name: Customer
- email: newcustomer@example.com
- phone: +491111111111
- password: Test@12345

**خطوات التنفيذ**

1. أرسل طلب POST إلى مسار register.
2. تأكد أن المستخدم تم إنشاؤه.
3. اطلب OTP أو التحقق عبر المسار المناسب.
4. أدخل OTP الصحيح.
5. نفذ login.
6. استخدم token في طلب profile.

**كيف أقوم به عملياً**

- في Postman:
  - أنشئ request للتسجيل
  - أنشئ request للتحقق من OTP
  - أنشئ request لتسجيل الدخول
  - أضف Bearer token إلى طلب profile
- في قاعدة البيانات:
  - راجع user row
  - تأكد من حالة التحقق email_verified أو ما يعادلها

**النتيجة المتوقعة**

- يتم إنشاء المستخدم مرة واحدة فقط
- لا يوجد duplicate user
- يتم قبول OTP الصحيح فقط
- login يعيد token صالح
- profile endpoint يعمل

**فحص قاعدة البيانات**

- users: row واحدة فقط
- otp table أو ما يعادلها: token مستخدم أو منتهي كما هو متوقع

**ماذا يعني الفشل**

- خلل أساسي في onboarding

**الخطورة**

- عالية

### AUTH-02 منع الحجز للمستخدم غير المفعّل

**الهدف**

- التأكد أن verified middleware يمنع الحجز عند الحاجة.

**الشروط المسبقة**

- مستخدم موجود لكنه غير verified

**خطوات التنفيذ**

1. نفذ login بالمستخدم غير المفعّل.
2. اطلب availability.
3. حاول إنشاء booking باستخدام token نفسه.

**كيف أقوم به عملياً**

- في Postman استخدم نفس الـ token على مسار bookings.
- في الواجهة حاول تنفيذ نفس السيناريو إن كانت الواجهة تسمح به.

**النتيجة المتوقعة**

- availability قد تعمل إذا كانت عامة
- booking يجب أن يُرفض بشكل واضح
- رسالة الخطأ يجب أن تكون مفهومة وليست 500

**فحص قاعدة البيانات**

- لا يجب إنشاء appointment أو invoice

**ماذا يعني الفشل**

- النظام يسمح بحجز مستخدم غير مستوفٍ لشروط الدخول

**الخطورة**

- عالية

### AUTH-03 إعادة استخدام OTP أو OTP منتهي

**الهدف**

- التأكد من أن OTP لا يُستخدم مرتين ولا يستمر بعد انتهاء الصلاحية.

**خطوات التنفيذ**

1. اطلب OTP.
2. استخدمه بنجاح مرة واحدة.
3. أعد استخدام نفس OTP.
4. اطلب OTP جديداً.
5. انتظر إلى ما بعد وقت الانتهاء إن أمكن.
6. حاول استخدام OTP المنتهي.

**النتيجة المتوقعة**

- OTP الأول يقبل مرة واحدة فقط
- OTP المنتهي يُرفض
- لا يوجد 500 error

**الخطورة**

- متوسطة إلى عالية

---

## B. اختبارات Availability

### AV-01 جلب التوفر ليوم عمل طبيعي

**الهدف**

- التأكد أن التوفر الأساسي يعمل بدون حجوزات أو إجازات.

**الشروط المسبقة**

- provider لديه schedule من 09:00 إلى 17:00
- لا توجد appointments على اليوم المختبر
- لا توجد time off

**البيانات المستخدمة**

- service_id = Service 1
- provider_id = Provider A
- date = يوم غد

**خطوات التنفيذ**

1. استدعِ API الخاص بالتوفر لمقدم خدمة محدد.
2. راجع قائمة المواعيد.
3. تأكد أن أول موعد يبدأ ضمن ساعات العمل.
4. تأكد أن آخر موعد لا يتجاوز نهاية ساعات العمل.

**كيف أقوم به عملياً**

- من Postman نفذ GET availability/provider
- من الواجهة افتح شاشة الحجز لنفس الخدمة ونفس اليوم
- قارن بين API والواجهة

**النتيجة المتوقعة**

- كل slot يجب أن يكون ضمن 09:00 إلى 17:00
- عدد الـ slots منطقي نسبة إلى مدة الخدمة

**فحص قاعدة البيانات**

- لا حاجة لتعديل DB هنا، فقط راجع provider_scheduled_works للتأكد من صحة setup

**ماذا يعني الفشل**

- منطق توليد المواعيد غير موثوق حتى قبل وجود تضاربات

**الخطورة**

- حرجة

### AV-02 حجب slot بسبب appointment مؤكد

**الهدف**

- التأكد أن الموعد المحجوز فعلياً يختفي من التوفر.

**الشروط المسبقة**

- يوجد appointment قائم لنفس provider في وقت 11:00 إلى 11:30
- created_status = 1
- status = pending

**خطوات التنفيذ**

1. أنشئ appointment مؤكد في قاعدة البيانات أو عبر API.
2. اطلب availability لنفس اليوم.
3. افحص هل slot 11:00 أو أي slot متداخل اختفى.
4. حاول الحجز يدوياً على نفس التوقيت.

**كيف أقوم به عملياً**

- الأفضل إنشاء الحجز عبر booking API حتى يكون setup واقعياً
- بعدها أعد طلب availability
- ثم أرسل POST booking لنفس slot من مستخدم آخر

**النتيجة المتوقعة**

- الـ slot المختبر لا يظهر في availability
- booking API يرفض نفس التوقيت

**فحص قاعدة البيانات**

- appointments لا يجب أن تحتوي على حجزيْن متداخلين confirmed لنفس provider

**ماذا يعني الفشل**

- احتمال double booking

**الخطورة**

- حرجة جداً

### AV-03 مقارنة التوفر مع الحجز عند online booking

**الهدف**

- كشف التناقض المحتمل بين created_status وAvailability logic.

**الشروط المسبقة**

- slot متاح حالياً

**خطوات التنفيذ**

1. اطلب availability واحفظ slot معيناً.
2. أنشئ booking بنفس slot مع payment_method = online.
3. افحص appointment الناتج وقيمة created_status.
4. اطلب availability مرة أخرى فوراً.
5. حاول إنشاء booking آخر من مستخدم مختلف على نفس slot.

**كيف أقوم به عملياً**

- نفذ 3 requests متتالية في Postman:
  - GET availability
  - POST booking online
  - GET availability مرة ثانية
  - POST booking جديد لنفس الموعد

**النتيجة المتوقعة**

- يجب أن يكون السلوك واحداً ومتسقاً:
  - إما online booking يحجب الموعد
  - أو لا يحجبه
- لا يجوز أن يظهر slot في availability ثم يرفضه booking أو العكس إلا إذا كان ذلك مقصوداً ومعلنًا بوضوح

**فحص قاعدة البيانات**

- appointments.created_status
- appointments.status
- invoices.status

**ماذا يعني الفشل**

- تناقض مباشر في النظام يؤدي إلى تجربة مستخدم مضللة

**الخطورة**

- حرجة جداً

### AV-04 تأثير book_buffer على التوفر

**الهدف**

- التحقق أن الموعد الظاهر في التوفر يمكن حجزه فعلياً.

**الشروط المسبقة**

- book_buffer = 60
- اليوم الحالي

**خطوات التنفيذ**

1. اختر وقتاً بعد 20 أو 30 دقيقة من الوقت الحالي.
2. اطلب availability لنفس اليوم.
3. إذا ظهر slot ضمن أقل من 60 دقيقة، حاول حجزه.

**كيف أقوم به عملياً**

- نفذ الاختبار في وقت عمل حقيقي وليس على يوم مستقبلي
- سجّل الساعة الحالية بدقة وقت التنفيذ

**النتيجة المتوقعة**

- لا يجب عرض slot يخالف buffer
- وإذا ظهر فلا يجب أن يُقبل الحجز عليه بدون تفسير واضح

**ماذا يعني الفشل**

- المستخدم يرى موعداً قابلاً للاختيار لكنه غير قابل للتنفيذ

**الخطورة**

- عالية جداً

### AV-05 تأثير full-day time off

**الهدف**

- التأكد أن الإجازة اليومية تمنع أي slot.

**الخطوات**

1. أنشئ full-day time off على يوم معين.
2. اطلب availability لنفس اليوم.
3. حاول الحجز على نفس اليوم.

**النتيجة المتوقعة**

- availability يرجع فارغاً
- الحجز يُرفض

**الخطورة**

- عالية

### AV-06 تأثير hourly time off

**الهدف**

- التأكد أن جزءاً من اليوم فقط يتم حجبه.

**الخطوات**

1. أضف hourly time off من 13:00 إلى 14:00.
2. اطلب availability.
3. افحص slots بين 13:00 و14:00 وما حولها.
4. جرّب slot يبدأ 12:45 لمدة 30 دقيقة.
5. جرّب slot يبدأ 14:00 مباشرة.

**النتيجة المتوقعة**

- المواعيد المتداخلة تُحجب
- الموعد الذي يبدأ بعد نهاية الإجازة مباشرة يمكن السماح له إذا لا يوجد تداخل

**الخطورة**

- عالية

---

## C. اختبارات Booking Flow

### BOOK-01 حجز خدمة واحدة نقداً

**الهدف**

- التحقق من المسار الأساسي الأكثر أهمية في النظام.

**الشروط المسبقة**

- المستخدم verified
- slot متاح

**البيانات المستخدمة**

- service_id = Service 1
- provider_id = Provider A
- start_time = 10:00
- payment_method = cash

**خطوات التنفيذ**

1. اطلب availability وحدد slot متاح.
2. أرسل POST booking.
3. راجع response.
4. افتح booking details إذا أمكن.
5. افتح appointment في admin panel.

**كيف أقوم به عملياً**

- في Postman أنشئ request body واضح للحجز
- في الواجهة كرر نفس الحجز إن كانت الواجهة جاهزة
- في DB راجع الجداول المرتبطة

**النتيجة المتوقعة**

- إنشاء appointment
- إنشاء appointment_services row
- إنشاء draft invoice
- payment_status يتوافق مع المسار النقدي المطبق فعلياً
- created_status مضبوط كما هو متوقع

**فحص قاعدة البيانات**

- appointments
- appointment_services
- invoices

**ماذا يعني الفشل**

- مسار الحجز الأساسي غير مستقر

**الخطورة**

- حرجة جداً

### BOOK-02 حجز متعدد الخدمات بشكل متسلسل

**الهدف**

- اختبار orchestration الكامل داخل BookingService.

**البيانات المستخدمة**

- Service 1 عند 10:00
- Service 2 عند 10:30
- Service 3 عند 11:15

**خطوات التنفيذ**

1. جهز payload بثلاث خدمات.
2. أرسل request واحد للحجز.
3. راجع start_time وend_time وduration_minutes.
4. راجع sequence_order في appointment_services.
5. راجع invoice items إن تم إنشاؤها في هذه المرحلة.

**كيف أقوم به عملياً**

- في Postman أرسل services array مرتبة
- ثم أعد الاختبار مع ترتيب عشوائي للـ services داخل payload

**النتيجة المتوقعة**

- النظام يعيد ترتيب الخدمات زمنياً بدون كسر البيانات
- end_time النهائي صحيح
- duration_minutes = مجموع مدد الخدمات
- لا يوجد overlap داخلي

**فحص قاعدة البيانات**

- appointment.start_time
- appointment.end_time
- appointment.duration_minutes
- appointment_services.sequence_order

**ماذا يعني الفشل**

- فشل في جوهر المنطق الأساسي للنظام

**الخطورة**

- حرجة جداً

### BOOK-03 منع التوقيت غير المتسلسل

**الهدف**

- التأكد أن الخدمة الثانية لا تبدأ قبل نهاية الأولى.

**خطوات التنفيذ**

1. أرسل payload فيه Service 1 عند 10:00 ومدتها 30 دقيقة.
2. أرسل Service 2 عند 10:20.
3. نفذ الحجز.

**النتيجة المتوقعة**

- request يُرفض برسالة واضحة
- لا يتم إنشاء أي appointment جزئي

**فحص قاعدة البيانات**

- لا توجد rows جديدة في appointments أو invoices

**الخطورة**

- حرجة

### BOOK-04 منع duplicate booking لنفس المستخدم

**الهدف**

- التأكد من منع تكرار الحجز لنفس الوقت والخدمة.

**خطوات التنفيذ**

1. أنشئ booking صالح.
2. أعد نفس الطلب بنفس المستخدم.
3. راجع الاستجابة.

**النتيجة المتوقعة**

- الطلب الثاني يُرفض
- لا يتم إنشاء appointment إضافي

**الخطورة**

- عالية

### BOOK-05 اختبار max_daily_bookings

**الهدف**

- التأكد من احترام الحد الأقصى للحجوزات اليومية.

**خطوات التنفيذ**

1. اعرف قيمة max_daily_bookings الحالية.
2. أنشئ عدد حجوزات يساوي الحد.
3. حاول إنشاء حجز إضافي في نفس اليوم.

**النتيجة المتوقعة**

- الحجز الإضافي يُرفض

**الخطورة**

- متوسطة إلى عالية

### BOOK-06 الحجز في الماضي أو قبل الحد الأدنى من الوقت

**الهدف**

- التأكد من منع الحجز في ماضٍ زمني أو قريب جداً.

**خطوات التنفيذ**

1. أرسل تاريخ يوم سابق.
2. أرسل اليوم الحالي بوقت مضى.
3. أرسل اليوم الحالي بوقت داخل buffer.

**النتيجة المتوقعة**

- كل هذه السيناريوهات يجب أن تُرفض بشكل واضح

**الخطورة**

- عالية

---

## D. اختبارات Guest Booking

### GUEST-01 التحقق هل Guest Booking مدعوم فعلياً أم لا

**الهدف**

- حسم التناقض بين الوثيقة والتنفيذ.

**خطوات التنفيذ**

1. حاول إرسال booking بدون token مع customer_name وcustomer_email وcustomer_phone.
2. راجع status code.
3. إن تم الرفض بسبب auth، وثّق ذلك.
4. إن تم قبوله، افحص الحقول داخل appointment.

**كيف أقوم به عملياً**

- لا تضع Authorization header
- استخدم نفس payload المذكور في Agent.md تقريباً

**النتيجة المتوقعة**

- يجب أن تكون النتيجة حاسمة:
  - إما guest booking مدعوم end-to-end
  - أو غير مدعوم فعلياً ويعتبر gap وظيفي

**فحص قاعدة البيانات**

- customer_id يجب أن يكون null إذا نجح الحجز كضيف
- customer_name وcustomer_email وcustomer_phone يجب أن تُخزن فعلياً

**ماذا يعني الفشل**

- إذا كانت الميزة مطلوبة للإطلاق، فهذا Release Blocker

**الخطورة**

- حرجة جداً

### GUEST-02 محاولة Guest Booking ببيانات ناقصة

**الهدف**

- التأكد من رفض guest booking غير المكتمل.

**خطوات التنفيذ**

1. أرسل guest booking بدون phone.
2. أرسل guest booking بدون email.
3. أرسل guest booking بدون name.

**النتيجة المتوقعة**

- يجب أن تُرفض الطلبات الناقصة
- لا يجب أن يتم إنشاء appointment ناقص البيانات

**الخطورة**

- عالية جداً

### GUEST-03 duplicate booking للضيف باستخدام الهاتف

**الهدف**

- التأكد من أن guest duplicate detection تعمل كما هو مقصود.

**خطوات التنفيذ**

1. إذا كان guest booking متاحاً فعلياً، أنشئ booking برقم هاتف معين.
2. أعد نفس الحجز بنفس الهاتف.
3. كرر بصيغة هاتف مختلفة لنفس الرقم إن أمكن.

**النتيجة المتوقعة**

- الحجز المكرر يُرفض
- إذا اختلاف التنسيق يسمح بتجاوز المنع فهذا bug

**الخطورة**

- عالية

---

## E. اختبارات الدفع والفواتير والضرائب

### FIN-01 التحقق من إنشاء Draft Invoice مع الحجز

**الهدف**

- التأكد أن الحجز ينتج فاتورة مسودة صحيحة.

**خطوات التنفيذ**

1. أنشئ booking صالح.
2. راجع appointment totals.
3. راجع invoice المرتبطة به.
4. قارن subtotal وtax وtotal بينهما.

**كيف أقوم به عملياً**

- نفذ الحجز عبر API
- استعلم من DB عن invoice المرتبطة بـ appointment_id

**النتيجة المتوقعة**

- invoice.status = draft
- invoice_number = null
- الأرقام متطابقة مع appointment

**فحص قاعدة البيانات**

- invoices
- invoice_items إن كانت تُنشأ في هذه المرحلة

**الخطورة**

- حرجة جداً

### FIN-02 التحقق من دقة الضرائب والتقريب لخدمة واحدة

**الهدف**

- التأكد من صحة reverse tax calculation.

**البيانات المستخدمة**

- gross = 19.99
- tax_rate = 19

**خطوات التنفيذ**

1. أنشئ خدمة أو حجزاً بهذه القيمة.
2. راجع subtotal وtax_amount وtotal_amount.
3. تأكد أن subtotal + tax_amount = total_amount بالضبط.
4. كرر مع gross = 9.99 و29.97 و100.01.

**كيف أقوم به عملياً**

- يمكن تنفيذها عبر bookings متعددة أو عبر خدمة مستقلة في admin إن كانت مرنة
- دوّن النتائج في جدول أثناء الجلسة

**النتيجة المتوقعة**

- لا يوجد فرق حتى 0.01 بدون تفسير
- القيم في appointment والفاتورة متسقة

**الخطورة**

- حرجة جداً

### FIN-03 التحقق من دقة الضرائب لحجز متعدد الخدمات

**الهدف**

- كشف أخطاء التراكم rounding accumulation.

**خطوات التنفيذ**

1. أنشئ booking يحتوي 3 خدمات بأسعار فيها كسور عشرية.
2. اجمع gross يدوياً.
3. قارن totals النهائية مع appointment ثم invoice.
4. راجع invoice items ومجموعها.

**النتيجة المتوقعة**

- مجموع invoice items يجب أن يطابق invoice totals
- لا يظهر فرق في الطباعة أو API response

**الخطورة**

- حرجة جداً

### FIN-04 Finalize Draft Invoice بالقيمة الكاملة

**الهدف**

- التأكد أن تحويل draft إلى paid يعمل بشكل سليم.

**الشروط المسبقة**

- يوجد draft invoice مرتبطة بـ appointment pending

**خطوات التنفيذ**

1. افتح الفاتورة من admin أو نفذ المسار المسؤول عن finalization.
2. أدخل نوع الدفع المناسب والمبلغ الكامل.
3. نفذ العملية.
4. راجع رقم الفاتورة والحالة.
5. راجع appointment payment_status.
6. افحص وجود payment record.

**كيف أقوم به عملياً**

- إذا كان التنفيذ من admin panel متاحاً فاختبر من هناك أولاً
- ثم افحص قاعدة البيانات مباشرة

**النتيجة المتوقعة**

- invoice.status = paid
- invoice_number تم توليده
- appointment.payment_status تم تحديثه
- payment ledger موجود إذا كان هذا جزءاً من السلوك المطلوب

**ماذا يعني الفشل**

- خلل مالي مباشر

**الخطورة**

- حرجة جداً

### FIN-05 Finalize Draft Invoice بمبلغ أقل من الإجمالي

**الهدف**

- معرفة هل النظام يدعم partial أو discounted payment بشكل متسق.

**خطوات التنفيذ**

1. جهّز draft invoice.
2. نفذ finalization بمبلغ أقل من total_amount.
3. راجع النتيجة.
4. افحص invoice.status وpayment records وinvoice_data.

**النتيجة المتوقعة**

- يجب أن يكون السلوك واضحاً:
  - إما رفض العملية
  - أو تحويلها إلى partial payment بشكل صحيح
  - أو تخفيض رسمي موثق

**ماذا يعني الفشل**

- النظام المالي غير محدد السلوك

**الخطورة**

- حرجة جداً

### FIN-06 التحقق من عدم قبول finalization مرتين

**الهدف**

- منع double payment أو توليد أكثر من invoice number لنفس المسودة.

**خطوات التنفيذ**

1. finalize draft invoice مرة أولى.
2. حاول إعادة finalization لنفس الفاتورة.
3. إن أمكن نفذ محاولتين بشكل شبه متزامن.

**النتيجة المتوقعة**

- العملية الثانية تُرفض
- لا يتم إنشاء بيانات مكررة

**الخطورة**

- حرجة جداً

### FIN-07 التحقق من InvoicePaymentService للدفعات الجزئية

**الهدف**

- التأكد من أن partial payment يغير الحالة بشكل صحيح.

**خطوات التنفيذ**

1. جهّز invoice payable.
2. نفذ payment أقل من الإجمالي.
3. راجع invoice status.
4. نفذ payment ثانٍ لإكمال المبلغ.
5. راجع status مجدداً.

**النتيجة المتوقعة**

- بعد الدفعة الأولى: partially_paid
- بعد الدفعة الثانية: paid
- مجموع payments = total

**الخطورة**

- عالية جداً

---

## F. اختبارات Cancellation

### CAN-01 إلغاء حجز pending في المستقبل

**الهدف**

- التأكد أن الإلغاء يعمل ويُحدث الحالة بشكل صحيح.

**خطوات التنفيذ**

1. أنشئ booking pending في المستقبل.
2. نفذ cancel عبر API.
3. راجع status وcancelled_at وcancellation_reason.
4. افحص availability بعد الإلغاء.

**النتيجة المتوقعة**

- status يتغير إلى cancelled المناسب
- الحجز الملغي لا يسبب blocking غير مبرر إذا كانت قاعدة العمل تقول ذلك

**الخطورة**

- عالية

### CAN-02 محاولة إلغاء حجز غير pending

**الهدف**

- التأكد أن النظام يمنع الإلغاء غير المسموح.

**خطوات التنفيذ**

1. جهّز appointment completed أو cancelled.
2. حاول إلغاءه.

**النتيجة المتوقعة**

- الطلب يُرفض برسالة واضحة

**الخطورة**

- متوسطة إلى عالية

---

## G. اختبارات Admin Panel

### ADMIN-01 إعداد Provider كامل ثم التحقق من انعكاسه على التوفر

**الهدف**

- التأكد أن إعدادات لوحة الإدارة تنعكس مباشرة على الحجز.

**خطوات التنفيذ**

1. من لوحة الإدارة أضف provider.
2. اربط خدماته.
3. أضف schedule أسبوعي.
4. اطلب availability عبر API.
5. افتح الواجهة وتحقق من نفس اليوم.

**النتيجة المتوقعة**

- ما تم ضبطه في admin يظهر في availability والحجز

**الخطورة**

- عالية

### ADMIN-02 تعديل tax_rate ثم اختبار حجز جديد

**الهدف**

- التأكد أن الإعداد الجديد يطبق على الحجوزات الجديدة فقط دون تخريب القديمة.

**خطوات التنفيذ**

1. أنشئ booking قبل تعديل الضريبة.
2. عدّل tax_rate من admin.
3. أنشئ booking جديداً.
4. قارن الأرقام بين الحجزين.

**النتيجة المتوقعة**

- الحجز القديم لا يتغير retroactively
- الحجز الجديد يستخدم الضريبة الجديدة

**الخطورة**

- عالية جداً

### ADMIN-03 تعديل custom_price وcustom_duration

**الهدف**

- التأكد أن القيم المخصصة للمزوّد مستخدمة فعلاً في التوفر والحجز.

**خطوات التنفيذ**

1. عدّل custom_duration لخدمة provider معين.
2. اطلب availability.
3. أنشئ booking.
4. راجع duration في appointment.
5. راجع السعر المستخدم.

**النتيجة المتوقعة**

- يجب أن ينعكس التعديل في كل الأماكن
- إذا لم ينعكس فهذا bug جوهري

**الخطورة**

- عالية جداً

---

## H. اختبارات Printing

### PRINT-01 طباعة فاتورة مدفوعة لأول مرة

**الهدف**

- التأكد من سلامة أول طباعة.

**خطوات التنفيذ**

1. جهّز invoice paid.
2. نفذ print.
3. راجع المحتوى المطبوع.
4. راجع print_count وfirst_printed_at وlast_printed_at.

**النتيجة المتوقعة**

- الطباعة تنجح
- لا يظهر Copy label لأول مرة
- print metadata يتم تحديثها

**الخطورة**

- عالية

### PRINT-02 إعادة طباعة نفس الفاتورة

**الهدف**

- التأكد أن النسخة الثانية تعامل كنسخة مكررة.

**خطوات التنفيذ**

1. اطبع نفس الفاتورة مرة ثانية.
2. راجع copy label.
3. راجع print_count.

**النتيجة المتوقعة**

- يظهر COPY أو ما يعادله
- print_count يزيد بشكل صحيح

**الخطورة**

- متوسطة إلى عالية

### PRINT-03 طباعة فاتورة لعميل Guest

**الهدف**

- التأكد أن الطباعة لا تنكسر بسبب customer accessors.

**خطوات التنفيذ**

1. إذا أمكن إنشاء guest appointment وفاتورته، نفذ print.
2. راجع customer name/email/phone في الطباعة.

**النتيجة المتوقعة**

- الطباعة تنجح بدون null errors أو recursion أو بيانات ناقصة

**الخطورة**

- عالية

---

## I. اختبارات Race Conditions

### RACE-01 طلبا حجز متزامنان لنفس الموعد

**الهدف**

- كشف إمكانية الحجز المزدوج تحت الضغط.

**الشروط المسبقة**

- يوجد slot واحد متاح بوضوح
- يوجد مستخدمان مختلفان

**خطوات التنفيذ**

1. احصل على slot متاح.
2. جهّز طلبين booking متطابقين لنفس الموعد لكن بمستخدمين مختلفين.
3. أرسل الطلبين في نفس اللحظة قدر الإمكان.
4. كرر الاختبار 10 إلى 20 مرة.

**كيف أقوم به عملياً**

- باستخدام Postman Runner أو أداة parallel requests أو سكربت بسيط
- المهم أن يصل الطلبان متقاربين جداً زمنياً

**النتيجة المتوقعة**

- طلب واحد فقط ينجح
- لا يوجد appointmentان متداخلان لنفس provider/time

**فحص قاعدة البيانات**

- راجع appointments لنفس provider ونفس الفترة الزمنية
- راجع invoices الناتجة

**ماذا يعني الفشل**

- النظام غير آمن تشغيلياً قبل الإطلاق

**الخطورة**

- حرجة جداً

---

## 8. سيناريوهات فشل متوقعة يجب مراقبتها أثناء الجلسة

### فشل منطقي محتمل في الحجز والتوفر

- slot يظهر في availability لكنه يُرفض عند POST booking
- slot لا يظهر للمستخدم لكن يمكن حجزه فعلياً عبر API
- online booking يحجب slot في مكان ولا يحجبه في مكان آخر
- cancellation لا تعيد slot كما هو متوقع

### فشل مالي محتمل

- subtotal + tax لا يساوي total
- appointment total يختلف عن invoice total
- invoice items مجموعها لا يساوي الفاتورة
- invoice تتحول إلى paid بدون payment row
- إعادة finalization تخلق تناقضات محاسبية

### فشل بيانات محتمل

- إنشاء appointment بدون invoice أو invoice بدون items أو payment ناقصة
- duplicate rows في appointment_services
- guest booking يخزن بيانات ناقصة

### فشل تشغيلي محتمل

- print يعمل لكن لا يحدث print_count
- print_count يتغير لكن المحتوى لا يظهر copy label
- admin يعدل settings لكن availability لا تتحدث بسبب cache أو منطق غير متناسق

---

## 9. مصفوفة المخاطر

| الخطر | المستوى | أثره على البزنس | قرار الإطلاق |
|---|---|---|---|
| Double booking | High | فوضى تشغيلية وخسارة ثقة | يمنع الإطلاق |
| تناقض availability وbooking | High | UX مضلل وحجوزات مرفوضة | يمنع الإطلاق إذا تكرر |
| خطأ مالي في الضرائب أو الفاتورة | High | أثر قانوني ومحاسبي | يمنع الإطلاق |
| Guest booking غير متاح إذا كان مطلوباً | High | feature gap كبير | يمنع الإطلاق إذا كانت مطلوبة |
| invoice paid بدون payment ledger | High | أثر مالي وتدقيقي | يمنع الإطلاق |
| custom_duration غير مطبق | Medium | جداول عمل غير صحيحة | قد يؤجل الإطلاق أو يقيد النطاق |
| مشاكل الطباعة الثانوية | Medium | أثر تشغيلي | قد تؤجل إذا الأرقام سليمة |
| مشاكل localization أو تنسيق | Low | أثر شكلي | لا تمنع الإطلاق غالباً |

---

## 10. خطة تنفيذ جلسة الاختبار

### ترتيب الجلسة المقترح

1. تجهيز البيانات الثابتة
2. اختبار Authentication الأساسي
3. اختبار Availability baseline
4. اختبار Booking cash
5. اختبار Booking online
6. اختبار created_status impact
7. اختبار multi-service booking
8. اختبار duplicate وlimits
9. اختبار Guest booking
10. اختبار Draft invoice
11. اختبار Finalize payment
12. اختبار الضرائب والتقريب
13. اختبار cancellation
14. اختبار admin edits
15. اختبار print
16. اختبار race conditions
17. إعادة اختبار P0 بعد الإصلاحات

### توزيع العمل بين Backend وFrontend

**Backend Developer**

- تنفيذ API tests
- مراجعة DB state بعد كل P0 case
- مراجعة logs والاستثناءات
- إعادة تشغيل السيناريوهات الحرجة بعد الإصلاح

**Frontend Developer**

- مقارنة سلوك الواجهة مع API
- اختبار رسائل الخطأ
- اختبار شاشات الحجز والطباعة ولوحة الإدارة
- توثيق أي فرق بين الظاهر للمستخدم وما يحدث فعلاً

---

## 11. خطة الإيقاف أو التراجع قبل الإطلاق

يجب إيقاف الإطلاق فوراً إذا ظهر أحد التالي:

- double booking مؤكد
- تناقض مالي في totals أو tax calculations
- invoice تُدفع بدون payment record موثوق
- Guest booking مطلوب لكنه غير متاح فعلياً
- finalization أو print يتسبب في crash أو data corruption

يمكن تأجيل إصلاح بعض الأمور مؤقتاً فقط إذا كانت:

- مشاكل صياغة رسائل
- مشاكل تنسيق بسيطة لا تؤثر على المنطق
- مشاكل localization غير مؤثرة على العمليات الأساسية

---

## 12. قائمة التحقق قبل Go-Live

### يجب أن تكون جميع النقاط التالية صحيحة

- لا توجد Bugs حرجة مفتوحة في Booking أو Availability أو Invoices أو Payments
- تم اختبار cash booking بنجاح
- تم اختبار online booking بنجاح
- تم اختبار multi-service booking بنجاح
- تم التحقق من سلوك created_status فعلياً وليس نظرياً
- لا يوجد mismatch بين availability وbooking API
- subtotal + tax = total في جميع حالات الاختبار الحرجة
- draft invoice وfinal invoice متسقتان
- payment records سليمة عند الدفع
- invoice numbers لا تتكرر
- الطباعة تعمل وتحدّث print_count بشكل صحيح
- cancellation لا تترك side effects خاطئة
- لا يوجد data corruption في الجداول المرتبطة
- logs خالية من exceptions غير مبررة في P0 flows

---

## 13. التوصية النهائية

هذا النظام ليس مجرد نظام CRUD، بل نظام تشغيل صالون + حجوزات + منطق مالي. لذلك:

- أي خلل بسيط في الحجز قد يتحول إلى مشكلة تشغيلية مباشرة
- أي خلل بسيط في الفواتير قد يتحول إلى مشكلة مالية أو قانونية
- أي تعارض بين availability وbooking سيكتشفه المستخدم بسرعة ويهز الثقة بالنظام

الحكم النهائي يجب أن يعتمد على نجاح اختبارات P0 بالكامل، وليس على أن الواجهة تبدو سليمة.

إذا اجتاز النظام هذه الخطة بدون Bugs حرجة، يمكن اعتباره قريباً من الجاهزية للإطلاق.

#### Scenario

1. Create draft invoice from booking.
2. Finalize with exact amount.
3. Finalize another invoice with discounted amount.
4. Attempt second finalization on same draft concurrently.
5. Print finalized invoice twice.

#### Assertions

- invoice_number unique and sequential
- invoice status consistent
- appointment payment_status consistent
- payment ledger exists when invoice becomes paid
- print metadata accurate

### Deep Flow 6: Tax Rounding Issues

#### Objective

- كشف أي اختلاف بين BookingService, TaxCalculatorService, InvoiceService, InvoiceItem observers.

#### Test Data Set

| Case | Gross Inputs | Tax Rate | Why |
|---|---|---|---|
| TAX-01 | 9.99 | 19 | classic rounding edge |
| TAX-02 | 19.99 + 19.99 + 19.99 | 19 | accumulated rounding |
| TAX-03 | 0.01 | 19 | minimum value edge |
| TAX-04 | 100.00 | 0 | no-tax branch |
| TAX-05 | mixed discounted services | 19 | realistic salon scenario |

#### Assertions

- subtotal + tax_amount = total_amount exactly at persisted level
- appointment totals = draft invoice totals = finalized invoice totals unless explicitly discounted
- printed numbers = DB numbers = API response numbers

### Deep Flow 7: created_status Impact on Availability

#### Objective

- التأكد أن created_status لا يسبب phantom blocking أو phantom availability.

#### Scenario

1. Create cash booking => created_status=1.
2. Confirm slot disappears from availability.
3. Create online booking on another slot => created_status=0.
4. Check if availability removes slot or keeps it.
5. Try booking same slot again.
6. Cancel both and re-check availability.

#### Expected

- behavior must be explicit, stable, and identical across APIs and UI.

---

## 5. Risk Analysis

| Risk | Level | Business Impact | Why |
|---|---|---|---|
| Double booking بسبب inconsistency بين availability و booking validation | High | فقدان ثقة العميل وفوضى تشغيلية | نفس slot قد يظهر متاحاً أو محجوزاً بشكل غير متسق |
| Guest booking غير متاح رغم requirement | High | feature gap مباشر قبل الإطلاق | documented flow لا يطابق routes الحالية |
| Tax/rounding mismatch | High | أخطاء مالية وقانونية | النظام ألماني والفاتورة حساسة قانونياً |
| Invoice marked paid without Payment ledger | High | accounting inconsistency | audit trail ناقص |
| Payment enum mapping failure | High | runtime failure أثناء الدفع | finalization path قابل للكسر |
| Custom duration not applied | Medium | مواعيد فعلية غير صحيحة | booking windows غير دقيقة |
| Buffer mismatch | Medium | UX سيئ ورفض بعد اختيار slot | availability لا يطابق booking validation |
| Print/template guest rendering failure | Medium | تعطل تشغيل الصالون عند الدفع | invoice printing flow أساسي |
| Currency display mismatch AED | Low | compliance/UI confusion | لا يوقف التشغيل لكنه يضر الثقة |

---

## 6. Priority Matrix

### Must Test First - P0

| Priority | Test Areas |
|---|---|
| P0-1 | Availability vs Booking consistency |
| P0-2 | Multi-service booking correctness |
| P0-3 | created_status behavior |
| P0-4 | Draft invoice creation and totals |
| P0-5 | Finalize payment and invoice status consistency |
| P0-6 | Tax rounding and exact totals |
| P0-7 | Guest booking capability validation |
| P0-8 | Concurrent booking race tests |

### Important but Can Follow - P1

| Priority | Test Areas |
|---|---|
| P1-1 | Authentication edge cases |
| P1-2 | Admin panel setup edits and cache effects |
| P1-3 | Printing, copy labels, print logs |
| P1-4 | Cancellation and rebooking behavior |
| P1-5 | Notifications and reminders |

### Can Be Deferred if Time Is Tight - P2

| Priority | Test Areas |
|---|---|
| P2-1 | Localization copy issues |
| P2-2 | Non-critical UI formatting |
| P2-3 | Low-value static page checks |

---

## 7. Execution Plan

### Session Structure

1. Backend Developer prepares deterministic dataset.
2. Frontend Developer opens booking UI and admin UI.
3. QA session starts from API truth, not UI assumptions.
4. Every critical test records:
   - request payload
   - API response
   - DB state
   - UI state
5. Any mismatch between these four layers يعتبر bug حتى لو flow "يبدو شغال".

### Step-by-Step Order

1. Seed baseline data: providers, services, schedules, payment methods, invoice template.
2. Verify auth states: guest, logged-in unverified, verified.
3. Test availability baseline with empty day.
4. Test single-service booking cash.
5. Test multi-service booking cash.
6. Test online booking and created_status effect.
7. Test conflict scenarios with existing appointments and time off.
8. Test cancellation then rebooking.
9. Test draft invoice totals.
10. Test finalization and payment records.
11. Test print flows and copy labels.
12. Run concurrency tests.
13. Run regression sweep on fixed defects.

### Backend and Frontend Collaboration

#### Backend Developer

- monitors logs and DB after every P0 case
- validates enum/state transitions
- confirms transaction integrity and rollback behavior
- reproduces race tests via API tools or scripts

#### Frontend Developer

- validates visible slots against API truth
- checks form validation and error messaging
- verifies admin screens, print flows, and localization display
- captures any UI state that masks backend inconsistency

### Recommended Evidence to Capture Per Critical Test

- request payload
- response body
- screenshot or screen recording
- SQL snapshot of appointments/invoices/payments
- bug severity and reproducibility level

---

## 8. Rollback Plan

### Stop-Release Conditions

- أي حالة Double Booking مؤكدة
- أي mismatch مالي بين appointment/invoice/payment/printed total
- inability to finalize invoice reliably
- Guest Booking requirement غير متاحة إذا كانت business-critical for launch
- runtime error in payment or invoice flow
- data corruption أو orphan records بعد transaction failure

### What Can Be Temporarily Tolerated

- non-critical localization issues
- wording problems in validation messages
- low-impact formatting issues في الطباعة إذا لم تمس الأرقام أو الحالة

### Response to Critical Findings

1. Freeze release candidate.
2. Classify bug as business blocker or operational blocker.
3. Re-test fix على نفس dataset أولاً.
4. Execute targeted regression حول نفس module.
5. Update go-live checklist only بعد pass واضح.

---

## 9. Go-Live Checklist

### Critical Quality Gates

- لا توجد Bugs حرجة مفتوحة في booking, availability, payments, invoices
- لا يوجد mismatch بين UI availability و booking API
- لا توجد Race Condition confirmed تؤدي إلى overlapping appointments
- multi-service booking validated end-to-end
- created_status behavior documented and verified عملياً
- guest booking requirement either verified or explicitly descoped قبل الإطلاق

### Financial Integrity Gates

- subtotal + tax = total في جميع السيناريوهات المختبرة
- draft invoice totals مطابقة لـ appointment totals
- finalized invoice totals مطابقة للمبلغ المعتمد business-wise
- payment ledger موجود لكل invoice مدفوعة
- invoice number uniqueness verified
- print output numbers مطابقة للـ DB

### Data Consistency Gates

- لا orphan rows في appointment_services أو invoice_items أو payments
- cancellations لا تترك blocking side effects غير مقصودة
- admin edits تنعكس بشكل صحيح على availability والحجوزات الجديدة

### Stability Gates

- logs خالية من exceptions في P0/P1 flows
- API responses ثابتة وصالحة structure-wise
- print flow يعمل دون crash
- reminder/device flows لا تخلق side effects مدمرة

### Final Release Decision

- Go فقط إذا مرت جميع P0 flows بدون bugs حرجة أو تناقضات منطقية.
- No-Go إذا بقي أي خلل مالي، أو عدم اتساق في availability/booking، أو عدم وضوح في guest booking requirement.

---

## Suggested E2E Candidates

- Registered user can login, view availability, create cash booking, and see booking details.
- Registered user cannot book slot shown as unavailable.
- Admin can finalize draft invoice and print it.
- Cancelled booking frees the slot according to approved business rule.
- Reprint increments copy labeling correctly.

## Final QA Position

- هذا النظام لا يجب تقييمه على أنه CRUD application فقط.
- هو Booking + Financial + Operational system، وبالتالي أي bug منطقي صغير قد يتحول إلى business incident مباشر.
- الأولوية قبل الإطلاق ليست زيادة coverage شكلياً، بل إزالة التناقضات بين Availability, Booking, Invoice, Payment, and Admin behaviors.
