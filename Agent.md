# Beauty Salon Management System — Agent Reference

> This document provides a complete technical overview of the system for AI agents. Read this file to fully understand the architecture, data flow, business logic, and every workflow path in the application.

---

## 1. System Overview

A **Laravel 12** application with a **Filament 4.0** admin panel for managing a beauty salon. The system handles the full lifecycle of salon operations: customers browse services, book appointments (with or without an account), pay on-site, and receive printed invoices compliant with German tax regulations.

### Tech Stack
| Layer | Technology |
|-------|-----------|
| Framework | Laravel 12 (PHP 8.2) |
| Admin Panel | Filament 4.0 |
| Database | PostgreSQL (Neon-backed, Replit built-in) |
| Auth | Laravel Sanctum (API tokens) + Spatie Permissions (roles) |
| Frontend Assets | Vite + TailwindCSS 4 |
| Tax Compliance | Fiskaly TSE (placeholder — future integration) |
| Notifications | OneSignal (push notifications) |
| Social Auth | Google OAuth |
| Multi-language | Custom translation system (Language, ServiceTranslation models) + Filament Language Switcher (en, ar, de) |

### Key Design Decisions
- **Prices are GROSS (tax-inclusive)**: All prices stored in the database include tax. Tax is extracted using reverse calculation.
- **bcmath for money**: All monetary calculations use PHP's `bcmath` extension to avoid floating-point precision errors.
- **Two-stage invoicing**: A Draft invoice is created at booking time (no invoice number). It is finalized to Paid status (with invoice number) only when the customer actually pays.
- **Guest booking supported**: Appointments can be created without a user account — only name, email, phone are required. The `customer_id` field is nullable.
- **`created_status` field**: Controls visibility in availability checks. `1` = confirmed/paid booking (blocks time slots), `0` = unconfirmed/abandoned (does not block time slots).
- **Single-branch currently, multi-branch ready**: The `Branch` model exists, `branch_id` is on User and SalonSetting, but the system currently operates as a single branch.

---

## 2. Directory Structure

```
app/
├── Console/                    # Artisan commands
├── Enum/                       # PHP 8.1 backed enums
│   ├── AppointmentStatus.php   # PENDING(0), COMPLETED(1), USER_CANCELLED(-1), ADMIN_CANCELLED(-2), NO_SHOW(-3)
│   ├── InvoiceStatus.php       # DRAFT(0), PENDING(1), PAID(2), PARTIALLY_PAID(3), CANCELLED(-1), REFUNDED(-2), OVERDUE(4)
│   ├── PaymentStatus.php       # PENDING(0), PAID_ONLINE(1), PAID_ONSTIE_CASH(2), PAID_ONSTIE_CARD(3), FAILED(4), REFUNDED(5), PARTIALLY_REFUNDED(6)
│   └── TemplateSectionType.php # Header, Body, Footer
├── Exceptions/
├── Filament/                   # Admin panel (see Section 8)
│   ├── Pages/                  # Custom Filament pages (schedule management)
│   ├── Resources/              # CRUD resources (Appointments, Providers, Services, etc.)
│   └── Widgets/
├── Helpers/
│   └── Main.php                # get_setting() global helper
├── Http/
│   ├── Controllers/
│   │   ├── Api/                # REST API controllers (mobile app)
│   │   │   ├── AuthController.php
│   │   │   ├── BookingController.php
│   │   │   ├── AvailabilityController.php
│   │   │   ├── ServicesController.php
│   │   │   ├── ProvidersController.php
│   │   │   ├── AppointmentController.php
│   │   │   ├── ProfileController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── DevicesController.php
│   │   │   ├── OtpController.php
│   │   │   └── SocialAuthController.php
│   │   ├── BookingController.php          # Web booking (Filament-authenticated)
│   │   ├── PrintController.php            # Invoice printing (web + API)
│   │   └── InvoiceTemplateController.php  # Template preview
│   ├── Middleware/
│   └── Requests/
├── Jobs/                       # Queue jobs
├── Livewire/                   # Livewire components (schedule managers)
├── Models/                     # See Section 3
├── Services/                   # See Section 4
│   ├── Fiskaly/                # German TSE integration (placeholder)
│   ├── InvoiceTemplate/        # Template line type registry
│   ├── Payments/               # Payment processing
│   └── Print/                  # Print management
bootstrap/
config/
database/
├── migrations/                 # 38 migration files
└── seeders/                    # See Section 9
resources/                      # Blade views, CSS, JS
routes/
├── web.php                     # Web routes (admin API, printing, auth callbacks)
├── api.php                     # REST API routes (mobile/frontend)
└── console.php
```

---

## 3. Data Models & Relationships

### 3.1 Core Models

#### `User`
The central user model. Serves three roles: **admin**, **provider** (stylist/barber), **customer**.

| Field | Type | Purpose |
|-------|------|---------|
| first_name, last_name | string | User identity |
| email, phone | string | Contact info |
| user_type | string | Role indicator |
| is_active | boolean | Active/inactive toggle |
| branch_id | FK → Branch | Which salon branch (providers) |
| google_id | string | Google OAuth ID |
| locale | string | Preferred language |
| avatar_url | string | Profile picture |

**Relationships:**
- `branch()` → BelongsTo Branch
- `scheduledWorks()` → HasMany ProviderScheduledWork
- `timeOffs()` → HasMany ProviderTimeOff
- `customerAppointments()` → HasMany Appointment (as customer)
- `appointmentsAsProvider()` → HasMany Appointment (as provider)
- `services()` → BelongsToMany Service (via `provider_service` pivot)
- `invoices()` → HasMany Invoice
- `devices()` → HasMany UserDevice (push notification tokens)
- `profile_image()` → MorphOne File

**Auth:** Implements `FilamentUser` (admin panel access requires 'admin' role), uses `HasApiTokens` (Sanctum), `HasRoles` (Spatie).

#### `Appointment`
The central booking record.

| Field | Type | Purpose |
|-------|------|---------|
| number | string | Unique ID (format: `APT-YYYYMMDD-XXXXXX`) |
| customer_id | FK → User (nullable) | Registered customer (null for guests) |
| provider_id | FK → User | Service provider |
| customer_name | string | Guest name (or registered customer name) |
| customer_email | string | Guest email |
| customer_phone | string | Guest phone |
| appointment_date | datetime | Date of appointment |
| start_time | datetime | Start datetime |
| end_time | datetime | End datetime |
| duration_minutes | integer | Total duration |
| subtotal | decimal(2) | Net amount (before tax) |
| tax_amount | decimal(2) | Tax portion |
| total_amount | decimal(2) | Gross amount (tax-inclusive) |
| status | AppointmentStatus (enum) | Booking status |
| payment_status | PaymentStatus (enum) | Payment state |
| payment_method | string | Payment method label |
| created_status | integer | 1=confirmed, 0=unconfirmed/abandoned |
| cancellation_reason | text | Reason for cancellation |
| cancelled_at | datetime | When cancelled |
| notes | text | Customer notes |

**Relationships:**
- `customer()` → BelongsTo User
- `provider()` → BelongsTo User
- `services()` → BelongsToMany Service (via `appointment_services` pivot with: service_name, duration_minutes, price, sequence_order)
- `services_record()` → HasMany AppointmentService
- `invoice()` → HasOne Invoice
- `payments()` → MorphMany Payment
- `reminders()` → HasMany AppointmentReminder

**Key Accessors:**
- `customer_name` → Returns registered customer's full_name, or guest customer_name, or 'Guest'
- `customer_email` → Returns registered customer's email, or guest email
- `has_customer_account` → Boolean: whether customer_id is set

**Boot Logic:** Auto-generates appointment number on creation. Cancels reminders on deletion.

#### `AppointmentService` (Pivot Model)
Tracks each individual service within a booking, preserving order.

| Field | Purpose |
|-------|---------|
| appointment_id | FK → Appointment |
| service_id | FK → Service |
| service_name | Snapshot of service name at booking time |
| duration_minutes | Duration of this specific service |
| price | Price at booking time |
| sequence_order | Order in the booking sequence (1, 2, 3...) |

**Boot Logic:** Auto-populates `service_name` from Service model if not provided.

#### `Service`
Salon service offerings (e.g., haircut, coloring, manicure).

| Field | Purpose |
|-------|---------|
| category_id | FK → ServiceCategory |
| name | Service name |
| description | Description |
| price | Base price (gross, tax-inclusive) |
| discount_price | Discounted price (optional) |
| duration_minutes | Service duration |
| is_active | Active toggle |
| is_featured | Featured on listings |
| sort_order | Display ordering |
| color_code | UI color |

**Relationships:**
- `category()` → BelongsTo ServiceCategory
- `providers()` → BelongsToMany User (via `provider_service` with: is_active, custom_price, custom_duration, notes)
- `activeProviders()` → Filtered providers (pivot is_active=true, user is_active=true)
- `translations()` → HasMany ServiceTranslation
- `image()` / `icon()` → MorphOne File
- `invoiceItems()` → MorphMany InvoiceItem

**Translation System:** Each service can have translations per language (ServiceTranslation model with language_id). `getNameIn($locale)` returns translated name.

#### `Invoice`
Financial document linked to an appointment.

| Field | Purpose |
|-------|---------|
| appointment_id | FK → Appointment |
| customer_id | FK → User (nullable) |
| invoice_number | Unique number (format: `INV-XXXX`, null for drafts) |
| subtotal | Net amount |
| tax_amount | Tax amount |
| tax_rate | Tax percentage (e.g., 19.00) |
| total_amount | Gross total |
| status | InvoiceStatus (enum) |
| notes | Notes |
| invoice_data | JSON — payment details, discount info, TSE data (future) |
| segnture | Text — TSE digital signature (future) |
| signature_missing_reason | Text — Why signature is missing |
| print_count | Integer — How many times printed |
| first_printed_at | datetime |
| last_printed_at | datetime |

**Relationships:**
- `appointment()` → BelongsTo Appointment
- `customer()` → BelongsTo User
- `items()` → HasMany InvoiceItem
- `payments()` → MorphMany Payment
- `printLogs()` → HasMany PrintLog

**Key Methods:**
- `generateInvoiceNumber()` → Uses DocumentNumberGenerator for sequential numbering
- `calculateTotals()` → Recalculates from items using bcmath
- `getTemplateOrDefault()` → Gets the default InvoiceTemplate for rendering
- `getCopyLabel()` → Returns "(COPY)" or "(COPY 2)" based on print count
- `incrementPrintCount()` → Tracks first/last print times

#### `InvoiceItem`
Individual line items on an invoice.

| Field | Purpose |
|-------|---------|
| invoice_id | FK → Invoice |
| description | Service name |
| quantity | Quantity (usually 1) |
| unit_price | Net unit price |
| tax_rate | Tax rate for this item |
| tax_amount | Tax for this item |
| total_amount | Gross total for this item |
| itemable_id/type | Polymorphic link to Service |

**Boot Logic:** Auto-calculates `total_amount` on save. Auto-recalculates parent Invoice totals on save/delete.

#### `Payment`
Payment records linked polymorphically to Invoices or Appointments.

| Field | Purpose |
|-------|---------|
| payment_method_id | FK → PaymentMethod |
| payment_number | Unique (format: `PAY-YYYYMMDD-XXXXXX`) |
| amount | Payment amount |
| subtotal | Net amount |
| tax_amount | Tax portion |
| status | PaymentStatus (enum) |
| type | full, partial, deposit, refund |
| paymentable_id/type | Polymorphic (Invoice or Appointment) |
| payment_metadata | JSON — gateway data, refund info |

### 3.2 Scheduling Models

#### `ProviderScheduledWork`
Weekly recurring work schedule for each provider.

| Field | Purpose |
|-------|---------|
| user_id | FK → User (provider) |
| day_of_week | 0=Sunday through 6=Saturday |
| start_time | Shift start (e.g., "09:00") |
| end_time | Shift end (e.g., "17:00") |
| is_work_day | Whether provider works this day |
| break_minutes | Break duration (currently unused in slot generation) |
| is_active | Active toggle |

**Static Utility Methods:**
- `shiftsOverlap()` → Detects time overlap between two shifts
- `findOverlaps()` → Finds all conflicts in a set of shifts
- `getWeeklySchedule($userId)` → Returns full week schedule grouped by day
- `timeToMinutes()` / `minutesToTime()` → Time conversion utilities

#### `ProviderTimeOff`
Provider absences — either full-day or hourly.

| Field | Purpose |
|-------|---------|
| user_id | FK → User |
| type | 0=TYPE_HOURLY, 1=TYPE_FULL_DAY |
| start_date | Start of time off |
| end_date | End of time off |
| start_time | Start time (hourly type only) |
| end_time | End time (hourly type only) |
| reason_id | FK → ReasonLeave |

#### `SalonSchedule`
Branch-level operating hours (salon open/close times per day of week).

### 3.3 Invoice Template Models

#### `InvoiceTemplate`
Customizable invoice layout template.

| Field | Purpose |
|-------|---------|
| name | Template name |
| is_default | Default template flag |
| is_active | Active toggle |
| language | Template language |
| paper_size | Paper size name |
| paper_width | Width in mm |
| font_family | CSS font family |
| font_size | Base font size |
| global_styles | JSON — colors, padding, borders |
| company_info | JSON — company name, address, phone, tax number, logo |
| static_body_html | Custom HTML body content |

**Boot Logic:** Auto-populates company_info from SalonSettings on creation. Ensures only one default template exists.

#### `TemplateLine`
Individual line/element within a template, organized by section.

| Field | Purpose |
|-------|---------|
| template_id | FK → InvoiceTemplate |
| section | header, body, or footer |
| type | Line type (from LineTypeRegistry config) |
| order | Display order within section |
| is_enabled | Show/hide toggle |
| properties | JSON — type-specific configuration |

### 3.4 Supporting Models

| Model | Purpose |
|-------|---------|
| `Branch` | Salon branch (name, address, phone, coordinates) |
| `ServiceCategory` | Service grouping (e.g., Hair, Nails, Skin) |
| `SalonSetting` | Key-value settings store (tax_rate, max_booking_days, etc.) |
| `Language` | Supported languages (code, name, is_default) |
| `PaymentMethod` | Available payment methods |
| `PrinterSetting` | Printer configuration for receipt printing |
| `PrintLog` | Log of every print operation |
| `UserDevice` | Push notification device tokens (OneSignal) |
| `Otp` | One-time passwords for email verification |
| `RefreshToken` | JWT refresh tokens |
| `File` | Polymorphic file storage (profile images, service images) |
| `AppointmentReminder` | Scheduled reminders for appointments |
| `ReasonLeave` | Leave/time-off reason catalog |
| `ServiceReview` | Customer reviews for services |
| `SamplePage` / `PageTranslation` | Static pages (privacy, terms) with translations |

---

## 4. Service Layer (Business Logic)

### 4.1 `BookingService`
**The primary booking orchestrator.** Creates appointments with multiple services.

**`createBooking(?User $customer, array $bookingData): Appointment`**

Flow:
1. Extract booking data (services, date, payment_method, notes, guest info)
2. Validate basic data via `BookingValidationService`
3. Validate daily booking limit (registered customers only)
4. Sort services by start_time
5. Validate and prepare each service (provider offers it, time slot available, no duplicates)
6. Calculate totals using bcmath (gross prices → extract net + tax per item → sum → reconcile rounding)
7. **DB Transaction:**
   - Create Appointment record
   - Create AppointmentService records (one per service, with sequence_order)
   - Create Draft Invoice via InvoiceService
   - Return loaded appointment

**Price Resolution:**
- Check `provider_service` pivot for `custom_price`
- Fall back to `service.price`
- Apply `discount_price` if lower than effective price

**Tax Calculation (in calculateTotals):**
- Get `tax_rate` from SalonSettings (e.g., "19")
- For each service: `net = gross / (1 + rate/100)`, `tax = gross - net`
- Round per line item to 2 decimals
- Sum all net and tax
- Reconcile: if `net + tax ≠ gross`, adjust tax by the difference

**`created_status` logic:**
- `payment_method == 'cash'` → `created_status = 1` (immediately confirmed)
- Other methods → `created_status = 0` (pending confirmation)

### 4.2 `BookingValidationService`
All booking validation rules, called by BookingService.

**Validations performed:**
1. `validateBasicData()` → At least 1 service, max `max_services_per_booking`, date not in past, date within `max_booking_days`, no duplicate service IDs
2. `validateProviderOffersService()` → Provider-service link exists in `provider_service` pivot, both provider and service are active
3. `validateSequentialTiming()` → Each service starts after the previous one ends (sequential booking)
4. `validateTimeSlotAvailability()` → **The most critical validation:**
   - Provider has a work schedule for that day of week (`provider_scheduled_works`)
   - Time slot falls within working hours
   - No full-day time off
   - No hourly time off conflicts
   - No conflicting appointments (checks `created_status = 1` only)
   - Time is not in the past
   - Meets minimum advance booking time (`book_buffer` setting, default 60 minutes)
5. `validateNoDuplicateBooking()` → No existing pending booking for same customer + time + services
6. `validateNoDuplicateBookingByPhone()` → Same check for guest customers using phone number
7. `validateDailyBookingLimit()` → Max bookings per customer per day

### 4.3 `ServiceAvailabilityService`
Calculates available time slots for the customer-facing booking interface.

**`getAvailableSlotsByDate(serviceId, date, branchId)`**
Returns all providers who offer the service with their available time slots for the given date.

**`getProviderAvailableSlotsByDate(serviceId, providerId, date)`**
Returns available slots for a specific provider on a specific date.

**`getAvailabilityCalendar(serviceId, providerId, startDate, endDate, branchId)`**
Returns a calendar view showing which dates have available slots (max 31 days).

**Slot Generation Algorithm (`generateTimeSlots`):**
1. Get provider's work schedule for the day (`ProviderScheduledWork`)
2. If no schedule or full-day time off → return empty
3. Start from shift start time
4. For today: skip past time slots, align to next service-duration boundary
5. Get existing appointments and hourly time offs
6. Iterate: for each potential slot (start → start + service_duration):
   - Check no overlap with existing appointments
   - Check no overlap with hourly time offs
   - If clear → add to available slots
7. Advance by `service_duration + SLOT_BUFFER` (buffer is currently 0)
8. Stop when remaining time < service_duration

**Conflict Detection (`hasConflict`):** Two periods overlap if `start1 < end2 AND start2 < end1`

**Caching:** Results cached for 1 minute per service+date+provider combination.

### 4.4 `InvoiceService`
Handles invoice creation, finalization, and tax calculation.

**`createDtaftInvoiceFromAppointment()`** — Creates a Draft invoice when booking is made:
- No invoice number (null)
- Status = DRAFT
- Copies subtotal, tax_amount, total_amount from appointment
- Creates InvoiceItems for each service (using TaxCalculatorService to extract net/tax from gross price)

**`createInvoiceFromAppointment()`** — Creates a finalized Paid invoice:
- Generates invoice number via `DocumentNumberGenerator`
- Calculates reverse tax from amount paid
- Supports adjusted duration
- Creates invoice items
- Updates appointment payment status
- Placeholder for TSE signature and German tax authority submission

**`finalizeDraftInvoice()`** — Converts Draft → Paid:
1. Validate invoice is in DRAFT status
2. Generate invoice number
3. Update status to PAID, store payment data in `invoice_data` JSON
4. Update appointment's payment_status and payment_method
5. Placeholder for TSE signature and payment record creation

**`calculateReverseTax()`** — Extracts tax from a gross amount:
- `subtotal = gross / (1 + rate/100)`
- `tax = gross - subtotal`

### 4.5 `TaxCalculatorService`
High-precision tax calculator using `bcmath`.

**`extractTax(grossAmount, taxRate, precision)`** — Reverse tax calculation:
- Input: gross amount (tax-inclusive), tax rate (e.g., 19)
- Output: `{ net, tax, gross }` as strings with requested precision
- Guarantees: `net + tax = gross` (reconciles rounding differences)

**`addTax(netAmount, taxRate, precision)`** — Forward tax calculation:
- Input: net amount, tax rate
- Output: `{ net, tax, gross }`

**`calculateBulk(items, precision)`** — Batch calculation for multiple items.

### 4.6 `AppointmentService` (Service class, not Model)
Customer-facing appointment management (used by API controllers).

- `getCustomerAppointments()` → Paginated, filtered appointment list
- `getAppointmentDetails()` → Single appointment with full relations (verifies ownership)
- `getAppointmentStatistics()` → Counts: total, pending, completed, cancelled, upcoming, total spent
- `cancelAppointment()` → Cancel if status is PENDING and start time is in future
- `getUpcomingAppointments()` → Next N days of pending appointments
- `getPastAppointments()` → Recent completed appointments
- `searchAppointments()` → Search by appointment number or provider name

### 4.7 `SettingsService`
Simple wrapper around `get_setting()` helper. Reads from `salon_settings` table.

**Key Settings:**
| Key | Default | Purpose |
|-----|---------|---------|
| tax_rate | 19 | Tax percentage (Germany: 19% VAT) |
| max_booking_days | 10 | How far in advance customers can book |
| max_services_per_booking | 10 | Maximum services per single booking |
| max_daily_bookings | 10 | Maximum bookings per customer per day |
| book_buffer | 60 | Minimum minutes before appointment start |
| company_name | — | Salon name for invoices |
| company_address | — | Salon address |
| company_phone | — | Salon phone |
| company_tax_number | — | Tax ID (Steuernummer) |

### 4.8 Other Services

| Service | Purpose |
|---------|---------|
| `AppointmentReminderService` | Schedule/cancel push notification reminders |
| `AuthTokenService` | Sanctum token management |
| `DocumentNumberGenerator` | Sequential document numbering (INV-0001, INV-0002...) |
| `InvoiceFinalizationService` | Additional invoice finalization logic |
| `NotificationService` | Push notification dispatch (OneSignal) |
| `OtpService` | OTP generation and verification for email/phone |
| `PageRenderService` | Static page rendering |
| `ProviderService` | Provider management operations |
| `TemplateExportImportService` | Import/export invoice templates |
| `InvoiceTemplate/LineTypeRegistry` | Registry of available invoice template line types |

---

## 5. API Routes & Endpoints

### 5.1 Public Endpoints (No Auth)

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | /api/services | ServicesController@index | List all active services |
| GET | /api/services/{id} | ServicesController@show | Service details |
| GET | /api/providers | ProvidersController@index | List providers |
| GET | /api/providers/{id} | ProvidersController@show | Provider details |
| GET | /api/availability/provider | AvailabilityController | Available slots for a provider/service/date |
| GET | /api/availability/calendar | AvailabilityController | Calendar view of availability |

### 5.2 Auth Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | /api/auth/register | User registration |
| POST | /api/auth/login | Login (returns Sanctum token) |
| POST | /api/auth/logout | Logout (revoke token) |
| POST | /api/auth/refresh | Refresh token |
| POST | /api/auth/forgot-password | Request password reset |
| POST | /api/auth/reset-password | Reset password |
| POST | /api/auth/google | Google OAuth login |
| POST | /api/auth/google/mobile | Google OAuth for mobile |
| POST | /api/auth/verify-email-otp | Verify email via OTP |
| POST | /api/auth/resend-verification-otp | Resend verification OTP |
| POST | /api/auth/request-otp | Request OTP |
| POST | /api/auth/verify-otp | Verify OTP |

### 5.3 Authenticated Endpoints (Sanctum)

| Method | Path | Purpose |
|--------|------|---------|
| GET | /api/profile | Get user profile |
| POST | /api/profile | Update profile |
| POST | /api/profile/change-password | Change password |
| GET | /api/appointments | List appointments (paginated, filtered) |
| GET | /api/appointments/statistics | Appointment statistics |
| GET | /api/appointments/upcoming | Upcoming appointments |
| GET | /api/appointments/past | Past appointments |
| GET | /api/appointments/search | Search appointments |
| GET | /api/appointments/{id} | Appointment details |
| POST | /api/appointments/{id}/cancel | Cancel appointment |
| GET | /api/bookings | Customer bookings list |
| POST | /api/bookings | Create new booking |
| GET | /api/bookings/{id} | Booking details |
| POST | /api/bookings/{id}/cancel | Cancel booking |
| POST | /api/register-device | Register push notification device |
| POST | /api/deregister-device | Unregister device |
| POST | /api/invoice/{id}/print | Print invoice (API) |
| POST | /api/invoices/print-batch | Batch print invoices |
| GET | /api/invoice/{id}/print-url | Get print URL |
| GET | /api/print/statistics | Print statistics |
| GET | /api/print/logs | Print logs |

### 5.4 Web Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | / | Welcome page |
| GET | /admin | Filament admin panel |
| GET | /admin/api/salon-schedules | Salon schedule API (auth required) |
| GET | /privacy | Privacy policy |
| GET | /terms | Terms of service |
| GET | /invoice-template/{id}/preview | Template preview |
| GET | /invoice/{id}/print | Print invoice (web, auth required) |

---

## 6. Complete Workflow Paths

### 6.1 Customer Booking Flow (API — Mobile/Web App)

```
Customer opens app
    │
    ├── Browse services (GET /api/services)
    │   └── View service details (GET /api/services/{id})
    │
    ├── Check availability (GET /api/availability/provider?service_id=X&date=Y)
    │   └── Calendar view (GET /api/availability/calendar?service_id=X&start_date=Y&end_date=Z)
    │
    ├── Select provider and time slot
    │
    ├── Login/Register OR continue as guest
    │   ├── Register (POST /api/auth/register) → verify email (POST /api/auth/verify-email-otp)
    │   ├── Login (POST /api/auth/login) → get Sanctum token
    │   └── Guest: provide name, email, phone
    │
    └── Create booking (POST /api/bookings)
        │
        │  Request body:
        │  {
        │    "date": "2026-03-01",
        │    "payment_method": "cash",
        │    "services": [
        │      { "service_id": 1, "provider_id": 5, "start_time": "10:00" },
        │      { "service_id": 3, "provider_id": 5, "start_time": "10:30" }
        │    ],
        │    "customer_name": "Jane Doe",       // (guest only)
        │    "customer_email": "jane@example.com", // (guest only)
        │    "customer_phone": "+491234567890",   // (guest only)
        │    "notes": "First time visit"
        │  }
        │
        ├── BookingService.createBooking()
        │   ├── BookingValidationService validates everything
        │   ├── Services sorted by start_time
        │   ├── Each service validated: provider offers it, slot available, no duplicates
        │   ├── Totals calculated (bcmath, gross → net + tax)
        │   ├── DB Transaction:
        │   │   ├── Create Appointment (status=PENDING, created_status=1 for cash)
        │   │   ├── Create AppointmentService records (one per service)
        │   │   └── Create Draft Invoice (status=DRAFT, no invoice_number)
        │   └── Return appointment with relations
        │
        └── Response: appointment object with services, provider, invoice
```

### 6.2 Payment & Invoice Finalization Flow (Admin Panel)

```
Customer arrives at salon
    │
    ├── Admin opens appointment in Filament
    │
    ├── Admin confirms services provided
    │   └── Optionally adjusts duration
    │
    ├── Admin processes payment
    │   ├── Select payment type: PAID_ONSTIE_CASH(2) or PAID_ONSTIE_CARD(3)
    │   ├── Enter amount paid (may differ from total for discounts)
    │   │
    │   └── InvoiceService.finalizeDraftInvoice() OR createInvoiceFromAppointment()
    │       │
    │       ├── Generate invoice number (INV-0001, sequential)
    │       ├── Update invoice status: DRAFT → PAID
    │       ├── Store payment data in invoice_data JSON:
    │       │   { finalized_at, payment_type, amount_paid, finalized_by }
    │       ├── Update appointment.payment_status
    │       ├── [FUTURE] Apply TSE digital signature
    │       └── [FUTURE] Submit to German tax authority
    │
    └── Print invoice
        ├── GET /invoice/{id}/print (web) or POST /api/invoice/{id}/print (API)
        ├── Load InvoiceTemplate (default template)
        ├── Render with template lines (header → body → footer)
        ├── Track print count, first/last print timestamps
        └── Show COPY label for reprints
```

### 6.3 Availability Calculation Flow

```
Request: GET /api/availability/provider?service_id=1&provider_id=5&date=2026-03-01
    │
    └── ServiceAvailabilityService.getProviderAvailableSlotsByDate()
        │
        ├── Load Service and Provider
        ├── Validate: date is not in past, provider offers service
        │
        └── getProviderAvailableSlots(provider, service, date)
            │
            ├── Get day_of_week (e.g., Sunday=0)
            ├── Query ProviderScheduledWork for that day
            │   └── If no schedule or not work day → return []
            │
            ├── Check full-day time off
            │   └── If has full-day off → return []
            │
            ├── Get service duration (from service.duration_minutes)
            │
            └── generateTimeSlots()
                │
                ├── Set window: shift start_time → end_time
                ├── If today: skip past slots, align to next slot boundary
                │
                ├── Load existing appointments (status=PENDING)
                ├── Load hourly time offs for this date
                │
                └── Loop: currentTime → currentTime + duration ≤ endTime
                    │
                    ├── Check overlap with appointments → skip if conflict
                    ├── Check overlap with hourly time offs → skip if conflict
                    ├── If no conflict → add slot:
                    │   { start_time: "10:00", end_time: "10:30",
                    │     start_time_formatted: "10:00 AM", ... }
                    │
                    └── Advance by (duration + buffer)
```

### 6.4 Admin Panel CRUD Operations

The Filament admin panel provides full CRUD for:

| Resource | Path | Operations |
|----------|------|------------|
| Appointments | /admin/appointments | View, create, manage, cancel appointments |
| Providers | /admin/providers | Manage providers, their services, schedules, time offs |
| Services | /admin/services | CRUD services, categories, translations, pricing |
| Service Categories | /admin/service-categories | CRUD categories |
| Users | /admin/users | User management |
| Salon Settings | /admin/salon-settings | Key-value settings |
| Invoice Templates | /admin/invoice-templates | Template design with line builder |
| Languages | /admin/languages | Manage supported languages |
| Reason Leaves | /admin/reason-leaves | Leave reason catalog |
| Printer Settings | /admin/printer-settings | Printer configuration |
| Print Logs | /admin/print-logs | Print history |
| Pages | /admin/pages | Static page management |

**Custom Filament Pages:**
- `ManageProviderSchedules` — Visual weekly schedule management
- `ManageProviderLeaves` — Time off management
- `ManageSalonSchedules` — Salon operating hours
- `ViewProviderScheduleTimeline` — Timeline view of provider schedules

### 6.5 Invoice Printing Flow

```
Admin clicks "Print" on invoice
    │
    ├── PrintController.print() (web) or apiPrint() (API)
    │
    ├── Load Invoice with: appointment, customer, items
    ├── Get default InvoiceTemplate
    │
    ├── Load template lines organized by section:
    │   ├── Header lines (company logo, name, address, tax number)
    │   ├── Body lines (invoice number, date, items table, totals, tax breakdown)
    │   └── Footer lines (thank you message, legal notices)
    │
    ├── Each TemplateLine has:
    │   ├── type → determines which Blade partial to render
    │   ├── properties → configuration for that type
    │   └── is_enabled → whether to show
    │
    ├── Render Blade view with template data
    ├── Invoice.incrementPrintCount()
    ├── Create PrintLog record
    │
    └── Return HTML for printing (browser print dialog or API response)
```

### 6.6 Authentication Flow

```
Mobile App Authentication:
    │
    ├── Email/Password Registration
    │   ├── POST /api/auth/register { first_name, last_name, email, password, phone }
    │   ├── Create User with 'customer' role
    │   ├── Send OTP to email
    │   └── POST /api/auth/verify-email-otp { email, otp }
    │
    ├── Login
    │   ├── POST /api/auth/login { email, password }
    │   ├── Validate credentials
    │   ├── Generate Sanctum token
    │   └── Return { token, user }
    │
    ├── Google OAuth
    │   ├── POST /api/auth/google/mobile { id_token }
    │   ├── Verify Google token
    │   ├── Find or create user by google_id
    │   └── Return { token, user }
    │
    └── Token Refresh
        ├── POST /api/auth/refresh { refresh_token }
        ├── Validate refresh token (RefreshToken model)
        ├── Generate new Sanctum token
        └── Return { token }

Admin Panel Authentication:
    │
    ├── Filament login page (/admin/login)
    ├── User must have 'admin' role (canAccessPanel check)
    └── Standard Laravel session authentication
```

---

## 7. Database Seeders

The system ships with comprehensive seeders for development:

| Seeder | What it creates |
|--------|----------------|
| LanguageSeeder | en (default), ar, de |
| BranchSeeder | Main salon branch |
| SalonSettingSeeder | All default settings (tax_rate=19, etc.) |
| RoleSeeder | admin, provider, customer roles |
| UserSeeder | Admin user + test providers + test customers |
| ServiceCategorySeeder | Hair, Nails, Skin, etc. |
| ServiceSeeder | Sample services with prices and durations |
| ProviderServiceSeeder | Links providers to services |
| ProviderScheduledWorkSeeder | Weekly schedules for providers |
| ProviderTimeOffSeeder | Sample time offs |
| SalonScheduleSeeder | Salon operating hours |
| AppointmentSeeder | Sample appointments with services |
| PaymentMethodSeeder | Cash, Card, Online |
| PrinterSeeder | Default printer configuration |
| InvoiceTemplateSeeder | Default invoice template with lines |
| ReasonLeaveSeeder | Leave reason catalog |
| StaticPagesSeeder | Privacy and Terms pages |

**Run order:** Defined in `DatabaseSeeder.php`. Run with `php artisan db:seed`.

---

## 8. Pivot Tables & Many-to-Many Relationships

| Pivot Table | Connects | Extra Columns |
|-------------|----------|---------------|
| `provider_service` | User ↔ Service | is_active, custom_price, custom_duration, notes |
| `appointment_services` | Appointment ↔ Service | service_name, duration_minutes, price, sequence_order |

---

## 9. Enums Reference

### AppointmentStatus (int-backed)
| Value | Name | Meaning |
|-------|------|---------|
| 0 | PENDING | Awaiting service delivery |
| 1 | COMPLETED | Service delivered |
| -1 | USER_CANCELLED | Customer cancelled |
| -2 | ADMIN_CANCELLED | Admin cancelled |
| -3 | NO_SHOW | Customer didn't show up |

### InvoiceStatus (int-backed)
| Value | Name | Meaning |
|-------|------|---------|
| 0 | DRAFT | Created at booking, no invoice number |
| 1 | PENDING | Awaiting payment |
| 2 | PAID | Payment received, invoice number assigned |
| 3 | PARTIALLY_PAID | Partial payment received |
| -1 | CANCELLED | Invoice cancelled |
| -2 | REFUNDED | Payment refunded |
| 4 | OVERDUE | Payment overdue |

### PaymentStatus (int-backed)
| Value | Name | Meaning |
|-------|------|---------|
| 0 | PENDING | Awaiting payment |
| 1 | PAID_ONLINE | Paid via online gateway |
| 2 | PAID_ONSTIE_CASH | Paid cash at salon |
| 3 | PAID_ONSTIE_CARD | Paid card at salon |
| 4 | FAILED | Payment failed |
| 5 | REFUNDED | Fully refunded |
| 6 | PARTIALLY_REFUNDED | Partially refunded |

---

## 10. Key Configuration & Settings

### Environment Variables
- `DATABASE_URL` — PostgreSQL connection string
- `APP_KEY` — Laravel encryption key
- `APP_URL` — Application URL
- Fiskaly: `FISKALY_API_KEY`, `FISKALY_API_SECRET`, `FISKALY_TSS_ID` (future TSE integration)

### SalonSettings (database-stored)
Retrieved via `get_setting('key', 'default')` or `SettingsService::get('key', 'default')`.

Key settings that control business logic:
- `tax_rate` — VAT percentage (default: 19 for Germany)
- `max_booking_days` — Maximum days in advance for booking
- `max_services_per_booking` — Maximum services per booking
- `max_daily_bookings` — Maximum bookings per customer per day
- `book_buffer` — Minimum minutes before appointment start time
- `company_name`, `company_address`, `company_phone`, `company_tax_number` — Company info for invoices

---

## 11. Future Integrations (Placeholders)

### Fiskaly TSE (Technical Security Environment)
German law requires digital cash registers to use a TSE for tamper-proof transaction recording. The system has placeholder methods:
- `InvoiceService::signInvoiceWithTSE()` — Will connect to Fiskaly Cloud TSE, obtain digital signature, store transaction number and certified timestamp
- `InvoiceService::submitToGermanTaxAuthority()` — Will submit to ELSTER/DATEV in XRechnung/ZUGFeRD format
- `Invoice.segnture` field ready for signature storage
- `Invoice.invoice_data` JSON ready for TSE metadata

### Multi-Branch
- `Branch` model exists with name, address, coordinates
- `branch_id` on User and SalonSetting
- Currently operates as single branch

---

## 12. Important Implementation Notes

1. **Price Storage**: All prices in the database are GROSS (tax-inclusive). Tax is always extracted using reverse calculation, never added.

2. **bcmath Usage**: The system uses `bcmath` for all monetary calculations. Never use PHP float arithmetic for money. The `TaxCalculatorService` and `BookingService.calculateTotals()` demonstrate the correct patterns.

3. **Rounding Reconciliation**: After per-item rounding, the system checks that `net + tax = gross` and adjusts the tax amount by the rounding difference to maintain exact equality.

4. **Guest Booking**: When `customer_id` is null, the system uses `customer_name`, `customer_email`, `customer_phone` fields directly on the Appointment model. All customer accessors handle both cases.

5. **created_status Field**: This is crucial for availability. Only appointments with `created_status = 1` block time slots. This prevents abandoned/unpaid bookings from consuming availability. A TODO comment mentions creating a job to clean up `created_status = 0` records.

6. **Invoice Lifecycle**: `DRAFT (booking) → PAID (payment)`. Draft invoices have no invoice number. The number is generated only upon finalization, ensuring sequential numbering without gaps from cancelled bookings.

7. **Appointment Number Format**: `APT-YYYYMMDD-XXXXXX` (6 random hex chars). Invoice number format: `INV-XXXX` (sequential via DocumentNumberGenerator).

8. **InvoiceItem Observers**: The `InvoiceItem` model has boot observers that auto-calculate totals on save and cascade to parent Invoice. When creating items in bulk (like from a booking), `InvoiceItem::withoutEvents()` is used to prevent redundant recalculations.

9. **Service Duration**: Currently uses `service.duration_minutes` directly (the custom duration from provider_service pivot is commented out / unreachable code in both BookingService and ServiceAvailabilityService).

10. **Spatie Roles**: Three roles: `admin`, `provider`, `customer`. Admin role is required for Filament panel access. Provider is not checked in Filament separately (relies on panel-level canAccessPanel which checks 'admin' role).

11. **Multi-language**: Three languages supported (en, ar, de). Services have translations via ServiceTranslation model. The Filament panel supports language switching via FilamentLanguageSwitcherPlugin.
