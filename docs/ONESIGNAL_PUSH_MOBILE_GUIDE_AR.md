# دليل إشعارات OneSignal لتطبيق Flutter — تسجيل الجهاز وتذكير المواعيد



## 0. القاعدة الذهبية — اقرأها قبل أي شيء

> **`device_id` = OneSignal Subscription ID** — أي القيمة `OneSignal.User.pushSubscription.id`.

السيرفر يرسل الإشعار إلى قيمة `device_id` المخزّنة **حرفياً**. إذا أرسلت أي شيء آخر (UUID الجهاز، `android_id`، FCM token…) فالطلب ينجح (201) **لكن لن يصل أي إشعار أبداً، ولن يظهر أي خطأ في التطبيق.**

| الحقل | ماذا تضع فيه | هل يُستخدم في الإرسال؟ |
|-------|--------------|------------------------|
| `device_id` | `OneSignal.User.pushSubscription.id` | ✅ نعم — هو العنوان الوحيد |
| `device_token` | `OneSignal.User.pushSubscription.token` (اختياري) | ❌ لا — للمعلومات فقط |

---

## 1. كيف يصل التذكير إلى الجوال (الصورة الكاملة)

```
[Flutter]                               [Laravel]                              [OneSignal]
   │                                        │                                       │
   │ 1. تسجيل دخول ناجح                     │                                       │
   │ 2. POST /api/register-device           │                                       │
   │    { device_id: <subscription id> } ──►│ user_devices (is_active = 1)          │
   │                                        │                                       │
   │ 3. الزبون يحجز ويختار "قبل ساعة"       │                                       │
   │    POST /api/appointments/reminders ──►│ appointment_reminders (pending)       │
   │                                        │ + Job مؤجَّل حتى remind_at             │
   │                                        │                                       │
   │                                        │ 4. عند remind_at:                     │
   │                                        │    هل reminder_push_enabled = true ؟  │
   │                                        │    ├─ لا  → لا push                    │
   │                                        │    └─ نعم → كل أجهزة الزبون الفعّالة ─►│
   │◄──────────────────────────────────────────────────────── 5. الإشعار ──────────│
   │ 6. الزبون يضغط → data.appointment_id → شاشة الموعد                            │
```

- **الإعداد `reminder_push_enabled` يُقرأ وقت الإرسال** وليس وقت الحجز. لو أطفأه الزبون بعد الحجز فلن يصله push.
- الإشعار يُرسل إلى **كل** الأجهزة الفعّالة للزبون (لو عنده جوالان مسجّلان يصل للاثنين).
- مع الـ push يُكتب أيضاً إشعار داخل التطبيق (in-app) في قاعدة البيانات.

---

## 2. تجهيز OneSignal في Flutter

### 2.1 الحزمة

```yaml
# pubspec.yaml
dependencies:
  onesignal_flutter: ^5.0.0
```

> هذا الدليل مكتوب على **SDK v5** (`OneSignal.User.pushSubscription...`). لا تستخدم SDK v3/v4 (`getDeviceState()` / `userId`) — واجهته مختلفة.

إعدادات Android/iOS الأصلية (Push capability في Xcode، App Groups، Notification Service Extension) اتبع فيها دليل OneSignal الرسمي لـ Flutter. هذا الملف يغطي التكامل مع الـ backend فقط.

### 2.2 التهيئة — مرة واحدة عند تشغيل التطبيق

```dart
import 'package:onesignal_flutter/onesignal_flutter.dart';

const oneSignalAppId = '<ONESIGNAL_APP_ID>'; // اطلبه من فريق الـ backend (ليس سرّياً)

Future<void> initPush() async {
  OneSignal.Debug.setLogLevel(OSLogLevel.warn); // verbose أثناء التطوير
  OneSignal.initialize(oneSignalAppId);

  // استقبال الضغط على الإشعار (القسم 6)
  OneSignal.Notifications.addClickListener(_onNotificationClicked);

  // الـ Subscription ID قد يتأخر أو يتغيّر → راقبه (القسم 3.3)
  OneSignal.User.pushSubscription.addObserver(_onSubscriptionChanged);
}
```

> ⚠️ **`ONESIGNAL_APP_ID` يجب أن يطابق تماماً** القيمة في `.env` على السيرفر. لو اختلف التطبيق (مثلاً App ID لمشروع تجريبي) فالـ Subscription IDs لن تكون موجودة في تطبيق OneSignal الذي يرسل منه السيرفر.

---

## 3. عند تسجيل الدخول — `POST /api/register-device`

### 3.1 متى تناديه

| الحدث | نادِه؟ | السبب |
|-------|--------|-------|
| بعد login / Google login / register **و** بعد التحقق من OTP | ✅ | ربط الجوال بالحساب |
| عند كل فتح للتطبيق والمستخدم مسجّل دخول مسبقاً | ✅ | يُحدّث `last_active_at` ويعيد تفعيل الجهاز إن أُلغي |
| عندما يتغيّر `pushSubscription.id` (observer) | ✅ | إعادة تثبيت / مسح بيانات التطبيق تُنتج ID جديد |
| بعد أن يسمح المستخدم بالإشعارات من الإعدادات | ✅ | قد يظهر ID لأول مرة |
| قبل التحقق من OTP | ❌ | السيرفر يرد **403** للحساب غير المُفعّل |
| `pushSubscription.id == null` | ❌ | لا يوجد عنوان بعد — انتظر الـ observer |

النداء **idempotent**: تكراره بنفس القيم آمن تماماً.

### 3.2 الطلب

```http
POST /api/register-device
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

```json
{
  "device_id": "1dd608f2-c6a1-11e3-851d-000c2940e62c",
  "device_token": "fcm-or-apns-token (اختياري)",
  "platform": "android",
  "os_version": "14",
  "app_version": "1.4.0",
  "meta": { "brand": "Samsung", "model": "Galaxy S24" }
}
```

| الحقل | النوع | إلزامي | ملاحظات |
|-------|-------|--------|---------|
| `device_id` | string | ✅ | **OneSignal Subscription ID** |
| `device_token` | string | ❌ | لا يُستخدم في الإرسال |
| `platform` | string | ❌ | `android` أو `ios` فقط — أي قيمة أخرى = 422 |
| `os_version` | string | ❌ | |
| `app_version` | string | ❌ | مفيد لتشخيص المشاكل |
| `meta` | object | ❌ | أي بيانات إضافية |

**✅ 201:**

```json
{
  "message": "Device registered successfully",
  "data": {
    "id": 7,
    "user_id": 12,
    "device_id": "1dd608f2-c6a1-11e3-851d-000c2940e62c",
    "platform": "android",
    "is_active": true,
    "last_active_at": "2026-10-03T09:15:00.000000Z"
  }
}
```

> **جوال واحد وحسابان:** إذا سجّل حساب B الدخول على جوال كان مسجّلاً لحساب A، فإن نداء `register-device` من B **ينقل** الجهاز إلى B تلقائياً، ولن تصل تذكيرات A إلى هذا الجوال بعد ذلك. لا تحتاج أي معالجة خاصة.

### 3.3 الكود

```dart
import 'dart:io' show Platform;

Future<void> registerDeviceWithBackend() async {
  // 1) اطلب الإذن (Android 13+ و iOS) — لا يضر تكراره
  await OneSignal.Notifications.requestPermission(true);

  // 2) تأكد أن الاشتراك مفعّل (قد يكون أُطفئ عند logout سابق — القسم 4)
  OneSignal.User.pushSubscription.optIn();

  // 3) خذ الـ ID — قد يكون null في أول تشغيل
  final subscriptionId = OneSignal.User.pushSubscription.id;
  if (subscriptionId == null || subscriptionId.isEmpty) return; // الـ observer سيكمل

  await _postRegister(subscriptionId);
}

Future<void> _postRegister(String subscriptionId) async {
  if (!auth.isLoggedIn || !auth.isVerified) return;

  await api.post('/api/register-device', data: {
    'device_id': subscriptionId,
    'device_token': OneSignal.User.pushSubscription.token,
    'platform': Platform.isIOS ? 'ios' : 'android',
    'os_version': Platform.operatingSystemVersion,
    'app_version': packageInfo.version,
  });
  // احفظ آخر ID أُرسل — تحتاجه في logout
  await storage.write(key: 'push_subscription_id', value: subscriptionId);
}

void _onSubscriptionChanged(OSPushSubscriptionChangedState state) {
  final newId = state.current.id;
  if (newId != null && newId.isNotEmpty && state.current.optedIn) {
    _postRegister(newId); // ID جديد أو أصبح متاحاً الآن
  }
}
```

**أين تستدعي `registerDeviceWithBackend()`:**
- بعد نجاح `POST /api/auth/login` (والحساب مُفعّل).
- بعد نجاح `POST /api/auth/verify-otp` / `verify-email-otp` (للحساب الجديد).
- بعد نجاح `POST /api/auth/google/mobile`.
- عند تشغيل التطبيق إذا كان هناك session محفوظة.

---

## 4. قبل تسجيل الخروج — `POST /api/deregister-device`

### 4.1 الترتيب مهم جداً

```
1. POST /api/deregister-device   ← بالتوكن الحالي (ما زال صالحاً)
2. POST /api/auth/logout          ← يُلغي التوكن
3. OneSignal.User.pushSubscription.optOut()
4. امسح التوكنات المحلية
```

> ❌ **لو ناديت `logout` أولاً** فالتوكن يُلغى و`deregister-device` يرد **401** — يبقى الجهاز فعّالاً على الحساب، وتستمر تذكيرات هذا الحساب بالوصول للجوال بعد الخروج.

`optOut()` في الخطوة 3 طبقة أمان ثانية: حتى لو فشلت الخطوة 1 (لا إنترنت مثلاً) فـ OneSignal لن يوصل أي إشعار لهذا الجوال حتى يُستدعى `optIn()` في الدخول التالي (القسم 3.3).

### 4.2 الطلب

```http
POST /api/deregister-device
Authorization: Bearer {access_token}
Content-Type: application/json
```

```json
{ "device_id": "1dd608f2-c6a1-11e3-851d-000c2940e62c" }
```

| الكود | المعنى | ماذا تفعل |
|-------|--------|-----------|
| **200** `Device unregistered successfully` | `is_active = false` | أكمل الخروج |
| **404** `Device not found` | الجهاز غير مسجّل لهذا الحساب (أو انتقل لحساب آخر) | **تجاهله** وأكمل الخروج |
| **401** | التوكن منتهي | جدّد التوكن بالـ refresh المعتاد ثم أعد المحاولة، أو أكمل الخروج (الـ optOut يحميك) |

### 4.3 الكود

```dart
Future<void> logout() async {
  final subscriptionId = OneSignal.User.pushSubscription.id
      ?? await storage.read(key: 'push_subscription_id');

  // 1) قبل إلغاء التوكن
  if (subscriptionId != null && subscriptionId.isNotEmpty) {
    try {
      await api.post('/api/deregister-device', data: {'device_id': subscriptionId});
    } catch (_) {
      // 404 أو مشكلة شبكة — لا تمنع الخروج
    }
  }

  // 2) إلغاء الجلسة
  try {
    await api.post('/api/auth/logout');
  } catch (_) {}

  // 3) أوقف الاستقبال على هذا الجوال
  OneSignal.User.pushSubscription.optOut();

  // 4) تنظيف محلي
  await storage.deleteAll();
}
```

> **حذف الحساب** (`DELETE /api/profile`): السيرفر يحذف أجهزة الحساب بنفسه — نادِ فقط `optOut()` محلياً.

---

## 5. ربط الجهاز بإعداد "تذكير عبر التطبيق"

تسجيل الجهاز **لا يكفي وحده** — يجب أن يكون الإعداد `reminder_push_enabled` مفعّلاً (الافتراضي `true`).

```http
PATCH /api/settings/reminder_push_enabled
Authorization: Bearer {token}

{ "value": true }
```

ردود التذكير (`GET /api/appointments/{id}/reminders` وغيرها) تحتوي:

```json
"channels": {
  "push": { "enabled": true, "deliverable": false, "effective": false }
}
```

| الحالة | المعنى | ماذا يعرض التطبيق |
|--------|--------|-------------------|
| `enabled: true, deliverable: true` | سيصل الـ push | لا شيء |
| `enabled: true, deliverable: false` | الإعداد مفعّل لكن **لا جهاز فعّال** مسجّل | اطلب إذن الإشعارات ثم `registerDeviceWithBackend()` |
| `enabled: false` | الزبون أطفأ الـ push | اعرض المفتاح مطفأً |

تفاصيل الشاشة والإعدادات: [`REMINDER_API_MOBILE_GUIDE_AR.md`](REMINDER_API_MOBILE_GUIDE_AR.md).

---

## 6. استقبال الإشعار وفتح الموعد

### 6.1 محتوى الإشعار

| الجزء | القيمة |
|-------|--------|
| العنوان | `تذكير بالموعد` / `Appointment reminder` / ألماني |
| النص | `تذكير: موعدك رقم APT-20261005-A1B2C3 في 2026-10-05 الساعة 14:00.` |
| `additionalData` | `{ "type": "appointment_reminder", "appointment_id": 345 }` |

### 6.2 الضغط على الإشعار

```dart
void _onNotificationClicked(OSNotificationClickEvent event) {
  final data = event.notification.additionalData ?? {};
  switch (data['type']) {
    case 'appointment_reminder':
      final id = int.tryParse('${data['appointment_id']}');
      if (id != null) {
        navigator.pushNamed('/appointments/details', arguments: id);
        // الشاشة تجلب البيانات من GET /api/appointments/{id}
      }
      break;
  }
}
```

- اعتمد على `type` للتوجيه — قد تُضاف أنواع إشعارات أخرى لاحقاً؛ تجاهل أي `type` لا تعرفه.
- إذا كان المستخدم قد خرج من الحساب عند الضغط: وجّهه لتسجيل الدخول أولاً.

### 6.3 لغة الإشعار

السيرفر يرسل العنوان والنص بثلاث لغات (`en` / `ar` / `de`)، وOneSignal **يختار حسب لغة الـ subscription** (لغة الجوال افتراضياً — وإن لم تكن من الثلاث يعرض `en`). إذا كان التطبيق يسمح بتغيير اللغة من داخله، زامنها:

```dart
OneSignal.User.setLanguage('ar'); // عند تغيير لغة التطبيق وعند تشغيله
```

---

## 7. استكشاف الأخطاء

| العَرَض | السبب المحتمل | الحل |
|---------|----------------|------|
| `register-device` يرجع 201 لكن لا يصل شيء | `device_id` ليس Subscription ID | استخدم `OneSignal.User.pushSubscription.id` |
| لا يصل شيء والـ ID صحيح | App ID في التطبيق ≠ App ID في السيرفر | طابقهما |
| 403 عند التسجيل | الحساب لم يتحقق من OTP بعد | سجّل الجهاز بعد التحقق |
| 422 | `device_id` فارغ أو `platform` ليس `android`/`ios` | راجع الطلب |
| `pushSubscription.id` = null | الإذن لم يُمنح بعد أو التهيئة لم تكتمل | الـ observer سيرسله عند توفره |
| يصل تذكير حساب قديم بعد الخروج | `logout` نودي قبل `deregister-device` | التزم بالترتيب في 4.1 + `optOut()` |
| لا شيء بعد إعادة الدخول | `optOut()` من الخروج السابق | `optIn()` في `registerDeviceWithBackend()` |
| `push.deliverable = false` | لا يوجد جهاز فعّال للحساب | نادِ `register-device` |

---

## 8. قائمة فحص قبل التسليم

- [ ] `device_id` = `OneSignal.User.pushSubscription.id` (وليس UUID/FCM).
- [ ] App ID في التطبيق مطابق لـ `ONESIGNAL_APP_ID` في السيرفر.
- [ ] `register-device` بعد كل دخول ناجح **وبعد التحقق**، وعند كل تشغيل، وفي الـ observer.
- [ ] `optIn()` عند الدخول — `optOut()` عند الخروج.
- [ ] `deregister-device` **قبل** `auth/logout`، و404 لا يوقف الخروج.
- [ ] الضغط على إشعار `appointment_reminder` يفتح `appointment_id` الصحيح.
- [ ] `OneSignal.User.setLanguage()` يتبع لغة التطبيق.

### سيناريو اختبار يدوي

1. سجّل دخول بحساب مُفعّل → تأكد أن `register-device` رجع 201 بالـ Subscription ID.
2. احجز موعداً واختر تذكيراً "قبل ساعة" (اختر موعداً بعد ساعة وبضع دقائق ليصل سريعاً).
3. اقفل التطبيق وانتظر → يصل الإشعار → اضغطه → تُفتح شاشة الموعد.
4. احجز موعداً آخر مع تذكير، ثم سجّل خروج قبل وقت التذكير → **لا يجب** أن يصل الإشعار لهذا الجوال.
5. سجّل دخول بحساب ثانٍ على نفس الجوال → `register-device` يرجع 201 (وليس 500)، وتذكيرات الحساب الثاني فقط تصل.
