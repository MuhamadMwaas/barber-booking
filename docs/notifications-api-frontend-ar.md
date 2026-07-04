<div dir="rtl">

# توثيق API: الإشعارات (Notifications) — دليل الـ Frontend

> دليل عملي مخصّص لمبرمج التطبيق. يشرح كيف **تجيب** إشعارات المستخدم، **تعرف عدد غير المقروء**، **تعلّم إشعار كمقروء**، و**تحذف** إشعار. كل الإشعارات مخزّنة بجدول واحد (`notifications`) وبترجع بنفس الشكل من كل الـ endpoints.

---

## 0. نظرة سريعة (TL;DR)

| العملية | Method | المسار | الاستخدام |
|---|---|---|---|
| جلب كل الإشعارات (مع فلتر اختياري) | `GET` | `/api/notifications` | شاشة "كل الإشعارات" — كل/مقروء/غير مقروء |
| جلب **غير المقروءة فقط** | `GET` | `/api/notifications/unread` | تبويب "غير مقروء" أو Inbox مختصر |
| جلب **عدد** غير المقروء فقط | `GET` | `/api/notifications/unread-count` | الـ Badge على أيقونة الجرس |
| تعليم إشعار كـ "مقروء" | `POST` | `/api/notifications/{notificationId}/read` | عند فتح/ضغط المستخدم على إشعار |
| حذف إشعار | `DELETE` | `/api/notifications/{notificationId}` | زر الحذف / Swipe to delete |

- كل الـ endpoints محميّة: تتطلب توكن دخول + حساب موثّق (`verified.customer`).
- `GET /api/notifications` و`GET /api/notifications/unread` بيرجّعوا **نفس شكل البيانات بالضبط** (نفس عنصر الإشعار + نفس الـ pagination) — الفرق الوحيد إنو الثاني بيفلتر تلقائياً على غير المقروء بدون ما تبعت `status`.
- الـ Pagination هون **cursor-based** مش صفحات بالأرقام (اشرحها بالتفصيل تحت — مهم تنتبهلها).

---

## 1. المصادقة (Authentication)

كل الطلبات محميّة بـ middleware: `auth:sanctum` + `verified.customer`. أرسل دائماً:

<div dir="ltr">

```
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

</div>

> لو التوكن منتهي/غير صالح بترجع `401`. لو الحساب لسا مش موثّق (OTP) بترجع `403` (شوف قسم الأخطاء).

---

## 2. شكل عنصر الإشعار (Notification Object)

كل إشعار — بأي endpoint — إله نفس الشكل التالي:

<div dir="ltr">

```json
{
  "id": "9c6e7e3a-1e3b-4a2b-8f0b-6f6b9b1e2b7d",
  "title": "تم تعديل موعد حجزك",
  "message": "تم تأجيل حجزك رقم 1042 إلى الساعة 14:30.",
  "data": {
    "type": "booking_pushed",
    "appointment_id": 1042,
    "appointment_number": "1042",
    "original_start_time": "14:00",
    "new_start_time": "14:30",
    "pushed_minutes": 30
  },
  "read_at": null,
  "created_at": "2026-07-04T10:15:00.000000Z"
}
```

</div>

### شرح الحقول

| الحقل | النوع | الشرح |
|---|---|---|
| `id` | string (UUID) | معرّف الإشعار. **استخدمه** بمسار `mark-as-read` و`DELETE`. |
| `title` | string | عنوان الإشعار، **مترجم جاهز** حسب لغة المستخدم (`locale`) — اعرضه كما هو، لا تترجمه بالتطبيق. |
| `message` | string | نص الإشعار الكامل، مترجم جاهز أيضاً. |
| `data` | object | بيانات إضافية خام (raw) خاصة بنوع الإشعار — **شكلها بيختلف حسب `data.type`**. استخدمها لو بدك تعمل action لما المستخدم يضغط الإشعار (مثلاً تفتح شاشة تفاصيل الحجز برقم `appointment_id`). لو ما بدك تتعامل معها حالياً تجاهلها بأمان. |
| `read_at` | string (ISO datetime) \| `null` | `null` = غير مقروء. أي قيمة تانية = وقت التعليم كمقروء. |
| `created_at` | string (ISO datetime) | وقت إنشاء الإشعار — استخدمه للترتيب/العرض ("منذ 5 دقائق"). |

> **مهم:** `data.type` مو enum ثابت موثّق بالكامل حالياً — كل نوع إشعار بالباك إند بيحدد قيمته وحقوله الخاصة جوا `data` (مثال فوق: `booking_pushed`). **تعامل معها دفاعياً**: لو `data.type` غير معروف بالتطبيق، اعرض `title`/`message` بس بدون action إضافي.

---

## 3. جلب كل الإشعارات — `GET /api/notifications`

### الطلب
<div dir="ltr">

```
GET /api/notifications?per_page=15&status=all&cursor=eyJpZCI6...
Authorization: Bearer <access_token>
Accept: application/json
```

</div>

| Query Param | إلزامي؟ | القيم | الشرح |
|---|---|---|---|
| `per_page` | لا | رقم من 1 إلى 200 (افتراضي `15`) | عدد العناصر بكل صفحة. |
| `status` | لا | `all` \| `read` \| `unread` (افتراضي `all`) | فلترة حسب حالة القراءة. |
| `cursor` | لا | string مشفّر (من `next_cursor`/`prev_cursor`) | لجلب الصفحة التالية/السابقة — شوف قسم الـ Pagination. |

### الرد `200`
<div dir="ltr">

```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": "9c6e7e3a-1e3b-4a2b-8f0b-6f6b9b1e2b7d",
      "title": "تم تعديل موعد حجزك",
      "message": "تم تأجيل حجزك رقم 1042 إلى الساعة 14:30.",
      "data": { "type": "booking_pushed", "appointment_id": 1042 },
      "read_at": null,
      "created_at": "2026-07-04T10:15:00.000000Z"
    }
  ],
  "pagination": {
    "per_page": 15,
    "next_cursor": "eyJpZCI6IjljNmU3ZTNhLi4uIn0",
    "prev_cursor": null,
    "has_more_pages": true
  },
  "unread_count": 4
}
```

</div>

> `unread_count` هون بيرجع **دايماً مع كل استجابة** (بغض النظر عن الفلتر) — عدد المقروء الإجمالي، ممكن تستخدمه لتحديث الـ Badge بنفس الاستدعاء بدون طلب إضافي.

---

## 4. جلب الإشعارات غير المقروءة فقط — `GET /api/notifications/unread`

نفس شكل `GET /api/notifications` تماماً (نفس عنصر الإشعار، نفس `pagination`، نفس `unread_count`) — لكن بيرجّع **فقط الإشعارات اللي `read_at = null`** بدون داعي لتمرير `status=unread` يدوياً.

### الطلب
<div dir="ltr">

```
GET /api/notifications/unread?per_page=15&cursor=eyJpZCI6...
Authorization: Bearer <access_token>
Accept: application/json
```

</div>

| Query Param | إلزامي؟ | القيم | الشرح |
|---|---|---|---|
| `per_page` | لا | رقم من 1 إلى 200 (افتراضي `15`) | عدد العناصر بكل صفحة. |
| `cursor` | لا | string مشفّر | لجلب الصفحة التالية/السابقة. |

### الرد `200`
نفس شكل رد `GET /api/notifications` بالضبط (شوف المثال بالقسم 3) — بس كل عناصر `data` بتكون `read_at: null`.

> **متى تستخدمه بدل `/api/notifications?status=unread`؟** الاثنان بيعملوا نفس الشي تماماً. استخدم هذا الـ endpoint لو بدك مسار مختصر وواضح بالكود (مثلاً تبويب "غير مقروء" مستقل بشاشته)، واستخدم `status=unread` لو الشاشة نفسها فيها تبديل بين all/read/unread بنفس مكان الطلب.

---

## 5. جلب عدد غير المقروء فقط — `GET /api/notifications/unread-count`

خفيف وسريع — استخدمه لتحديث الـ Badge على أيقونة الجرس بدون تحميل قائمة كاملة (مثلاً بعد فتح التطبيق، أو بعد Push جديد، أو بشكل دوري polling).

### الطلب
<div dir="ltr">

```
GET /api/notifications/unread-count
Authorization: Bearer <access_token>
Accept: application/json
```

</div>

### الرد `200`
<div dir="ltr">

```json
{
  "success": true,
  "message": "Unread notifications count retrieved successfully",
  "data": {
    "unread_count": 4
  }
}
```

</div>

---

## 6. تعليم إشعار كـ "مقروء" — `POST /api/notifications/{notificationId}/read`

استدعِه لما المستخدم يفتح/يضغط على إشعار معيّن من القائمة.

### الطلب
<div dir="ltr">

```
POST /api/notifications/9c6e7e3a-1e3b-4a2b-8f0b-6f6b9b1e2b7d/read
Authorization: Bearer <access_token>
Accept: application/json
```

</div>

> `{notificationId}` = نفس `id` (UUID) اللي إجاك بعنصر الإشعار. لا يوجد Body مطلوب.

### الرد `200` (نجاح)
<div dir="ltr">

```json
{
  "success": true,
  "message": "Notification marked as read successfully",
  "data": {
    "id": "9c6e7e3a-1e3b-4a2b-8f0b-6f6b9b1e2b7d",
    "title": "تم تعديل موعد حجزك",
    "message": "تم تأجيل حجزك رقم 1042 إلى الساعة 14:30.",
    "data": { "type": "booking_pushed", "appointment_id": 1042 },
    "read_at": "2026-07-04T10:20:00.000000Z",
    "created_at": "2026-07-04T10:15:00.000000Z"
  }
}
```

</div>

> - لو الإشعار كان مقروء أصلاً، الـ endpoint **idempotent** — بيرجّع `200` بنفس البيانات بدون خطأ (ما بيغيّر `read_at` مرتين).
> - **لاحظ:** هذا الرد ما بيرجّع `unread_count` محدّث — إذا بدك تحدّث الـ Badge بعد التعليم كمقروء، اطرح 1 محلياً (Optimistic) أو نادِ `GET /api/notifications/unread-count` بعدها.

### الرد `404` (الإشعار مش موجود / مش تبع هذا المستخدم)
<div dir="ltr">

```json
{
  "success": false,
  "message": "Notification not found"
}
```

</div>

---

## 7. حذف إشعار — `DELETE /api/notifications/{notificationId}`

حذف نهائي (Hard delete) — ما في "سلة محذوفات" أو استرجاع.

### الطلب
<div dir="ltr">

```
DELETE /api/notifications/9c6e7e3a-1e3b-4a2b-8f0b-6f6b9b1e2b7d
Authorization: Bearer <access_token>
Accept: application/json
```

</div>

### الرد `200` (نجاح)
<div dir="ltr">

```json
{
  "success": true,
  "message": "Notification deleted successfully",
  "unread_count": 3
}
```

</div>

> هون **`unread_count` بيرجع محدّث فوراً** بعد الحذف — استخدمه مباشرة لتحديث الـ Badge بدون طلب إضافي.

### الرد `404` (الإشعار مش موجود / مش تبع هذا المستخدم)
<div dir="ltr">

```json
{
  "success": false,
  "message": "Notification not found"
}
```

</div>

> نفس منطق الأمان بكل الـ endpoints: السيرفر بيدوّر عن الإشعار **ضمن إشعارات المستخدم الحالي فقط** (بالتوكن). محاولة حذف/تعليم إشعار تبع مستخدم تاني بترجع `404` (مو `403`) — بالنسبة للتطبيق عاملها كـ "غير موجود".

---

## 8. الـ Pagination (Cursor-based) — مهم تفهمها

الـ endpoints اللي بترجّع قائمة (`GET /api/notifications` و`GET /api/notifications/unread`) بتستخدم **cursor pagination** مو أرقام صفحات (`page=1,2,3`).

<div dir="ltr">

```json
"pagination": {
  "per_page": 15,
  "next_cursor": "eyJpZCI6IjljNmU3ZTNhLi4uIn0",
  "prev_cursor": null,
  "has_more_pages": true
}
```

</div>

### كيف تستخدمها
- **أول تحميل:** لا ترسل `cursor` إطلاقاً.
- **تحميل المزيد (Infinite Scroll):** خذ `next_cursor` من آخر رد، وابعته كـ `?cursor=<القيمة>` بالطلب التالي.
- **توقف عن الطلب** لما `has_more_pages = false` أو `next_cursor = null`.
- **لا تحفظ رقم صفحة ولا تحسب offset يدوياً** — الـ cursor عبارة عن قيمة مشفّرة (opaque string)، مرّرها كما هي بدون تعديل أو فك تشفير.
- `prev_cursor` موجود لو بدك تدعم "الرجوع للخلف" بالقائمة، غالباً مش لازم للـ Infinite Scroll العادي.

---

## 9. حالات الخطأ العامة

| الكود | المعنى | شو تعمل بالواجهة |
|---|---|---|
| `401` | التوكن غير صالح/منتهي | اعمل refresh أو رجّع المستخدم لتسجيل الدخول. |
| `403` | الحساب لسا مش موثّق (`requires_otp_verification: true`) | وجّه المستخدم لشاشة التحقق من OTP. |
| `404` | الإشعار (`notificationId`) غير موجود أو مش تابع للمستخدم الحالي | تجاهله من القائمة المحلية (اعتبره أُزيل). |
| `422` | فشل التحقق من `query params` (مثلاً `per_page` أكبر من 200 أو `status` قيمة غير مسموحة) | تأكد من القيم المرسلة، شوف `errors`. |

### مثال خطأ `403` (حساب غير موثّق)
<div dir="ltr">

```json
{
  "message": "Your account is not verified. Please verify it using the OTP sent to your registered contact.",
  "registration_method": "phone",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}
```

</div>

### مثال خطأ `422`
<div dir="ltr">

```json
{
  "message": "The per page field must not be greater than 200.",
  "errors": {
    "per_page": ["The per page field must not be greater than 200."]
  }
}
```

</div>

---

## 10. تدفّق عملي مقترح (Pseudo-code)

<div dir="ltr">

```
// عند فتح التطبيق / تسجيل الدخول:
const { data } = await api.get('/api/notifications/unread-count');
setBadge(data.unread_count);

// عند فتح شاشة الإشعارات (أول تحميل):
let cursor = null;
async function loadNotifications(status = 'all') {
  const res = await api.get('/api/notifications', { params: { status, per_page: 15, cursor } });
  appendToList(res.data);
  cursor = res.pagination.next_cursor;
  setBadge(res.unread_count);
  return res.pagination.has_more_pages;
}

// عند الوصول لآخر القائمة (Infinite Scroll):
if (hasMorePages) await loadNotifications(currentStatus);

// عند الضغط على إشعار:
async function onTapNotification(notification) {
  openRelatedScreen(notification.data); // اختياري: حسب notification.data.type
  if (!notification.read_at) {
    notification.read_at = new Date().toISOString(); // optimistic update
    decrementBadge();
    try {
      await api.post(`/api/notifications/${notification.id}/read`);
    } catch (e) {
      notification.read_at = null; // rollback
      incrementBadge();
    }
  }
}

// عند حذف إشعار (Swipe to delete):
async function onDeleteNotification(notification) {
  removeFromListOptimistically(notification.id);
  try {
    const res = await api.delete(`/api/notifications/${notification.id}`);
    setBadge(res.unread_count); // القيمة الرسمية من السيرفر
  } catch (e) {
    restoreToList(notification); // rollback لو فشل
  }
}
```

</div>

---

## 11. ملاحظات أخيرة

- **لا تفترض قيم ثابتة لـ `data.type`.** أي نوع إشعار جديد بيضيفه الباك إند رح يوصل بنفس شكل الغلاف (`id`/`title`/`message`/`data`/`read_at`/`created_at`) — بس محتوى `data` بيختلف. تعامل مع `title`/`message` كنص جاهز دايماً يشتغل، واعتبر أي action إضافي مبني على `data.type` تحسين اختياري (progressive enhancement).
- **الترتيب** بكل القوائم هو الأحدث أولاً (`created_at` تنازلي) — لا داعي لإعادة ترتيب محلي.
- استخدم `GET /api/notifications/unread-count` بشكل خفيف ودوري (polling) أو بعد استلام Push جديد لتحديث الـ Badge، بدل تحميل القائمة كاملة في كل مرة.

</div>
