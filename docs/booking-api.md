# Booking API Documentation

> **Audience:** Frontend Developers  
> **Base URL:** `/api`  
> **Auth:** All booking endpoints require a Bearer token (`Authorization: Bearer {token}`) and a **verified** email account.

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication & Headers](#authentication--headers)
3. [Create Booking — Single Service](#1-create-booking--single-service)
4. [Create Booking — Multiple Services](#2-create-booking--multiple-services)
5. [List My Bookings](#3-list-my-bookings)
6. [Get Booking Details](#4-get-booking-details)
7. [Cancel Booking](#5-cancel-booking)
8. [Response Schema Reference](#response-schema-reference)
9. [Error Responses](#error-responses)
10. [Business Rules & Constraints](#business-rules--constraints)
11. [Status & Payment Enums](#status--payment-enums)

---

## Overview

The booking system allows a customer to book **one or more services** in a single appointment. All services in one booking share the same **date**, but each service has its own **provider** and **start_time**.

When multiple services are booked together:
- Services are automatically sorted by their `start_time`
- Each service must start **after** the previous one ends (no overlaps)
- The appointment `start_time` = first service start, `end_time` = last service end
- Total duration = sum of all service durations
- Total price = sum of all service prices (with tax applied per-item)

---

## Authentication & Headers

```http
Authorization: Bearer {your_token}
Content-Type: application/json
Accept: application/json
```

> **Important:** The user's email must be verified. If not verified, all booking endpoints return `403 Forbidden`.

---

## 1. Create Booking — Single Service

Book one service with one provider at a specific time.

### Request

```
POST /api/bookings
```

```json
{
  "date": "2026-04-10",
  "payment_method": "cash",
  "notes": "Please use scissors, not a razor.",
  "services": [
    {
      "service_id": 3,
      "provider_id": 7,
      "start_time": "10:00"
    }
  ]
}
```

### Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | `string` | ✅ Yes | Appointment date in `YYYY-MM-DD` format. Must be today or future. |
| `payment_method` | `string` | ✅ Yes | `"cash"` or `"online"` |
| `notes` | `string` | ❌ No | Extra notes for the barber. Max 1000 characters. |
| `services` | `array` | ✅ Yes | Array of service objects. Min 1, Max 10. |
| `services[].service_id` | `integer` | ✅ Yes | ID of the service to book. |
| `services[].provider_id` | `integer` | ✅ Yes | ID of the provider (barber) who will perform the service. |
| `services[].start_time` | `string` | ✅ Yes | Time in `HH:MM` format (24-hour). Example: `"09:30"` |

### Success Response — `201 Created`

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 42,
    "number": "APT-20260410-A3F9C1",

    "appointment_date": "2026-04-10",
    "formatted_date": "Friday, April 10, 2026",
    "start_time": "10:00",
    "end_time": "10:30",
    "time_range": "10:00 - 10:30",
    "duration_minutes": 30,
    "formatted_duration": "30 minutes",

    "subtotal": 45.38,
    "tax_amount": 6.62,
    "total_amount": 52.00,

    "status": "PENDING",
    "status_value": 0,
    "status_label": "Pending",
    "payment_status": "PENDING",
    "payment_status_value": 0,
    "payment_status_label": "Pending",
    "payment_method": "cash",

    "cancellation_reason": null,
    "cancelled_at": null,

    "provider": {
      "id": 7,
      "full_name": "Ahmed Al-Barber",
      "email": "ahmed@example.com",
      "phone": "+966500000001",
      "avatar_url": "https://example.com/avatars/ahmed.jpg",
      "profile_image_url": "https://example.com/profiles/ahmed.jpg"
    },

    "services_details": [
      {
        "id": 101,
        "service_id": 3,
        "service_name": "Classic Haircut",
        "duration_minutes": 30,
        "formatted_duration": "30 minutes",
        "price": 52.00,
        "formatted_price": "SAR 52.00",
        "sequence_order": 1,
        "service": {
          "id": 3,
          "name": "Classic Haircut",
          "image_url": "https://example.com/services/haircut.jpg",
          "color_code": "#4A90D9"
        }
      }
    ],

    "notes": "Please use scissors, not a razor.",
    "created_at": "2026-04-05 12:00:00",
    "updated_at": "2026-04-05 12:00:00",

    "is_upcoming": true,
    "is_past": false,
    "is_cancelled": false,
    "is_completed": false,
    "can_cancel": true
  }
}
```

---

## 2. Create Booking — Multiple Services

Book two or more services in a single appointment. Each service can have a different provider, but must be sequential (no time overlaps).

### Request

```
POST /api/bookings
```

```json
{
  "date": "2026-04-10",
  "payment_method": "cash",
  "notes": "Birthday session, please be careful.",
  "services": [
    {
      "service_id": 3,
      "provider_id": 7,
      "start_time": "10:00"
    },
    {
      "service_id": 5,
      "provider_id": 7,
      "start_time": "10:30"
    },
    {
      "service_id": 8,
      "provider_id": 9,
      "start_time": "11:00"
    }
  ]
}
```

> **Rule:** Each service's `start_time` must be **after or equal** to the previous service's calculated end time.  
> The system calculates end time as: `start_time + service_duration_minutes`.  
> If service #1 starts at `10:00` and takes 30 min, service #2 must start at `10:30` or later.

### Success Response — `201 Created`

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 43,
    "number": "APT-20260410-B7D2E5",

    "appointment_date": "2026-04-10",
    "formatted_date": "Friday, April 10, 2026",
    "start_time": "10:00",
    "end_time": "11:45",
    "time_range": "10:00 - 11:45",
    "duration_minutes": 105,
    "formatted_duration": "1 hour 45 minutes",

    "subtotal": 119.27,
    "tax_amount": 17.73,
    "total_amount": 137.00,

    "status": "PENDING",
    "status_value": 0,
    "status_label": "Pending",
    "payment_status": "PENDING",
    "payment_status_value": 0,
    "payment_status_label": "Pending",
    "payment_method": "cash",

    "cancellation_reason": null,
    "cancelled_at": null,

    "provider": {
      "id": 7,
      "full_name": "Ahmed Al-Barber",
      "email": "ahmed@example.com",
      "phone": "+966500000001",
      "avatar_url": "https://example.com/avatars/ahmed.jpg",
      "profile_image_url": "https://example.com/profiles/ahmed.jpg"
    },

    "services_details": [
      {
        "id": 201,
        "service_id": 3,
        "service_name": "Classic Haircut",
        "duration_minutes": 30,
        "formatted_duration": "30 minutes",
        "price": 52.00,
        "formatted_price": "SAR 52.00",
        "sequence_order": 1,
        "service": {
          "id": 3,
          "name": "Classic Haircut",
          "image_url": "https://example.com/services/haircut.jpg",
          "color_code": "#4A90D9"
        }
      },
      {
        "id": 202,
        "service_id": 5,
        "service_name": "Beard Trim",
        "duration_minutes": 30,
        "formatted_duration": "30 minutes",
        "price": 35.00,
        "formatted_price": "SAR 35.00",
        "sequence_order": 2,
        "service": {
          "id": 5,
          "name": "Beard Trim",
          "image_url": "https://example.com/services/beard.jpg",
          "color_code": "#E67E22"
        }
      },
      {
        "id": 203,
        "service_id": 8,
        "service_name": "Hair Coloring",
        "duration_minutes": 45,
        "formatted_duration": "45 minutes",
        "price": 50.00,
        "formatted_price": "SAR 50.00",
        "sequence_order": 3,
        "service": {
          "id": 8,
          "name": "Hair Coloring",
          "image_url": "https://example.com/services/color.jpg",
          "color_code": "#9B59B6"
        }
      }
    ],

    "notes": "Birthday session, please be careful.",
    "created_at": "2026-04-05 12:00:00",
    "updated_at": "2026-04-05 12:00:00",

    "is_upcoming": true,
    "is_past": false,
    "is_cancelled": false,
    "is_completed": false,
    "can_cancel": true
  }
}
```

---

## 3. List My Bookings

Returns all bookings for the authenticated customer.

### Request

```
GET /api/bookings
GET /api/bookings?status=0       ← pending only
GET /api/bookings?status=1       ← completed only
GET /api/bookings?status=-1      ← cancelled by customer
GET /api/bookings?status=-2      ← cancelled by admin
```

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | `integer` | ❌ No | Filter by status value. See [Status Enums](#status--payment-enums). |

### Success Response — `200 OK`

```json
{
  "success": true,
  "message": "Bookings retrieved successfully",
  "data": [
    { /* AppointmentResource object */ },
    { /* AppointmentResource object */ }
  ]
}
```

> Results are ordered by `appointment_date DESC`, then `start_time DESC` (newest first).

---

## 4. Get Booking Details

Fetch full details of a single booking. Only the owner can access their own bookings.

### Request

```
GET /api/bookings/{id}
```

### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | `integer` | The booking ID. |

### Success Response — `200 OK`

```json
{
  "success": true,
  "message": "Booking details retrieved successfully",
  "data": {
    /* Full AppointmentResource object */
  }
}
```

### Error — Booking Not Yours — `403 Forbidden`

```json
{
  "success": false,
  "message": "Unauthorized access to this appointment",
  "error_type": "authorization_error"
}
```

---

## 5. Cancel Booking

Cancel a pending booking. Only bookings with `status = 0 (PENDING)` can be cancelled.

### Request

```
POST /api/bookings/{id}/cancel
```

```json
{
  "cancellation_reason": "Plans changed"
}
```

### Body Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `cancellation_reason` | `string` | ❌ No | Optional reason for cancellation. |

### Success Response — `200 OK`

```json
{
  "success": true,
  "message": "Booking cancelled successfully",
  "data": {
    "id": 42,
    "status": "USER_CANCELLED",
    "status_value": -1,
    "status_label": "Cancelled",
    "cancellation_reason": "Plans changed",
    "cancelled_at": "2026-04-05 14:30:00",
    "is_cancelled": true,
    "can_cancel": false
    /* ...rest of AppointmentResource */
  }
}
```

### Error — Cannot Cancel — `422 Unprocessable`

```json
{
  "success": false,
  "message": "Only pending appointments can be cancelled",
  "error_type": "validation_error"
}
```

---

## Response Schema Reference

### AppointmentResource (Full Object)

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Unique booking ID |
| `number` | `string` | Human-readable booking number e.g. `APT-20260410-A3F9C1` |
| `appointment_date` | `string` | Date in `YYYY-MM-DD` |
| `formatted_date` | `string` | Human-readable date e.g. `"Friday, April 10, 2026"` |
| `start_time` | `string` | Time in `HH:MM` |
| `end_time` | `string` | Time in `HH:MM` |
| `time_range` | `string` | Combined e.g. `"10:00 - 11:45"` |
| `duration_minutes` | `integer` | Total duration in minutes |
| `formatted_duration` | `string` | Human-readable duration |
| `subtotal` | `float` | Price before tax |
| `tax_amount` | `float` | Tax amount |
| `total_amount` | `float` | Final price (subtotal + tax) |
| `status` | `string` | Status name: `PENDING`, `COMPLETED`, `USER_CANCELLED`, `ADMIN_CANCELLED` |
| `status_value` | `integer` | Status numeric value. See [Enums](#status--payment-enums). |
| `status_label` | `string` | Localized status label |
| `payment_status` | `string` | Payment status name |
| `payment_status_value` | `integer` | Payment status numeric value |
| `payment_status_label` | `string` | Localized payment status label |
| `payment_method` | `string` | `"cash"` or `"online"` |
| `cancellation_reason` | `string\|null` | Reason for cancellation if cancelled |
| `cancelled_at` | `string\|null` | Cancellation datetime in `YYYY-MM-DD HH:MM:SS` |
| `provider` | `object` | Provider details (see below) |
| `services_details` | `array` | Array of service line items (see below) |
| `notes` | `string\|null` | Customer notes |
| `created_at` | `string` | Datetime in `YYYY-MM-DD HH:MM:SS` |
| `updated_at` | `string` | Datetime in `YYYY-MM-DD HH:MM:SS` |
| `is_upcoming` | `boolean` | `true` if appointment is in the future |
| `is_past` | `boolean` | `true` if appointment is in the past |
| `is_cancelled` | `boolean` | `true` if status is -1 or -2 |
| `is_completed` | `boolean` | `true` if status is 1 |
| `can_cancel` | `boolean` | `true` if status is PENDING and appointment is upcoming |

### provider Object

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Provider ID |
| `full_name` | `string` | Full name |
| `email` | `string` | Email address |
| `phone` | `string` | Phone number |
| `avatar_url` | `string\|null` | Avatar image URL |
| `profile_image_url` | `string\|null` | Profile image URL |

### services_details Item

| Field | Type | Description |
|-------|------|-------------|
| `id` | `integer` | Record ID (appointment_service row) |
| `service_id` | `integer` | Original service ID |
| `service_name` | `string` | Service name at time of booking |
| `duration_minutes` | `integer` | Duration in minutes |
| `formatted_duration` | `string` | Human-readable duration |
| `price` | `float` | Price of this service |
| `formatted_price` | `string` | Formatted price with currency |
| `sequence_order` | `integer` | Order of this service in the booking (1, 2, 3...) |
| `service.id` | `integer` | Service ID |
| `service.name` | `string` | Service current name |
| `service.image_url` | `string\|null` | Service image |
| `service.color_code` | `string\|null` | Hex color for UI theming |

---

## Error Responses

### Validation Error — `422 Unprocessable`

Triggered when the request body has invalid data (form validation).

```json
{
  "success": false,
  "message": "بيانات غير صحيحة",
  "errors": {
    "date": ["تاريخ الحجز مطلوب"],
    "services.0.start_time": ["وقت البدء يجب أن يكون بصيغة HH:MM"]
  },
  "error_type": "validation_error"
}
```

### Business Rule Error — `422 Unprocessable`

Triggered when data passes format validation but fails a business rule.

```json
{
  "success": false,
  "message": "Time slot 10:00 - 10:30 is already booked for provider 'Ahmed Al-Barber'",
  "error_type": "validation_error"
}
```

Possible business rule error messages:

| Situation | Example Message |
|-----------|-----------------|
| Past date | `"Cannot book in the past"` |
| Too far in advance | `"Cannot book more than 10 days in advance"` |
| Too many services | `"Maximum 10 services per booking"` |
| Duplicate service IDs | `"Duplicate services are not allowed in the same booking"` |
| Daily limit hit | `"Maximum 10 bookings per day reached"` |
| Provider doesn't offer service | `"Provider 'Ahmed' does not offer service 'Classic Haircut'"` |
| Provider inactive | `"Provider 'Ahmed' is not active"` |
| Service inactive | `"Service 'Classic Haircut' is not active"` |
| Outside working hours | `"Time slot is outside provider's working hours (09:00 - 18:00)"` |
| Provider day off | `"Provider 'Ahmed' does not work on Friday"` |
| Provider on leave | `"Provider 'Ahmed' is not available on 2026-04-10"` |
| Slot conflict | `"Time slot 10:00 - 10:30 is already booked for provider 'Ahmed'"` |
| Services overlap | `"Service at position 2 start time (10:15) must be after previous service end time (10:30)"` |
| Too soon to book | `"Booking must be at least 60 minutes in advance"` |
| Duplicate booking | `"You already have a booking for the same time and services"` |
| Not owner | `"Unauthorized access to this appointment"` |
| Cannot cancel completed | `"Only pending appointments can be cancelled"` |

### Authorization Error — `403 Forbidden`

```json
{
  "success": false,
  "message": "Unauthorized access to this appointment",
  "error_type": "authorization_error"
}
```

### Server Error — `500 Internal Server Error`

```json
{
  "success": false,
  "message": "An error occurred while creating the booking",
  "error_type": "server_error"
}
```

---

## Business Rules & Constraints

### Timing Rules

- `date` must be **today or future** (format: `YYYY-MM-DD`)
- `start_time` must be in **24-hour `HH:MM`** format
- Each service's start time must be **≥ previous service's end time** (sequential, no overlap)
- The system calculates `end_time` automatically: `start_time + service.duration_minutes`
- Bookings must be placed at least **N minutes** in advance (default: 60 minutes, controlled by system settings)
- Cannot book more than **N days** in advance (default: 10 days, controlled by system settings)

### Service Rules

- Minimum **1** service per booking
- Maximum **10** services per booking
- You **cannot repeat** the same service twice in one booking
- Each service must be **active** and offered by the selected provider
- The provider must **work on the selected day** and the time must fall within their working hours
- The provider must **not have time-off** during the requested slot
- The slot must **not conflict** with an existing confirmed appointment for that provider

### Payment Rules

| `payment_method` | Behaviour |
|-----------------|-----------|
| `"cash"` | Booking is immediately **confirmed** (`created_status = 1`, payment_status = `PAID_ONSITE_CASH`) |
| `"online"` | Booking is **pending payment** (`created_status = 0`, payment_status = `PENDING`) |

### Pricing & Tax

- Service price is resolved in this order: **provider custom price → service discount price → service regular price**
- Tax is calculated **per service** (reverse tax — price is treated as tax-inclusive)
- Tax rate is configured in system settings

---

## Status & Payment Enums

### Appointment Status (`status_value`)

| Value | Name | Description |
|-------|------|-------------|
| `0` | `PENDING` | Booking confirmed, awaiting the appointment |
| `1` | `COMPLETED` | Appointment was completed |
| `-1` | `USER_CANCELLED` | Cancelled by the customer |
| `-2` | `ADMIN_CANCELLED` | Cancelled by the admin |

### Payment Status (`payment_status_value`)

| Value | Name | Description |
|-------|------|-------------|
| `0` | `PENDING` | Payment not yet made (online booking) |
| `1` | `PAID_ONSITE_CASH` | Will pay in cash on arrival |
| `2` | `PAID_ONLINE` | Paid online successfully |
| `-1` | `FAILED` | Online payment failed |
| `-2` | `REFUNDED` | Payment was refunded |

---

## Quick Reference — Endpoint Summary

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `POST` | `/api/bookings` | Create a booking (1 or more services) | ✅ Required + Verified |
| `GET` | `/api/bookings` | List my bookings | ✅ Required + Verified |
| `GET` | `/api/bookings/{id}` | Get booking details | ✅ Required + Verified |
| `POST` | `/api/bookings/{id}/cancel` | Cancel a booking | ✅ Required + Verified |
