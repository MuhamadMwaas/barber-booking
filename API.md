# API Documentation — BarberBooking

> **موجه لـ:** مطور Frontend
> **الشرح:** بالعربية — المصطلحات التقنية والحقول بالإنجليزية

---

## فهرس المحتويات

- [Base URL](#base-url)
- [Authentication](#authentication)
- [Headers المطلوبة](#headers-المطلوبة)
- [Authentication API](#authentication-api)
- [Profile API](#profile-api)
- [Providers API](#providers-api)
- [Services API](#services-api)
- [Availability API](#availability-api)
- [Appointments API](#appointments-api)
- [Bookings API](#bookings-api)
- [Devices API](#devices-api)
- [Print API](#print-api)

---

## Base URL

```
http://localhost:8000
```

في كل الأمثلة استخدم `{{base_url}}` كمتغير Postman، أو استبدله بـ:

```
http://localhost:8000
```

---

## Authentication

يستخدم المشروع **Laravel Sanctum** لإدارة التوثيق.

### كيف يعمل؟

1. أرسل طلب **Login** مع email + password
2. ستحصل في الـ response على `access_token`
3. أرسل هذا الـ token مع كل طلب محمي في الـ Header كالتالي:

```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

### أين يُستخدم؟

- كل endpoint محمي يتطلب هذا الـ header
- بدونه ستحصل على `401 Unauthorized`

### مثال كامل على Login ثم استخدام Token:

**Step 1 — Login:**
```http
POST {{base_url}}/api/auth/login
Content-Type: application/json
Accept: application/json

{
  "email": "hala.alhashimi@gmail.com",
  "password": "password"
}
```

**Step 2 — استخراج الـ Token من الـ Response:**
```json
{
  "access_token": "1|abc123xyz...",
  "token_type": "bearer"
}
```

**Step 3 — استخدام Token:**
```http
GET {{base_url}}/api/profile
Authorization: Bearer 1|abc123xyz...
Accept: application/json
```

### Token Types

| Token | الوصف | المدة |
|-------|-------|-------|
| `access_token` | للوصول إلى API | قصيرة المدة |
| `refresh_token` | لتجديد access_token | طويلة المدة |

### بيانات الدخول الافتراضية (من الـ Seeder)

| الدور | Email | Password |
|-------|-------|----------|
| Customer (مُتحقق) | `hala.alhashimi@gmail.com` | `password` |
| Admin | `admin@elitebeauty.ae` | `password` |

---

## Headers المطلوبة

### لكل الطلبات:
```
Accept: application/json
```

### للطلبات المحمية إضافةً إلى ما سبق:
```
Authorization: Bearer {access_token}
```

### لطلبات JSON body:
```
Content-Type: application/json
```

### لطلبات رفع ملفات (multipart):
```
Content-Type: multipart/form-data
```

---

## Authentication API

---

### 1. Login

```
POST /api/auth/login
```

**الوصف:** تسجيل دخول المستخدم والحصول على access_token.

**Authentication:** ❌ لا يتطلب

**Headers:**
```
Accept: application/json
Content-Type: application/json
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | البريد الإلكتروني |
| `password` | string | ✅ | كلمة المرور |

**Example Request:**
```json
{
  "email": "hala.alhashimi@gmail.com",
  "password": "password"
}
```

**Success Response — 200:**
```json
{
  "user": {
    "id": 5,
    "first_name": "Hala",
    "last_name": "Al Hashimi",
    "email": "hala.alhashimi@gmail.com",
    "phone": "+971-50-111-1111"
  },
  "access_token": "1|abc123xyz...",
  "access_expires_at": "2026-03-28T10:00:00.000000Z",
  "refresh_token": "def456...",
  "refresh_expires_at": "2026-04-28T10:00:00.000000Z",
  "token_type": "bearer"
}
```

**Error Response — 401:**
```json
{
  "message": "Invalid credentials"
}
```

---

### 2. Register

```
POST /api/auth/register
```

**الوصف:** تسجيل مستخدم جديد. يُرسل OTP للبريد الإلكتروني للتحقق.

**Authentication:** ❌ لا يتطلب

**Headers:**
```
Accept: application/json
Content-Type: application/json
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | ✅ | الاسم الأول (max: 255) |
| `last_name` | string | ✅ | الاسم الأخير (max: 255) |
| `email` | string | ✅ | البريد الإلكتروني — يجب أن يكون فريداً |
| `phone` | string | ❌ | رقم الهاتف — يجب أن يكون فريداً إن وُجد |
| `password` | string | ✅ | min: 8، يشترط: أحرف كبيرة وصغيرة + أرقام + رموز |
| `password_confirmation` | string | ✅ | يجب أن يطابق `password` |

**متطلبات كلمة المرور:**
- الحد الأدنى: 8 أحرف
- يحتوي على أحرف كبيرة وصغيرة
- يحتوي على أرقام
- يحتوي على رموز خاصة (مثال: `@`, `!`, `#`)

**Example Request:**
```json
{
  "first_name": "Test",
  "last_name": "User",
  "email": "testuser@example.com",
  "phone": "+971501234567",
  "password": "Password1@",
  "password_confirmation": "Password1@"
}
```

**Success Response — 201:**
```json
{
  "user": {
    "id": 20,
    "first_name": "Test",
    "last_name": "User",
    "email": "testuser@example.com"
  },
  "access_token": "2|xyz789...",
  "access_expires_at": "2026-03-28T10:00:00.000000Z",
  "refresh_token": "abc456...",
  "refresh_expires_at": "2026-04-28T10:00:00.000000Z",
  "token_type": "bearer",
  "otp": "123456"
}
```

> **ملاحظة:** `otp` يظهر في الـ response في بيئة التطوير فقط. في الإنتاج يُرسل للبريد فقط.

**Validation Error — 422:**
```json
{
  "success": false,
  "message": "بيانات غير صحيحة",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must contain at least one symbol."]
  }
}
```

---

### 3. Logout

```
POST /api/auth/logout
```

**الوصف:** تسجيل الخروج وحذف جميع tokens الخاصة بالمستخدم.

**Authentication:** ✅ يتطلب Bearer Token

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Request Body:** لا يوجد

**Success Response — 200:**
```json
{
  "message": "Logged out"
}
```

---

### 4. Refresh Token

```
POST /api/auth/refresh
```

**الوصف:** الحصول على access_token جديد باستخدام refresh_token.

**Authentication:** ❌ لا يتطلب (يكتفي بالـ refresh_token)

**Headers:**
```
Accept: application/json
Content-Type: application/json
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `refresh_token` | string | ✅ | الـ refresh_token من Login |

**Example Request:**
```json
{
  "refresh_token": "def456..."
}
```

**Success Response — 200:**
```json
{
  "access_token": "3|newtoken...",
  "access_expires_at": "2026-03-28T12:00:00.000000Z"
}
```

**Error Response — 401:**
```json
{
  "message": "Invalid or expired refresh token"
}
```

---

### 5. Forgot Password (Send OTP)

```
POST /api/auth/forgot-password
```

**الوصف:** يُرسل OTP إلى البريد الإلكتروني لإعادة تعيين كلمة المرور.

**Authentication:** ❌ لا يتطلب

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | البريد الإلكتروني المسجل |

**Example Request:**
```json
{
  "email": "hala.alhashimi@gmail.com"
}
```

**Success Response — 200:**
```json
{
  "message": "OTP sent to email",
  "otp": "123456"
}
```

> `otp` يظهر في dev mode فقط.

---

### 6. Reset Password (with OTP)

```
POST /api/auth/reset-password
```

**الوصف:** إعادة تعيين كلمة المرور باستخدام OTP المُرسل للبريد.

**Authentication:** ❌ لا يتطلب

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | البريد الإلكتروني |
| `otp` | string | ✅ | الكود المُرسل (6 أرقام) |
| `password` | string | ✅ | كلمة المرور الجديدة |
| `password_confirmation` | string | ✅ | تأكيد كلمة المرور |

**Example Request:**
```json
{
  "email": "hala.alhashimi@gmail.com",
  "otp": "123456",
  "password": "NewPassword1@",
  "password_confirmation": "NewPassword1@"
}
```

**Success Response — 200:**
```json
{
  "message": "Password reset successful"
}
```

**Error Response — 422:**
```json
{
  "error": "Invalid or expired OTP"
}
```

---

### 7. Request OTP

```
POST /api/auth/request-otp
```

**الوصف:** طلب إرسال OTP عبر البريد الإلكتروني أو SMS.

**Authentication:** ❌ لا يتطلب

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | ✅ | `email_otp` أو `sms_otp` |
| `email` | string | ✅ إذا type=email_otp | البريد الإلكتروني |
| `phone` | string | ✅ إذا type=sms_otp | رقم الهاتف |

**Example Request (Email):**
```json
{
  "type": "email_otp",
  "email": "hala.alhashimi@gmail.com"
}
```

**Example Request (SMS):**
```json
{
  "type": "sms_otp",
  "phone": "+971501111111"
}
```

**Success Response — 200:**
```json
{
  "message": "OTP sent to hala.alhashimi@gmail.com"
}
```

---

### 8. Verify OTP

```
POST /api/auth/verify-otp
```

**الوصف:** التحقق من صحة OTP.

**Authentication:** ❌ لا يتطلب

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | ✅ | `email_otp` أو `sms_otp` |
| `email` | string | ✅ إذا type=email_otp | البريد الإلكتروني |
| `phone` | string | ✅ إذا type=sms_otp | رقم الهاتف |
| `otp` | string | ✅ | رمز الـ OTP |

**Example Request:**
```json
{
  "type": "email_otp",
  "email": "hala.alhashimi@gmail.com",
  "otp": "123456"
}
```

**Success Response — 200:**
```json
{
  "message": "OTP verified"
}
```

**Error Response — 422:**
```json
{
  "error": "Invalid or expired OTP"
}
```

---

### 9. Verify Email via OTP

```
POST /api/auth/verify-email-otp
```

**الوصف:** التحقق من البريد الإلكتروني بعد التسجيل باستخدام OTP مكون من 6 أرقام.

**Authentication:** ❌ لا يتطلب

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | البريد الإلكتروني |
| `otp` | string | ✅ | OTP مكون من 6 أرقام بالضبط |

**Example Request:**
```json
{
  "email": "testuser@example.com",
  "otp": "123456"
}
```

**Success Response — 200:**
```json
{
  "message": "Email verified successfully",
  "email_verified": true
}
```

---

### 10. Resend Verification OTP

```
POST /api/auth/resend-verification-otp
```

**الوصف:** إعادة إرسال OTP للتحقق من البريد الإلكتروني.

**Authentication:** ❌ لا يتطلب

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | بريد مسجل وغير مُتحقق منه |

**Example Request:**
```json
{
  "email": "testuser@example.com"
}
```

**Success Response — 200:**
```json
{
  "message": "OTP sent to your email",
  "otp": "654321"
}
```

**Error Response — 400 (البريد مُتحقق منه مسبقاً):**
```json
{
  "message": "Email already verified"
}
```

---

## Profile API

> جميع endpoints في هذا القسم تتطلب **Authentication**

---

### 1. Get Profile

```
GET /api/profile
```

**الوصف:** جلب بيانات المستخدم الحالي المسجل دخوله.

**Authentication:** ✅ يتطلب Bearer Token

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 5,
    "first_name": "Hala",
    "last_name": "Al Hashimi",
    "email": "hala.alhashimi@gmail.com",
    "phone": "+971-50-111-1111",
    "city": "Dubai",
    "address": "Dubai, UAE",
    "avatar_url": "http://localhost:8000/storage/users/profile_images/5/Hala_5.png"
  }
}
```

---

### 2. Update Profile

```
POST /api/profile
```

**الوصف:** تحديث بيانات الملف الشخصي. جميع الحقول اختيارية.

**Authentication:** ✅ يتطلب Bearer Token

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

> استخدم `multipart/form-data` وليس JSON لدعم رفع الصورة.

**Request Body (Form Data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `first_name` | string | ❌ | الاسم الأول (max: 255) |
| `last_name` | string | ❌ | الاسم الأخير (max: 255) |
| `phone` | string | ❌ | رقم الهاتف (max: 20) |
| `address` | string | ❌ | العنوان (max: 500) |
| `city` | string | ❌ | المدينة (max: 255) |
| `image` | file | ❌ | صورة الملف الشخصي (max: 2MB، صور فقط) |

**Example Request:**
```
POST /api/profile
Content-Type: multipart/form-data

first_name=Hala
last_name=Updated
phone=+971501111111
city=Dubai
address=Marina Walk, Dubai
image=@/path/to/photo.jpg
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": {
    "id": 5,
    "first_name": "Hala",
    "last_name": "Updated",
    "phone": "+971501111111",
    "city": "Dubai"
  }
}
```

---

### 3. Change Password

```
POST /api/profile/change-password
```

**الوصف:** تغيير كلمة المرور. يُلغي جميع الـ tokens الحالية بعد التغيير.

**Authentication:** ✅ يتطلب Bearer Token

**Headers:**
```
Accept: application/json
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `current_password` | string | ✅ | كلمة المرور الحالية |
| `password` | string | ✅ | كلمة المرور الجديدة — min: 8، mixed case + numbers |
| `password_confirmation` | string | ✅ | تأكيد كلمة المرور الجديدة |

**Example Request:**
```json
{
  "current_password": "password",
  "password": "NewPassword1@",
  "password_confirmation": "NewPassword1@"
}
```

**Success Response — 200:**
```json
{
  "message": "Password updated"
}
```

> ⚠️ بعد تغيير كلمة المرور، يجب تسجيل الدخول مجدداً للحصول على token جديد.

**Error Response — 422 (كلمة المرور الحالية خاطئة):**
```json
{
  "message": "Current password incorrect"
}
```

---

## Providers API

> Public endpoints — لا تتطلب Authentication

---

### 1. Providers - List

```
GET /api/providers
```

**الوصف:** جلب قائمة جميع مقدمي الخدمات النشطين مع pagination.

**Authentication:** ❌ لا يتطلب

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `per_page` | integer | ❌ | 15 | عدد النتائج في الصفحة |
| `sort_by` | string | ❌ | `first_name` | حقل الترتيب |
| `sort_direction` | string | ❌ | `asc` | `asc` أو `desc` |
| `search` | string | ❌ | — | البحث بالاسم |
| `branch_id` | integer | ❌ | — | فلترة حسب الفرع |
| `service_id` | integer | ❌ | — | فلترة من يقدم خدمة معينة |

**Example Request:**
```
GET {{base_url}}/api/providers?per_page=15&sort_by=first_name&sort_direction=asc
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Providers retrieved successfully",
  "data": [
    {
      "id": 3,
      "first_name": "Sarah",
      "last_name": "Johnson",
      "full_name": "Sarah Johnson",
      "email": "sarah.johnson@elitebeauty.ae",
      "phone": "+971-55-111-2222",
      "avatar_url": "http://localhost:8000/storage/...",
      "services": [...]
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 8
  }
}
```

---

### 2. Providers - Show

```
GET /api/providers/{id}
```

**الوصف:** جلب تفاصيل مقدم خدمة محدد مع قائمة خدماته وعدد حجوزاته.

**Authentication:** ❌ لا يتطلب

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | ✅ | معرف مقدم الخدمة |

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `locale` | string | ❌ | لغة الاستجابة: `en`, `ar`, `de` |

**Example Request:**
```
GET {{base_url}}/api/providers/{{provider_id}}?locale=en
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Provider retrieved successfully",
  "data": {
    "id": 3,
    "full_name": "Sarah Johnson",
    "email": "sarah.johnson@elitebeauty.ae",
    "avatar_url": "http://localhost:8000/storage/...",
    "total_booking_count": 42,
    "services": [
      {
        "id": 1,
        "name": "Hair Cut",
        "price": "50.00",
        "duration_minutes": 30
      }
    ]
  }
}
```

**Error Response — 404:**
```json
{
  "success": false,
  "message": "Provider not found"
}
```

---

## Services API

> Public endpoints — لا تتطلب Authentication

---

### 1. Services - List

```
GET /api/services
```

**الوصف:** جلب قائمة جميع الخدمات النشطة مع pagination.

**Authentication:** ❌ لا يتطلب

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `per_page` | integer | ❌ | 15 | عدد النتائج في الصفحة |
| `sort_by` | string | ❌ | `sort_order` | حقل الترتيب |
| `sort_direction` | string | ❌ | `asc` | `asc` أو `desc` |
| `category_id` | integer | ❌ | — | فلترة حسب الفئة |
| `featured` | any | ❌ | — | عرض الخدمات المميزة فقط |
| `search` | string | ❌ | — | البحث في الاسم والوصف |

**Example Request:**
```
GET {{base_url}}/api/services?per_page=15&sort_by=sort_order&sort_direction=asc
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Services retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Hair Cut",
      "description": "Classic hair cut service",
      "price": "50.00",
      "discount_price": null,
      "duration_minutes": 30,
      "color_code": "#FF6B9D",
      "is_featured": false,
      "category": {
        "id": 1,
        "name": "Hair"
      },
      "providers": [
        {
          "id": 3,
          "first_name": "Sarah",
          "last_name": "Johnson"
        }
      ]
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 35
  }
}
```

---

### 2. Services - Show

```
GET /api/services/{id}
```

**الوصف:** جلب تفاصيل خدمة محددة مع المزودين والتقييمات والفئة.

**Authentication:** ❌ لا يتطلب

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | ✅ | معرف الخدمة (أرقام فقط) |

**Example Request:**
```
GET {{base_url}}/api/services/{{service_id}}
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Service retrieved successfully",
  "data": {
    "id": 1,
    "name": "Hair Cut",
    "description": "Classic hair cut service",
    "price": "50.00",
    "discount_price": null,
    "duration_minutes": 30,
    "color_code": "#FF6B9D",
    "category": {
      "id": 1,
      "name": "Hair",
      "description": "Hair services"
    },
    "providers": [...],
    "reviews": [...]
  }
}
```

**Error Response — 404:**
```json
{
  "success": false,
  "message": "Service not found"
}
```

---

## Availability API

> Public endpoints — لا تتطلب Authentication

---

### 1. Availability - Provider Slots

```
GET /api/availability/provider
```

**الوصف:** جلب الأوقات المتاحة لمقدم خدمة محدد في تاريخ معين.

**Authentication:** ❌ لا يتطلب

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service_id` | integer | ✅ | معرف الخدمة |
| `provider_id` | integer | ✅ | معرف مقدم الخدمة |
| `date` | string | ✅ | التاريخ بصيغة `Y-m-d` — اليوم أو مستقبلاً |
| `branch_id` | integer | ❌ | معرف الفرع |

**Example Request:**
```
GET {{base_url}}/api/availability/provider?service_id={{service_id}}&provider_id={{provider_id}}&date=2026-03-16
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Provider availability retrieved successfully",
  "data": {
    "date": "2026-03-16",
    "provider_id": 3,
    "service_id": 1,
    "available_slots": [
      "09:00",
      "09:30",
      "10:00",
      "10:30",
      "14:00",
      "14:30"
    ],
    "working_hours": {
      "start": "09:00",
      "end": "17:00"
    }
  }
}
```

**Error Response — 400:**
```json
{
  "success": false,
  "message": "Provider 'Sarah Johnson' does not work on Sunday"
}
```

---

### 2. Availability - Calendar

```
GET /api/availability/calendar
```

**الوصف:** جلب تقويم التوفر لنطاق تاريخي. الحد الأقصى 31 يوماً.

**Authentication:** ❌ لا يتطلب

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `service_id` | integer | ✅ | معرف الخدمة |
| `provider_id` | integer | ❌ | معرف مقدم الخدمة |
| `start_date` | string | ✅ | تاريخ البداية `Y-m-d` — اليوم أو مستقبلاً |
| `end_date` | string | ✅ | تاريخ النهاية `Y-m-d` — يجب أن يكون بعد start_date |
| `branch_id` | integer | ❌ | معرف الفرع |

**Example Request:**
```
GET {{base_url}}/api/availability/calendar?service_id={{service_id}}&provider_id={{provider_id}}&start_date=2026-03-16&end_date=2026-03-31
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Availability calendar retrieved successfully",
  "data": {
    "2026-03-16": {
      "available": true,
      "slots_count": 8
    },
    "2026-03-17": {
      "available": true,
      "slots_count": 12
    },
    "2026-03-18": {
      "available": false,
      "slots_count": 0,
      "reason": "Day off"
    }
  }
}
```

**Error Response — 400 (نطاق أكبر من 31 يوماً):**
```json
{
  "success": false,
  "message": "Date range cannot exceed 31 days"
}
```

---

## Appointments API

> جميع endpoints تتطلب **Authentication**

---

### 1. Appointments - List

```
GET /api/appointments
```

**الوصف:** جلب قائمة حجوزات المستخدم الحالي مع فلاتر متعددة. النتائج paginated.

**Authentication:** ✅ يتطلب Bearer Token

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `per_page` | integer | ❌ | عدد النتائج (1-100، افتراضي: 15) |
| `status` | string | ❌ | `PENDING` \| `COMPLETED` \| `USER_CANCELLED` \| `ADMIN_CANCELLED` \| `ALL` |
| `payment_status` | string | ❌ | `PENDING` \| `PAID_ONLINE` \| `PAID_ONSTIE_CASH` \| `PAID_ONSTIE_CARD` \| `FAILED` \| `REFUNDED` \| `PARTIALLY_REFUNDED` |
| `date_from` | string | ❌ | تاريخ البداية `Y-m-d` |
| `date_to` | string | ❌ | تاريخ النهاية `Y-m-d` |
| `type` | string | ❌ | `upcoming` أو `past` |
| `sort_by` | string | ❌ | `appointment_date` \| `created_at` \| `total_amount` |
| `sort_direction` | string | ❌ | `asc` أو `desc` |

**Example Request:**
```
GET {{base_url}}/api/appointments?per_page=15&status=PENDING
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم جلب قائمة الحجوزات بنجاح",
  "data": {
    "data": [
      {
        "id": 10,
        "number": "APT-20260315-A1B2C3",
        "appointment_date": "2026-03-16",
        "start_time": "10:00",
        "end_time": "10:30",
        "duration_minutes": 30,
        "status": "PENDING",
        "status_value": 0,
        "payment_status": "PAID_ONSTIE_CASH",
        "total_amount": 50.00,
        "can_cancel": true
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 5,
    "last_page": 1
  }
}
```

**قيم AppointmentStatus:**

| القيمة | الاسم | الوصف |
|--------|-------|-------|
| `0` | `PENDING` | قيد الانتظار |
| `1` | `COMPLETED` | مكتمل |
| `-1` | `USER_CANCELLED` | ألغاه العميل |
| `-2` | `ADMIN_CANCELLED` | ألغاه الإدارة |
| `-3` | `NO_SHOW` | لم يحضر |

---

### 2. Appointments - Show

```
GET /api/appointments/{id}
```

**الوصف:** جلب تفاصيل حجز محدد. يعيد خطأ 403 إذا لم يكن الحجز ملك المستخدم.

**Authentication:** ✅ يتطلب Bearer Token

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | ✅ | معرف الحجز (أرقام فقط) |

**Example Request:**
```
GET {{base_url}}/api/appointments/{{appointment_id}}
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم جلب بيانات الحجز بنجاح",
  "data": {
    "id": 10,
    "number": "APT-20260315-A1B2C3",
    "appointment_date": "2026-03-16",
    "formatted_date": "Mar 16, 2026",
    "start_time": "10:00",
    "end_time": "10:30",
    "time_range": "10:00 AM - 10:30 AM",
    "duration_minutes": 30,
    "formatted_duration": "30m",
    "subtotal": 42.02,
    "tax_amount": 7.98,
    "total_amount": 50.00,
    "status": "PENDING",
    "status_value": 0,
    "status_label": "Pending",
    "payment_status": "PAID_ONSTIE_CASH",
    "payment_status_value": 2,
    "payment_status_label": "Paid On site Cash",
    "payment_method": "cash",
    "cancellation_reason": null,
    "cancelled_at": null,
    "provider": {
      "id": 3,
      "full_name": "Sarah Johnson",
      "email": "sarah.johnson@elitebeauty.ae",
      "phone": "+971-55-111-2222",
      "avatar_url": "http://localhost:8000/storage/..."
    },
    "services_details": [
      {
        "id": 1,
        "service_id": 1,
        "service_name": "Hair Cut",
        "duration_minutes": 30,
        "formatted_duration": "30m",
        "price": 50.00,
        "formatted_price": "50.00",
        "sequence_order": 1
      }
    ],
    "notes": "Please be on time",
    "created_at": "2026-02-28 10:00:00",
    "updated_at": "2026-02-28 10:00:00",
    "is_upcoming": true,
    "is_past": false,
    "is_cancelled": false,
    "is_completed": false,
    "can_cancel": true
  }
}
```

**Error Response — 403 (ليس حجز المستخدم):**
```json
{
  "success": false,
  "message": "Unauthorized access to this appointment",
  "error_type": "access_error"
}
```

**Error Response — 404:**
```json
{
  "success": false,
  "message": "الحجز المطلوب غير موجود",
  "error_type": "not_found"
}
```

---

### 3. Appointments - Upcoming

```
GET /api/appointments/upcoming
```

**الوصف:** جلب الحجوزات القادمة خلال N أيام.

**Authentication:** ✅ يتطلب Bearer Token

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `days` | integer | ❌ | 7 | عدد الأيام القادمة (1-90) |

**Example Request:**
```
GET {{base_url}}/api/appointments/upcoming?days=7
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم جلب الحجوزات المقبلة خلال 7 أيام",
  "data": [...],
  "count": 3
}
```

---

### 4. Appointments - Past

```
GET /api/appointments/past
```

**الوصف:** جلب آخر N حجوز سابقة.

**Authentication:** ✅ يتطلب Bearer Token

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | ❌ | 10 | عدد النتائج (1-50) |

**Example Request:**
```
GET {{base_url}}/api/appointments/past?limit=10
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم جلب الحجوزات السابقة",
  "data": [...],
  "count": 10
}
```

---

### 5. Appointments - Statistics

```
GET /api/appointments/statistics
```

**الوصف:** جلب إحصائيات الحجوزات للمستخدم الحالي.

**Authentication:** ✅ يتطلب Bearer Token

**Example Request:**
```
GET {{base_url}}/api/appointments/statistics
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم جلب إحصائيات الحجوزات",
  "data": {
    "total": 15,
    "pending": 2,
    "completed": 10,
    "cancelled": 3,
    "total_spent": 750.00,
    "upcoming_count": 2
  }
}
```

---

### 6. Appointments - Search

```
GET /api/appointments/search
```

**الوصف:** البحث في حجوزات المستخدم.

**Authentication:** ✅ يتطلب Bearer Token

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `query` | string | ✅ | نص البحث — الحد الأدنى حرفان |

**Example Request:**
```
GET {{base_url}}/api/appointments/search?query=APT
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم البحث بنجاح",
  "data": [...],
  "count": 2
}
```

**Error Response — 422:**
```json
{
  "success": false,
  "message": "The query must be at least 2 characters."
}
```

---

### 7. Appointments - Cancel

```
POST /api/appointments/{id}/cancel
```

**الوصف:** إلغاء حجز بحالة PENDING. لا يمكن إلغاء حجز مكتمل أو مُلغى مسبقاً.

**Authentication:** ✅ يتطلب Bearer Token

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | ✅ | معرف الحجز |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reason` | string | ❌ | سبب الإلغاء (max: 500) |

**Example Request:**
```json
{
  "reason": "Change of plans"
}
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "تم إلغاء الحجز بنجاح",
  "data": {
    "id": 10,
    "status": "USER_CANCELLED",
    "status_value": -1,
    "cancellation_reason": "Change of plans",
    "cancelled_at": "2026-02-28 10:30:00"
  }
}
```

**Error Response — 422 (الحجز ليس PENDING):**
```json
{
  "success": false,
  "message": "Only pending appointments can be cancelled",
  "error_type": "business_error"
}
```

---

### 8. Appointments - Set Reminder

```
POST /api/appointments/reminders
```

**الوصف:** تعيين تذكير لحجز قادم. يجب أن يكون وقت التذكير قبل بدء الحجز وبعد الوقت الحالي.

**Authentication:** ✅ يتطلب Bearer Token

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `appointment_id` | integer | ✅ | معرف الحجز — يجب أن يكون ملك المستخدم |
| `remind_at` | datetime | ✅ | وقت التذكير — بعد الآن وقبل بداية الحجز |

**Example Request:**
```json
{
  "appointment_id": 10,
  "remind_at": "2026-03-15 09:00:00"
}
```

**Success Response — 201:**
```json
{
  "success": true,
  "message": "Reminder created successfully",
  "data": {
    "reminder_id": 5,
    "appointment_id": 10,
    "remind_at": "2026-03-15T09:00:00+00:00",
    "status": "pending"
  }
}
```

**Error Response — 422:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "remind_at": ["The remind_at must be before the appointment start time."],
    "appointment_id": ["The appointment has been cancelled."]
  }
}
```

**Error Response — 404:**
```json
{
  "success": false,
  "message": "Appointment not found",
  "error_type": "not_found"
}
```

---

## Bookings API

> جميع endpoints تتطلب **Authentication + Email Verification**

> ⚠️ **مهم:** يجب التحقق من البريد الإلكتروني (`email_verified_at`) لاستخدام هذه الـ endpoints. بدونه ستحصل على `403 Forbidden`.

---

### 1. Bookings - List

```
GET /api/bookings
```

**الوصف:** جلب قائمة حجوزات العميل الحالي.

**Authentication:** ✅ يتطلب Bearer Token + Email Verified

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | integer | ❌ | قيمة رقمية: `0`=PENDING، `1`=COMPLETED، `-1`=USER_CANCELLED |

**Example Request:**
```
GET {{base_url}}/api/bookings
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Bookings retrieved successfully",
  "data": [
    {
      "id": 10,
      "number": "APT-20260316-X1Y2Z3",
      "appointment_date": "2026-03-16",
      "start_time": "10:00",
      "end_time": "10:30",
      "status": "PENDING",
      "total_amount": 50.00,
      "can_cancel": true
    }
  ]
}
```

---

### 2. Bookings - Create

```
POST /api/bookings
```

**الوصف:** إنشاء حجز جديد لخدمة واحدة أو أكثر. يتحقق من توفر الوقت وجدول المزود تلقائياً.

**Authentication:** ✅ يتطلب Bearer Token + Email Verified

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | string | ✅ | تاريخ الحجز `Y-m-d` — اليوم أو مستقبلاً |
| `payment_method` | string | ✅ | `cash` أو `online` |
| `notes` | string | ❌ | ملاحظات (max: 1000) |
| `services` | array | ✅ | قائمة الخدمات (1-10 خدمات، بدون تكرار) |
| `services[].service_id` | integer | ✅ | معرف الخدمة — يجب أن يكون موجوداً في DB |
| `services[].provider_id` | integer | ✅ | معرف مقدم الخدمة — يجب أن يقدم الخدمة المحددة |
| `services[].start_time` | string | ✅ | وقت البدء بصيغة `H:i` مثال: `10:00` |

**قواعد الـ Validation:**
- الخدمات يجب أن تكون متتالية (لا تداخل في الأوقات)
- المزود يجب أن يكون نشطاً ويقدم الخدمة
- الوقت يجب أن يكون ضمن ساعات عمل المزود
- لا يجوز الحجز إذا كان هناك حجز آخر في نفس الوقت للمزود
- الحجز يجب أن يكون على الأقل `book_buffer` دقيقة مقدماً

**Example Request (خدمة واحدة):**
```json
{
  "date": "2026-03-16",
  "payment_method": "cash",
  "notes": "Please be on time",
  "services": [
    {
      "service_id": 1,
      "provider_id": 3,
      "start_time": "10:00"
    }
  ]
}
```

**Example Request (خدمتان متتاليتان):**
```json
{
  "date": "2026-03-17",
  "payment_method": "cash",
  "notes": "Multi-service booking",
  "services": [
    {
      "service_id": 1,
      "provider_id": 3,
      "start_time": "10:00"
    },
    {
      "service_id": 2,
      "provider_id": 3,
      "start_time": "10:30"
    }
  ]
}
```

**Success Response — 201:**
```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 12,
    "number": "APT-20260316-A1B2C3",
    "appointment_date": "2026-03-16",
    "formatted_date": "Mar 16, 2026",
    "start_time": "10:00",
    "end_time": "10:30",
    "time_range": "10:00 AM - 10:30 AM",
    "duration_minutes": 30,
    "formatted_duration": "30m",
    "subtotal": 42.02,
    "tax_amount": 7.98,
    "total_amount": 50.00,
    "status": "PENDING",
    "status_value": 0,
    "status_label": "Pending",
    "payment_status": "PAID_ONSTIE_CASH",
    "payment_status_value": 2,
    "payment_method": "cash",
    "provider": {
      "id": 3,
      "full_name": "Sarah Johnson",
      "avatar_url": "http://localhost:8000/storage/..."
    },
    "services_details": [
      {
        "service_name": "Hair Cut",
        "duration_minutes": 30,
        "price": 50.00,
        "sequence_order": 1
      }
    ],
    "notes": "Please be on time",
    "is_upcoming": true,
    "can_cancel": true
  }
}
```

**Validation Error — 422:**
```json
{
  "success": false,
  "message": "Time slot 10:00 - 10:30 is already booked for provider 'Sarah Johnson'",
  "error_type": "validation_error"
}
```

**أنواع أخطاء الـ Business Logic الشائعة:**

| الرسالة | السبب |
|---------|-------|
| `Provider 'X' does not offer service 'Y'` | المزود لا يقدم الخدمة المحددة |
| `Provider 'X' does not work on Sunday` | لا جدول عمل في هذا اليوم |
| `Time slot is outside working hours (09:00 - 17:00)` | الوقت خارج ساعات العمل |
| `Provider has time off during the requested time slot` | المزود في إجازة |
| `Time slot is already booked` | الوقت محجوز مسبقاً |
| `Booking must be at least X minutes in advance` | الحجز قريب جداً من الوقت الحالي |
| `Cannot book more than X days in advance` | التاريخ بعيد جداً |
| `You already have a booking for the same time and services` | حجز مكرر |

---

### 3. Bookings - Show

```
GET /api/bookings/{id}
```

**الوصف:** جلب تفاصيل حجز محدد.

**Authentication:** ✅ يتطلب Bearer Token + Email Verified

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | ✅ | معرف الحجز |

**Example Request:**
```
GET {{base_url}}/api/bookings/{{booking_id}}
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Booking details retrieved successfully",
  "data": { ... }
}
```

**Error Response — 403 (ليس حجز المستخدم):**
```json
{
  "success": false,
  "message": "Unauthorized access to this appointment",
  "error_type": "authorization_error"
}
```

> ⚠️ إذا لم يوجد الحجز، تُرجع `500` بدلاً من `404` — هذا خطأ معروف في الكود.

---

### 4. Bookings - Cancel

```
POST /api/bookings/{id}/cancel
```

**الوصف:** إلغاء حجز PENDING فقط.

**Authentication:** ✅ يتطلب Bearer Token + Email Verified

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | ✅ | معرف الحجز |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `cancellation_reason` | string | ❌ | سبب الإلغاء |

**Example Request:**
```json
{
  "cancellation_reason": "Cannot attend anymore"
}
```

**Success Response — 200:**
```json
{
  "success": true,
  "message": "Booking cancelled successfully",
  "data": {
    "id": 12,
    "status": "USER_CANCELLED",
    "cancelled_at": "2026-02-28 10:30:00"
  }
}
```

**Error Response — 422:**
```json
{
  "success": false,
  "message": "Only pending appointments can be cancelled",
  "error_type": "validation_error"
}
```

---

## Devices API

> يُستخدم لتسجيل أجهزة المستخدمين لاستقبال Push Notifications.

---

### 1. Devices - Register

```
POST /api/register-device
```

**الوصف:** تسجيل أو تحديث جهاز للمستخدم الحالي. يستخدم `updateOrCreate` بناءً على `device_id + user_id`.

**Authentication:** ✅ يتطلب Bearer Token

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `device_id` | string | ✅ | معرف الجهاز الفريد |
| `device_token` | string | ❌ | FCM/APNs push token |
| `platform` | string | ❌ | `android` أو `ios` |
| `os_version` | string | ❌ | إصدار نظام التشغيل |
| `app_version` | string | ❌ | إصدار التطبيق |
| `meta` | object | ❌ | بيانات إضافية بأي شكل |

**Example Request:**
```json
{
  "device_id": "device-uuid-abc123",
  "device_token": "fcm-push-token-here",
  "platform": "android",
  "os_version": "14.0",
  "app_version": "1.0.0",
  "meta": {
    "brand": "Samsung",
    "model": "Galaxy S24"
  }
}
```

**Success Response — 201:**
```json
{
  "message": "Device registered successfully",
  "data": {
    "id": 1,
    "user_id": 5,
    "device_id": "device-uuid-abc123",
    "platform": "android",
    "is_active": true,
    "last_active_at": "2026-02-28T10:00:00.000000Z"
  }
}
```

---

### 2. Devices - Unregister

```
POST /api/deregister-device
```

**الوصف:** إلغاء تسجيل جهاز (يضع `is_active = false`).

**Authentication:** ✅ يتطلب Bearer Token

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `device_id` | string | ✅ | معرف الجهاز المراد إلغاء تسجيله |

**Example Request:**
```json
{
  "device_id": "device-uuid-abc123"
}
```

**Success Response — 200:**
```json
{
  "message": "Device unregistered successfully"
}
```

**Error Response — 404:**
```json
{
  "message": "Device not found"
}
```

---

## Print API

> Endpoints للطباعة وإدارة الطابعات — تتطلب Authentication

---

### 1. Print - Invoice

```
POST /api/invoice/{invoice}/print
```

**الوصف:** طباعة فاتورة محددة.

**Authentication:** ✅ يتطلب Bearer Token

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `invoice` | integer | ✅ | معرف الفاتورة |

**Example Request:**
```
POST {{base_url}}/api/invoice/{{invoice_id}}/print
```

---

### 2. Print - Batch

```
POST /api/invoices/print-batch
```

**الوصف:** طباعة مجموعة من الفواتير دفعة واحدة.

**Authentication:** ✅ يتطلب Bearer Token

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `invoice_ids` | array | ✅ | مصفوفة معرفات الفواتير |

**Example Request:**
```json
{
  "invoice_ids": [1, 2, 3]
}
```

---

### 3. Print - Get URL

```
GET /api/invoice/{invoice}/print-url
```

**الوصف:** الحصول على URL قابل للطباعة لفاتورة محددة.

**Authentication:** ✅ يتطلب Bearer Token

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `invoice` | integer | ✅ | معرف الفاتورة |

**Example Request:**
```
GET {{base_url}}/api/invoice/{{invoice_id}}/print-url
```

---

### 4. Print - Test Printer

```
POST /api/printer/{printer}/test
```

**الوصف:** إرسال طباعة اختبارية لطابعة محددة.

**Authentication:** ✅ يتطلب Bearer Token

**Path Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `printer` | integer | ✅ | معرف الطابعة |

**Example Request:**
```
POST {{base_url}}/api/printer/{{printer_id}}/test
```

---

### 5. Print - Statistics

```
GET /api/print/statistics
```

**الوصف:** جلب إحصائيات الطباعة.

**Authentication:** ✅ يتطلب Bearer Token

**Example Request:**
```
GET {{base_url}}/api/print/statistics
```

---

### 6. Print - Logs

```
GET /api/print/logs
```

**الوصف:** جلب سجل عمليات الطباعة.

**Authentication:** ✅ يتطلب Bearer Token

**Example Request:**
```
GET {{base_url}}/api/print/logs
```

---

## ملخص الـ Errors الشائعة

| Status Code | الوصف | السبب الشائع |
|-------------|-------|-------------|
| `401` | Unauthorized | لم تُرسل token أو انتهت صلاحيتها |
| `403` | Forbidden | محاولة الوصول لبيانات مستخدم آخر |
| `404` | Not Found | المورد غير موجود |
| `422` | Unprocessable Entity | خطأ في الـ Validation أو قواعد العمل |
| `500` | Server Error | خطأ في السيرفر |

## شكل الـ Error Response العام

```json
{
  "success": false,
  "message": "وصف الخطأ",
  "errors": {
    "field_name": ["رسالة الخطأ"]
  },
  "error_type": "validation_error"
}
```

**قيم `error_type`:**
- `validation_error` — خطأ في بيانات الـ request
- `authorization_error` — لا صلاحية للوصول
- `not_found` — المورد غير موجود
- `business_error` — خطأ في قواعد العمل
- `server_error` — خطأ في السيرفر

---
