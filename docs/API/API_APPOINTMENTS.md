# 📱 Appointments API Documentation

## 🔐 Authentication

All endpoints require authentication using **Bearer Token**.

### Headers Required:
```http
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

---

## 📋 Base URL

```
https://your-domain.com/api/appointments
```

---

## 📑 Table of Contents

1. [Get All Appointments](#1-get-all-appointments)
2. [Get Appointment Statistics](#2-get-appointment-statistics)
3. [Get Upcoming Appointments](#3-get-upcoming-appointments)
4. [Get Past Appointments](#4-get-past-appointments)
5. [Search Appointments](#5-search-appointments)
6. [Get Single Appointment](#6-get-single-appointment)
7. [Cancel Appointment](#7-cancel-appointment)
8. [Response Structure](#response-structure)
9. [Error Handling](#error-handling)

---

## 1. Get All Appointments

Get a paginated list of appointments with advanced filtering options.

### Endpoint
```http
GET /api/appointments
```

### Query Parameters

| Parameter | Type | Required | Values | Default | Description |
|-----------|------|----------|--------|---------|-------------|
| `status` | string | No | `PENDING`, `COMPLETED`, `USER_CANCELLED`, `ADMIN_CANCELLED`, `ALL` | `ALL` | Filter by appointment status |
| `payment_status` | string | No | `PENDING`, `PAID_ONLINE`, `PAID_ONSTIE_CASH`, `PAID_ONSTIE_CARD`, `FAILED`, `REFUNDED`, `PARTIALLY_REFUNDED` | - | Filter by payment status |
| `date_from` | string | No | `Y-m-d` format | - | Start date for filtering |
| `date_to` | string | No | `Y-m-d` format | - | End date for filtering |
| `type` | string | No | `upcoming`, `past` | - | Filter by time period |
| `sort_by` | string | No | `appointment_date`, `created_at`, `total_amount` | `appointment_date` | Sort field |
| `sort_direction` | string | No | `asc`, `desc` | `asc` | Sort direction |
| `per_page` | integer | No | `1-100` | `15` | Items per page |

### Request Example (JavaScript/Axios)

```javascript
// Example 1: Get all pending appointments
const response = await axios.get('/api/appointments', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  },
  params: {
    status: 'PENDING',
    per_page: 20,
    sort_by: 'appointment_date',
    sort_direction: 'asc'
  }
});
```

```javascript
// Example 2: Get appointments for specific date range
const response = await axios.get('/api/appointments', {
  headers: {
    'Authorization': `Bearer ${token}`
  },
  params: {
    date_from: '2026-02-01',
    date_to: '2026-02-28',
    payment_status: 'PAID_ONLINE'
  }
});
```

### Request Example (cURL)

```bash
curl -X GET "https://your-domain.com/api/appointments?status=PENDING&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم جلب قائمة الحجوزات بنجاح",
  "data": [
    {
      "id": 123,
      "appointment_date": "2026-02-15",
      "appointment_time": "14:00:00",
      "status": "PENDING",
      "payment_status": "PENDING",
      "total_amount": 150.00,
      "customer": {
        "id": 45,
        "name": "أحمد محمد",
        "phone": "+971501234567"
      },
      "services": [
        {
          "id": 12,
          "name": "قص شعر",
          "price": 50.00
        },
        {
          "id": 13,
          "name": "حلاقة ذقن",
          "price": 30.00
        }
      ],
      "provider": {
        "id": 5,
        "name": "محمد الحلاق"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

---

## 2. Get Appointment Statistics

Get statistical data about user's appointments.

### Endpoint
```http
GET /api/appointments/statistics
```

### Parameters
No parameters required.

### Request Example (JavaScript/Axios)

```javascript
const response = await axios.get('/api/appointments/statistics', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});
```

### Request Example (cURL)

```bash
curl -X GET "https://your-domain.com/api/appointments/statistics" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم جلب إحصائيات الحجوزات",
  "data": {
    "total_appointments": 45,
    "pending": 5,
    "completed": 35,
    "cancelled": 5,
    "total_spent": 6750.00,
    "upcoming_count": 3,
    "this_month": 8,
    "last_month": 12
  }
}
```

---

## 3. Get Upcoming Appointments

Get appointments scheduled in the future.

### Endpoint
```http
GET /api/appointments/upcoming
```

### Query Parameters

| Parameter | Type | Required | Values | Default | Description |
|-----------|------|----------|--------|---------|-------------|
| `days` | integer | No | `1-90` | `7` | Number of days to look ahead |

### Request Example (JavaScript/Axios)

```javascript
// Get appointments for next 14 days
const response = await axios.get('/api/appointments/upcoming', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  },
  params: {
    days: 14
  }
});
```

### Request Example (cURL)

```bash
curl -X GET "https://your-domain.com/api/appointments/upcoming?days=14" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم جلب الحجوزات المقبلة خلال 14 أيام",
  "data": [
    {
      "id": 124,
      "appointment_date": "2026-02-05",
      "appointment_time": "10:00:00",
      "status": "PENDING",
      "total_amount": 100.00,
      "services": [
        {
          "id": 12,
          "name": "قص شعر",
          "price": 50.00
        }
      ],
      "provider": {
        "id": 5,
        "name": "محمد الحلاق"
      }
    }
  ],
  "count": 3
}
```

---

## 4. Get Past Appointments

Get historical appointments.

### Endpoint
```http
GET /api/appointments/past
```

### Query Parameters

| Parameter | Type | Required | Values | Default | Description |
|-----------|------|----------|--------|---------|-------------|
| `limit` | integer | No | `1-50` | `10` | Number of appointments to return |

### Request Example (JavaScript/Axios)

```javascript
// Get last 20 appointments
const response = await axios.get('/api/appointments/past', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  },
  params: {
    limit: 20
  }
});
```

### Request Example (cURL)

```bash
curl -X GET "https://your-domain.com/api/appointments/past?limit=20" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم جلب الحجوزات السابقة",
  "data": [
    {
      "id": 120,
      "appointment_date": "2026-01-25",
      "appointment_time": "14:00:00",
      "status": "COMPLETED",
      "payment_status": "PAID_ONLINE",
      "total_amount": 150.00,
      "services": [
        {
          "id": 12,
          "name": "قص شعر",
          "price": 50.00
        }
      ]
    }
  ],
  "count": 20
}
```

---

## 5. Search Appointments

Search appointments by query string.

### Endpoint
```http
GET /api/appointments/search
```

### Query Parameters

| Parameter | Type | Required | Values | Description |
|-----------|------|----------|--------|-------------|
| `query` | string | **Yes** | min: 2 chars | Search term |

### Request Example (JavaScript/Axios)

```javascript
// Search for appointments
const response = await axios.get('/api/appointments/search', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  },
  params: {
    query: 'أحمد'
  }
});
```

### Request Example (cURL)

```bash
curl -X GET "https://your-domain.com/api/appointments/search?query=أحمد" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم البحث بنجاح",
  "data": [
    {
      "id": 123,
      "appointment_date": "2026-02-15",
      "appointment_time": "14:00:00",
      "status": "PENDING",
      "total_amount": 150.00,
      "customer": {
        "id": 45,
        "name": "أحمد محمد"
      }
    }
  ],
  "count": 5
}
```

---

## 6. Get Single Appointment

Get detailed information about a specific appointment.

### Endpoint
```http
GET /api/appointments/{id}
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | **Yes** | Appointment ID |

### Request Example (JavaScript/Axios)

```javascript
// Get appointment with ID 123
const response = await axios.get('/api/appointments/123', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});
```

### Request Example (cURL)

```bash
curl -X GET "https://your-domain.com/api/appointments/123" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم جلب بيانات الحجز بنجاح",
  "data": {
    "id": 123,
    "appointment_date": "2026-02-15",
    "appointment_time": "14:00:00",
    "status": "PENDING",
    "payment_status": "PENDING",
    "total_amount": 150.00,
    "tax_amount": 7.50,
    "subtotal": 142.50,
    "notes": "ملاحظات خاصة",
    "created_at": "2026-02-01T10:30:00.000000Z",
    "customer": {
      "id": 45,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone": "+971501234567"
    },
    "services": [
      {
        "id": 12,
        "name": "قص شعر",
        "description": "قص شعر احترافي",
        "price": 50.00,
        "duration": 30
      },
      {
        "id": 13,
        "name": "حلاقة ذقن",
        "description": "تشذيب وحلاقة ذقن",
        "price": 30.00,
        "duration": 15
      }
    ],
    "provider": {
      "id": 5,
      "name": "محمد الحلاق",
      "rating": 4.8,
      "avatar": "https://example.com/avatars/5.jpg"
    }
  }
}
```

---

## 7. Cancel Appointment

Cancel an existing appointment.

### Endpoint
```http
POST /api/appointments/{id}/cancel
```

### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | **Yes** | Appointment ID |

### Body Parameters

| Parameter | Type | Required | Max Length | Description |
|-----------|------|----------|------------|-------------|
| `reason` | string | No | 500 | Cancellation reason |

### Request Example (JavaScript/Axios)

```javascript
// Cancel appointment with ID 123
const response = await axios.post('/api/appointments/123/cancel',
  {
    reason: 'لدي ظرف طارئ'
  },
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  }
);
```

### Request Example (cURL)

```bash
curl -X POST "https://your-domain.com/api/appointments/123/cancel" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "reason": "لدي ظرف طارئ"
  }'
```

### Response Example (200 OK)

```json
{
  "success": true,
  "message": "تم إلغاء الحجز بنجاح",
  "data": {
    "id": 123,
    "appointment_date": "2026-02-15",
    "appointment_time": "14:00:00",
    "status": "USER_CANCELLED",
    "cancellation_reason": "لدي ظرف طارئ",
    "cancelled_at": "2026-02-01T15:45:00.000000Z"
  }
}
```

---

## Response Structure

### Success Response

All successful responses follow this structure:

```json
{
  "success": true,
  "message": "Success message in Arabic",
  "data": { /* Response data */ },
  "meta": { /* Optional pagination meta */ }
}
```

### Pagination Meta

For paginated endpoints:

```json
{
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

---

## Error Handling

### Error Response Structure

```json
{
  "success": false,
  "message": "Error message in Arabic",
  "error_type": "error_category",
  "error": "Detailed error (only in debug mode)"
}
```

### HTTP Status Codes

| Status Code | Description | When It Occurs |
|-------------|-------------|----------------|
| `200` | OK | Request successful |
| `401` | Unauthorized | Missing or invalid token |
| `403` | Forbidden | No permission to access resource |
| `404` | Not Found | Appointment not found |
| `422` | Unprocessable Entity | Validation error |
| `500` | Internal Server Error | Server error |

### Error Types

| Error Type | Description |
|------------|-------------|
| `validation_error` | Invalid input data |
| `access_error` | Permission denied |
| `not_found` | Resource not found |
| `business_error` | Business logic error (e.g., can't cancel completed appointment) |
| `server_error` | Internal server error |

### Error Examples

#### 401 - Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

#### 403 - Forbidden

```json
{
  "success": false,
  "message": "ليس لديك صلاحية للوصول إلى هذا الحجز",
  "error_type": "access_error"
}
```

#### 404 - Not Found

```json
{
  "success": false,
  "message": "الحجز المطلوب غير موجود",
  "error_type": "not_found"
}
```

#### 422 - Validation Error

```json
{
  "success": false,
  "message": "لا يمكن إلغاء حجز تم إكماله بالفعل",
  "error_type": "business_error"
}
```

---

## 🔍 Complete React/React Native Example

### Setup Axios Instance

```javascript
// api/axiosInstance.js
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

const API_BASE_URL = 'https://your-domain.com/api';

const axiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Add token to requests
axiosInstance.interceptors.request.use(
  async (config) => {
    const token = await AsyncStorage.getItem('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Handle errors
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized (e.g., redirect to login)
      console.log('Unauthorized - redirect to login');
    }
    return Promise.reject(error);
  }
);

export default axiosInstance;
```

### Appointments Service

```javascript
// services/appointmentsService.js
import axiosInstance from '../api/axiosInstance';

class AppointmentsService {

  // Get all appointments
  async getAppointments(filters = {}) {
    try {
      const response = await axiosInstance.get('/appointments', {
        params: filters
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }

  // Get statistics
  async getStatistics() {
    try {
      const response = await axiosInstance.get('/appointments/statistics');
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }

  // Get upcoming appointments
  async getUpcoming(days = 7) {
    try {
      const response = await axiosInstance.get('/appointments/upcoming', {
        params: { days }
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }

  // Get past appointments
  async getPast(limit = 10) {
    try {
      const response = await axiosInstance.get('/appointments/past', {
        params: { limit }
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }

  // Search appointments
  async search(query) {
    try {
      const response = await axiosInstance.get('/appointments/search', {
        params: { query }
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }

  // Get single appointment
  async getAppointment(id) {
    try {
      const response = await axiosInstance.get(`/appointments/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }

  // Cancel appointment
  async cancelAppointment(id, reason = '') {
    try {
      const response = await axiosInstance.post(`/appointments/${id}/cancel`, {
        reason
      });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  }
}

export default new AppointmentsService();
```

### React Component Example

```javascript
// components/AppointmentsList.jsx
import React, { useState, useEffect } from 'react';
import appointmentsService from '../services/appointmentsService';

function AppointmentsList() {
  const [appointments, setAppointments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [filters, setFilters] = useState({
    status: 'ALL',
    per_page: 20,
    sort_by: 'appointment_date',
    sort_direction: 'asc'
  });

  useEffect(() => {
    loadAppointments();
  }, [filters, currentPage]);

  const loadAppointments = async () => {
    try {
      setLoading(true);
      const response = await appointmentsService.getAppointments({
        ...filters,
        page: currentPage
      });
      setAppointments(response.data);
      setError(null);
    } catch (err) {
      setError(err.message || 'حدث خطأ أثناء تحميل الحجوزات');
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = async (appointmentId) => {
    if (!confirm('هل أنت متأكد من إلغاء الحجز؟')) return;

    try {
      await appointmentsService.cancelAppointment(appointmentId, 'تم الإلغاء من قبل المستخدم');
      loadAppointments(); // Reload list
      alert('تم إلغاء الحجز بنجاح');
    } catch (err) {
      alert(err.message || 'حدث خطأ أثناء إلغاء الحجز');
    }
  };

  if (loading) return <div>جاري التحميل...</div>;
  if (error) return <div>خطأ: {error}</div>;

  return (
    <div>
      <h2>حجوزاتي</h2>

      {/* Filters */}
      <select
        value={filters.status}
        onChange={(e) => setFilters({...filters, status: e.target.value})}
      >
        <option value="ALL">الكل</option>
        <option value="PENDING">قيد الانتظار</option>
        <option value="COMPLETED">مكتمل</option>
        <option value="USER_CANCELLED">ملغي</option>
      </select>

      {/* Appointments List */}
      <ul>
        {appointments.map(appointment => (
          <li key={appointment.id}>
            <div>
              <h3>الحجز #{appointment.id}</h3>
              <p>التاريخ: {appointment.appointment_date}</p>
              <p>الوقت: {appointment.appointment_time}</p>
              <p>الحالة: {appointment.status}</p>
              <p>المبلغ: {appointment.total_amount} AED</p>

              {appointment.status === 'PENDING' && (
                <button onClick={() => handleCancel(appointment.id)}>
                  إلغاء الحجز
                </button>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

export default AppointmentsList;
```

---

