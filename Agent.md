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
| Tax Compliance | Fiskaly/TSE support code exists but is **deliberately OFF** for the current non-production phase; payment makes no Fiskaly call, and later enablement requires a reviewed integration project, not only an env change |
| Notifications | OneSignal (push) + Mail + SMS — all three gated per-customer, see note 21 |
| Social Auth | Google OAuth |
| Multi-language | Custom translation system (Language, ServiceTranslation models) + Filament Language Switcher (en, ar, de) |

### Key Design Decisions
- **Prices are GROSS (tax-inclusive)**: All prices stored in the database include tax. Tax is extracted using reverse calculation.
- **bcmath for money**: All monetary calculations use PHP's `bcmath` extension to avoid floating-point precision errors.
- **Two-stage invoicing**: A Draft invoice is created at booking time (no invoice number). It is finalized to Paid status (with invoice number) only when the customer actually pays.
- **Guest booking supported**: Appointments can be created without a user account — only name, email, phone are required. The `customer_id` field is nullable.
- **`created_status` field**: Controls whether a booking occupies the provider's time. `1` = confirmed (blocks the slot), `0` = unconfirmed (blocks nothing). There is no online payment — every booking is settled in cash at the shop — so **every booking is created with `created_status = 1`** and the column defaults to `1`. It is read in exactly one place, `Appointment::scopeBlocksProviderTime()`, and stays in the schema as the flag a future deposit / online-payment flow would flip.
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
│   ├── Fiskaly/                # Dormant German TSE support; not connected to current payments
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
- Delegates to `TaxCalculatorService::calculateBulk()` — **the single tax implementation in the project**
- Per line item: `net = round(gross / (1 + rate/100))`, `tax = gross - net`, rounded to 2 decimals
- Sum all net and tax, then reconcile so `net + tax = gross` exactly (the difference always adjusts TAX)
- This method used to carry its own 40-line bcmath copy. It was the *correct* one while
  `TaxCalculatorService` truncated at scale 2, so `appointments` and `invoices` disagreed by a cent
  on the same transaction (MON-01, fixed 2026-09-10)

**Concurrency (BOOK-02):** the conflict check and the write happen inside ONE transaction, and the transaction opens by locking the rows of every provider involved plus the customer (`BookingLockService::lockUsers()` — `SELECT … FOR UPDATE` on `users`, ordered by id so concurrent bookings can never deadlock). Locking the provider's *user* row rather than the appointments is deliberate: the state being defended is the ABSENCE of a booking, and you cannot lock rows that do not exist. Any new code path that writes an appointment's provider/date/time MUST take the same lock and re-run `BookingValidationService::assertNoConflictingAppointment()` inside the transaction — a check outside it is a fast rejection, never a guarantee. Losing the race returns **409** with `error_type: slot_conflict` (`SlotUnavailableException`).

**`created_status` / `payment_status` logic:**
- `created_status = 1` always. `payment_method` is recorded as intent only and never decides whether the booking is real (a caller may pass `is_confirmed` explicitly, but no path does).
- `payment_status = PENDING` always at creation. Money is only recorded when staff finalize the invoice at the counter — a booking is never marked paid before the customer has arrived.

### 4.2 `BookingValidationService`
All booking validation rules, called by BookingService.

**Validations performed:**
1. `validateBasicData()` → At least 1 service, max `max_services_per_booking`, date not in past, date within `max_booking_days`, no duplicate service IDs
2. `validateProviderOffersService()` → Provider-service link exists in `provider_service` pivot, both provider and service are active
3. `validateSequentialTiming()` → Each service starts after the previous one ends (sequential booking)
4. `validateTimeSlotAvailability()` → **The most critical validation:**
   - Provider has a work schedule for that day of week (`provider_scheduled_works`)
   - Time slot falls within working hours
   - No time off — full-day or hourly — via `ProviderTimeOff::scopeCoveringDate()` + `blocksWindow()`, the same pair the availability layer uses
   - No conflicting appointments — via `Appointment::scopeBlocksProviderTime()`, the single shared definition (see 4.3)
   - Time is not in the past
   - Meets minimum advance booking time (`book_buffer` setting, default 60 minutes)
5. `assertCustomerIsFree()` → The customer has no other booking overlapping this window, on any provider. Identity spans account id AND phone (normalised via `App\Support\PhoneNumber`), so a guest booking and an account booking by the same person are recognised as one. Staff holding `force_booking` may override it with `allow_customer_overlap`
6. `validateDailyBookingLimit()` → Max bookings per customer per day

### 4.3 `ServiceAvailabilityService`
Calculates available time slots for the customer-facing booking interface.

**`getAvailableSlotsByDate(serviceId, date, branchId)`**
Returns all providers who offer the service with their available time slots for the given date. Providers with no bookable slot (on leave, not a work day, fully booked, or the date is past the booking window) are **filtered out entirely**. Exposed as `GET /api/availability/service`.

**`getProviderAvailableSlotsByDate(serviceId, providerId, date)`**
Returns available slots for a specific provider on a specific date.

**`getAvailabilityCalendar(serviceId, providerId, startDate, endDate, branchId)`**
Returns a calendar view showing which dates have available slots (max 31 days).

**Slot Generation Algorithm (`generateTimeSlots`):**
1. Get provider's work schedule for the day (`ProviderScheduledWork`)
2. If date is past `today + max_booking_days` → `outside_booking_window`, return empty
3. If no schedule or full-day time off → return empty
4. Start from shift start time — the grid is always `shift_start + k × duration`, **identical on every day including today**
5. Get existing appointments and hourly time offs
6. Iterate: for each potential slot (start → start + service_duration):
   - Skip if it starts before `now + book_buffer` (this is what excludes today's past slots)
   - Check no overlap with existing appointments
   - Check no overlap with hourly time offs
   - If clear → add to available slots
7. Advance by `service_duration + SLOT_BUFFER` (buffer is currently 0)
8. Stop when remaining time < service_duration

**Booking-constraint parity:** `book_buffer` and `max_booking_days` are enforced here as well as in `BookingValidationService`, so availability never offers a slot the booking call would reject. `max_daily_bookings`, duplicate-service and sequential-timing rules are request-level and remain booking-only.

**Conflict Detection:** Availability and booking share ONE definition of "the provider is busy" — `Appointment::scopeBlocksProviderTime()` (`created_status = 1` AND status IN (PENDING, COMPLETED)) combined with `scopeOverlapping()` (`start1 < end2 AND start2 < end1`, half-open so back-to-back bookings do not collide). Both `ServiceAvailabilityService::getProviderAppointments()` and `BookingValidationService::validateTimeSlotAvailability()` go through them, so a slot can never be advertised and then refused, or hidden with nothing real behind it.

**Caching:** Results cached for 1 minute per service+date+provider combination.

### 4.4 `InvoiceService`
Handles draft invoice construction, aggregated items, discounts, and tax calculation.

**`createDtaftInvoiceFromAppointment()`** — Creates a Draft invoice when booking is made:
- No invoice number (null)
- Status = DRAFT
- Copies subtotal, tax_amount, total_amount from appointment
- Creates InvoiceItems for each service (using TaxCalculatorService to extract net/tax from gross price)

It deliberately does **not** finalize payments. The old
`createInvoiceFromAppointment()` and `finalizeDraftInvoice()` payment writers were
removed by MON-05 so this class cannot issue a PAID invoice through a competing path.

**`rebuildAggregatedInvoice()`** — Rebuilds the one DRAFT invoice on the parent/standalone
appointment from every service in the linked group.

**`applyFinalAmount()`** — Applies the amount actually charged. A lower amount is a
special-customer price/discount, not a partial payment; it reconciles net/tax/gross.

### 4.4.1 `InvoiceFinalizationService`

**The only on-site payment application operation.** All payment UIs call
`finalizeAppointmentPayment(Appointment, paymentMethod, finalAmount, notes, source)`.

It atomically:
1. Resolves and locks the invoice owner plus every linked appointment.
2. Accepts only an active Cash/Card `PaymentMethod`.
3. Locks/re-checks the DRAFT invoice to suppress double collection.
4. Rebuilds all invoice items and applies any special-customer price.
5. Assigns the sequential invoice number and marks the invoice PAID.
6. Creates exactly one Payment with a non-null `payment_method_id`.
7. Marks every covered appointment COMPLETED with normalized `cash`/`card`.
8. Records source/audit metadata. TSE is explicitly disabled and no network call is made.

**`calculateReverseTax()`** — Thin wrapper over `TaxCalculatorService::extractTax()`. Kept only
because Filament payment paths call it by this name; it holds no arithmetic of its own.

### 4.5 `TaxCalculatorService`
**THE single tax implementation for the whole project.** Every layer that converts between gross
and net goes through it. High precision via `bcmath`.

**`extractTax(grossAmount, taxRate, precision)`** — Reverse tax calculation:
- Input: gross amount (tax-inclusive), tax rate (e.g., 19 or 19.5)
- Output: `{ net, tax, gross }` as strings with requested precision
- Guarantees: `net + tax = gross` (the difference always adjusts TAX, deterministically)

**`addTax(netAmount, taxRate, precision)`** — Forward tax calculation. ⚠️ **Not the inverse of
`extractTax`** — see note 18 below.

**`calculateBulk(items, precision)`** — Batch: rounds each line at the requested precision (invoice
practice), sums, reconciles once. Skips items without a usable numeric price.

**Internal precision:** `max(10, precision + 8)` — never the output precision. `bcdiv` TRUNCATES
rather than rounds, so dividing at the output precision destroys the digit that should have been
rounded, and rounding afterwards cannot bring it back. This was MON-01: `50.00 / 1.19` truncated to
`42.01` instead of rounding to `42.02`, and `bcdiv('19.5','100',2)` returned `'0.19'` — silently
computing 19.5% as 19%.

⚠️ **Never call `bcscale()`** anywhere in this project. It is request-wide state that silently
changes the default precision of every later `bcmath` call, in layers unrelated to the caller
(MON-06). Pass precision explicitly as the third argument of every `bcmath` call.

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
| `AppointmentReminderService` | Schedule/reschedule/cancel appointment reminders (one live reminder per appointment; see note 21) |
| `Reminders/ReminderChannelResolver` | **The single decision point for which channels a reminder uses** — push/email/SMS each gated on the customer's own setting |
| `AuthTokenService` | Sanctum token management |
| `DocumentNumberGenerator` | Sequential document numbering from a locked counter row (`INV-2026-000001`, `PAY-2026-000001`). **Must be called inside the caller's transaction** — it throws otherwise; see note 19 |
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
| GET | /api/availability/service | AvailabilityController | **All available providers** for a service on one date, each with slots + pricing (throttle 60/min) |
| GET | /api/availability/provider | AvailabilityController | Available slots for a provider/service/date (throttle 60/min) |
| GET | /api/availability/calendar | AvailabilityController | Calendar view of availability (throttle 30/min — heaviest) |

> **Rate limiting:** the availability routes are the only public reads that are throttled, because each provider × day pair issues its own schedule/leave/appointment queries — a 31-day calendar without `provider_id` fans out to ~800 queries. Limits are per IP (these routes are unauthenticated) and deliberately generous, since carrier CGNAT puts many real customers behind one address. The other public reads (`/services`, `/providers`, `/sliders`, `/about-us`) are still unthrottled.

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
| GET | /api/appointments/reminders/options | Reminder lead times + translated screen texts |
| POST | /api/appointments/reminders | Set or change an appointment's reminder |
| GET | /api/appointments/{id}/reminders | The live reminder (200 + `data: null` when none) |
| DELETE | /api/appointments/{id}/reminders | Switch the reminder off |
| GET | /api/settings | User options catalog incl. the 3 reminder channels |
| PATCH | /api/settings/{key} | Update one option (generic route) |
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
        │   │   ├── Create Appointment (status=PENDING, created_status=1, payment_status=PENDING)
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
    │   └── InvoiceFinalizationService.finalizeAppointmentPayment()
    │       │
    │       ├── Generate invoice number (INV-0001, sequential)
    │       ├── Update invoice status: DRAFT → PAID
    │       ├── Store payment data in invoice_data JSON:
    │       │   { finalized_at, payment_type, amount_paid, finalized_by }
    │       ├── Update appointment.payment_status
    │       ├── Create exactly one Payment linked to PaymentMethod
    │       ├── Complete the parent + all linked appointments
    │       └── Record TSE disabled metadata (no Fiskaly call)
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
- Fiskaly: `FISKALY_API_KEY`, `FISKALY_API_SECRET`, `FISKALY_TSS_ID` (dormant; these variables alone do not activate TSE)

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

The repository contains Fiskaly/TSE support code, but TSE is deliberately disabled
for the current non-production phase. The canonical payment flow makes no Fiskaly
call and records `tse_enabled=false` in invoice and payment metadata. It must not be
described as a signed transaction.

Re-enabling TSE later requires a separate reviewed integration and compliance
rollout; changing an environment variable alone is insufficient.

- `InvoiceService::signInvoiceWithTSE()` remains a dormant placeholder.
- `app/Services/Fiskaly/` contains support services outside the active payment path.
- `InvoiceService::submitToGermanTaxAuthority()` is also future work.
- `Invoice.segnture` and `Invoice.invoice_data` can hold future signature metadata.

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

5. **created_status Field**: Only appointments with `created_status = 1` block time slots, and since there is no online payment every booking is created at `1` (the column default is `1` too). The rule lives in `Appointment::scopeBlocksProviderTime()` and is consumed by both the availability and the booking layer — never re-write the condition inline, or the two layers drift apart again (this was bug BOOK-01).

6. **Invoice Lifecycle**: `DRAFT (booking) → PAID (payment)`. Draft invoices have no invoice number — a draft is not an issued document, so it must not consume one. The number is reserved and written inside the finalizing transaction, so a rollback returns it and leaves no gap. **One invoice per appointment** is enforced by a unique constraint; the finalizing paths upgrade the existing draft rather than inserting a second row. See note 19.

7. **Booking is a shared-resource write, not an INSERT**: provider time is contended. `BookingLockService` + `assertNoConflictingAppointment()` inside one transaction are what keep it correct; `appointments_conflict_lookup_idx` on `(provider_id, appointment_date, created_status, status)` is what keeps it fast. The three writers that touch an appointment's calendar position are `BookingService::createBooking()`, `BookingService::addServiceToBooking()` and `StaffDashboard::updateAppointment()` — all three lock and re-check. `force_booking` permission holders may overlap deliberately; nobody else can.

8. **Provider leave ranges**: `ProviderTimeOff::scopeCoveringDate()` decides which days a leave applies to (a null `end_date` means a single day — `end_date >= :date` silently drops null rows, because in SQL `NULL >= '…'` is UNKNOWN, not false). `blockedWindowOn()` decides which hours of a given day it eats: a **multi-day hourly leave is one continuous absence**, so its start day is blocked from `start_time` onward, its middle days entirely, and its end day until `end_time`. Both the availability and the booking layer call these — never re-derive leave windows inline (BOOK-04).

9. **A customer cannot be in two places at once**: `BookingValidationService::assertCustomerIsFree()` refuses any booking overlapping one the customer already holds — regardless of provider or service. Back-to-back is fine (half-open overlap), simultaneous is not. It replaced a check that only caught an identical start time *plus* a shared service, which let simultaneous bookings with a different provider and all partial overlaps through (BOOK-06). Phone numbers are compared through `App\Support\PhoneNumber::key()` (last 9 digits) — never by string equality, and never written back over what the customer typed.

10. **Cancellation is always allowed, and watched**: both customer endpoints (`/api/bookings/{id}/cancel` and `/api/appointments/{id}/cancel`) accept a cancellation on any PENDING booking, including after its start time — they used to disagree, so the customer picked a policy by picking a URL (BOOK-08). `cancellation_hours` exists in `salon_settings` but is deliberately unused: the deterrent is visibility, not a locked button. `Appointment::cancel()` is the single self-cancellation path (it hardcodes USER_CANCELLED; staff write ADMIN_CANCELLED directly) and calls `CancellationMonitor`, which raises a Filament database notification to `admin` and `manager` from the SECOND USER_CANCELLED within a rolling 7 days, and on every one after.

11. **`exists:` rules do not know about SoftDeletes**: an `exists:table,id` rule runs a raw table query, so it matches soft-deleted rows that Eloquent's global scope then hides — validation passes, the model lookup returns null, and a typed parameter raises a `TypeError`. That is an `Error`, not an `Exception`, so it escapes `catch (\Exception)` and lands as a bare 500 (BOOK-09). Booking is guarded on both sides: the rules carry `whereNull('deleted_at')`, and `validateProviderOffersService()` accepts null and rejects it. The second layer is the load-bearing one — StaffDashboard builds its payload by hand and never passes through a Form Request. Booking entry points catch `\Throwable`.

12. **Never distinguish "not found" from "not available" in booking responses**: `rejectProviderServicePair()` returns one generic message for every rejection reason and logs the real one, so the endpoint cannot be used to enumerate real service and user ids. Any new rejection path must reuse it.

13. **Document Number Formats**:
    - Appointment: `APT-YYYYMMDD-XXXXXX` (6 random hex chars, retried on collision)
    - Invoice: `INV-YYYY-NNNNNN` — sequential, resets each year
    - Payment: `PAY-YYYY-NNNNNN` — sequential, resets each year

    Invoice and payment numbers come from a locked counter row in
    `document_counters`, never from a table scan. `Agent.md` used to document
    the invoice format as `INV-XXXX`, which the code never produced. See note 19.

14. **InvoiceItem Observers**: The `InvoiceItem` model has boot observers that recalculate on save and cascade to the parent Invoice. When creating items in bulk, `InvoiceItem::withoutEvents()` is used to prevent redundant recalculations. **`calculateTotal()` treats a stored `total_amount` (gross) as authoritative and reverse-extracts the tax from it** — it must never rebuild the gross from the net; see note 18.

15. **Service Duration**: Currently uses `service.duration_minutes` directly (the custom duration from provider_service pivot is commented out / unreachable code in both BookingService and ServiceAvailabilityService).

16. **Spatie Roles**: Three roles: `admin`, `provider`, `customer`. Admin role is required for Filament panel access. Provider is not checked in Filament separately (relies on panel-level canAccessPanel which checks 'admin' role).

17. **Multi-language**: Three languages supported (en, ar, de). Services have translations via ServiceTranslation model. The Filament panel supports language switching via FilamentLanguageSwitcherPlugin.

18. **One tax implementation, and GROSS is the truth** (MON-01, fixed 2026-09-10): the project had
    SEVEN copies of the reverse-tax equation at three internal precisions, so one transaction wrote
    two different VAT figures — `appointments` said 7.98 and `invoices` said 7.99 for the same
    50.00 EUR service, while the printed receipt and the confirmation email showed the two different
    numbers to the customer. All of them now call `TaxCalculatorService`.

    The load-bearing consequence: **reverse extraction is not injective**, so a gross value can
    never be recovered from its net. `45.00 / 1.19 = 37.8151 → net 37.82`, and
    `37.82 * 1.19 = 45.0058 → gross 45.01 ≠ 45.00`. No amount of internal precision fixes that.
    Therefore a stored gross is authoritative and must never be re-derived from the net — which is
    what `InvoiceItem::calculateTotal()` used to do on every save, shaving a cent off invoices that
    were already finalised and printed. To change a line's price, write its `total_amount`.

    Tax rates always come from `get_setting('tax_rate')`, never a literal. Guarded by
    `tests/Feature/Money/TaxParityTest.php` (30 tests; 21 of them fail against the old code).
    Historical invoices are deliberately NOT corrected — GoBD *Unveränderbarkeit* — use the
    read-only `php artisan tax:drift-report` to size the drift. Full write-up:
    `docs/fixes/MON-01_vat_calculation_unified.md`.

19. **Document numbering is a concurrency problem, not a string-formatting one**
    (MON-03 / DB-01, fixed 2026-09-10): `DocumentNumberGenerator` used to read the
    newest invoice ROW (`ORDER BY id DESC`) instead of the highest NUMBER. Every
    booking creates a DRAFT whose `invoice_number` is NULL, so the newest row was
    a draft, the suffix parsed as 0, and **every invoice was issued as
    `INV-YYYY-000001`** — deterministically, with no concurrency involved. It also
    wrapped its own `DB::transaction()`, so the lock it took was released before
    the caller wrote the number, and nothing was reserved at all.

    The rules now:
    - Every sequential number comes from `DocumentNumberGenerator::next()`, which
      locks one row in `document_counters`. Never `MAX(number) + 1`, never
      `ORDER BY id DESC`, never `uniqid()`, never `while (exists())`.
    - It **must run inside the caller's transaction** and throws a
      `RuntimeException` otherwise. A lock released before the write reserves
      nothing — that was the bug. Do not silence the guard, and do not wrap
      `next()` in its own transaction.
    - `AUTO_INCREMENT` is deliberately not used: its value does not roll back
      with the transaction, so one rollback leaves a permanent gap.
    - Counter rows are seeded by the migration, never created on the hot path —
      `lockForUpdate()` cannot lock a row that does not exist (the same lesson as
      `BookingLockService` locking `users` rather than absent `appointments`).
    - Status guards go **after** the lock, inside the transaction — a check
      outside it is a fast rejection, never a guarantee (the BOOK-02 contract).
    - Two layers of defence: the lock stops the race, the UNIQUE constraint stops
      everything we forgot. Five constraints exist — `invoices(invoice_number)`,
      `invoices(appointment_id)`, `payments(payment_number)`,
      `appointments(number)`, `provider_service(provider_id, service_id)`.
    - `unique(['provider_id','start_time'])` on appointments is deliberately
      ABSENT: `force_booking` permits deliberate overlap.

    A double-click is not staff error: the second request on a transaction that
    succeeded raises `InvoiceAlreadyFinalizedException` (which carries the
    invoice) and the dashboard reprints instead of showing a failure.
    `wire:loading.attr="disabled"` is a browser-only guard.

    Historical duplicates were renumbered with a documented audit trail in
    `invoice_data.number_correction` (GoBD permits documented correction of a
    system fault, not silent change). Inspect with the read-only
    `php artisan documents:number-audit`. Full write-up:
    `docs/fixes/MON-03_document_numbering.md`.

20. **One on-site payment has one writer** (MON-05, fixed 2026-09-10):
    `InvoiceFinalizationService::finalizeAppointmentPayment()` is the only code
    allowed to turn a DRAFT invoice into PAID and insert its Payment. The
    StaffDashboard is the reference UX; the Filament appointment table and the
    provider relation manager are thin adapters to the same operation.

    The operation starts from an Appointment so it can resolve the invoice owner,
    locks the parent/standalone plus the entire linked group and invoice, rebuilds
    all items, applies an optional special-customer price, then writes one
    transactionally consistent result. Every Payment has an active
    `payment_method_id`; `appointments.payment_method` contains only `cash` or
    `card`; all covered appointments become COMPLETED together. A lower charge is
    a full payment after discount, not a partial payment. Zero, overpayment,
    disabled methods, online methods, cancelled/no-show appointments and a second
    finalization are rejected.

    The competing `InvoiceService::createInvoiceFromAppointment()`,
    `InvoiceService::finalizeDraftInvoice()` and `InvoicePaymentService` were
    removed. TSE remains deliberately disabled: the unified path performs no
    Fiskaly call and records `tse_enabled=false`. Guarded by
    `tests/Feature/Money/UnifiedPaymentFlowTest.php`; full write-up:
    `docs/fixes/MON-05_unified_payment_flow.md`.

21. **A reminder has no privileged channel** (2026-09-11): push, email and SMS are
    each gated on the customer's own setting, so "enabled SMS only" delivers an
    SMS and nothing else. Push used to be an unconditional "always-on baseline"
    sent from inside the claiming transaction, which made that rule impossible to
    express — and coupled `markSent()` to it, so gating push would have silently
    gated every channel. `Reminders\ReminderChannelResolver` is now the ONE place
    that answers "which channels?"; adding a channel is a line in `SETTING_KEYS`
    plus a seeder row. Settings are read at SEND time, never at scheduling time.

    `SendAppointmentReminderJob` runs CLAIM → DELIVER → RECORD. It claims the
    reminder (lock, re-check, `markSent()`) BEFORE sending anything, so a retried
    job cannot double-send; delivery happens OUTSIDE the transaction because no
    rollback can un-send an SMS. `delivered_channels` records what actually went
    out — `[]` means it fired with every channel switched off, which is the
    difference between diagnosing "I got no reminder" and guessing at it.

    **One live reminder per appointment**, enforced by
    `unique(appointment_id, user_id, active_slot)` where `active_slot` is 1 while
    pending and NULL once sent/cancelled (SQL treats NULLs as distinct, so history
    rows leave the index). The previous index spanned the MUTABLE `status` column,
    so rescheduling — which flips `pending` to `cancelled` rather than deleting —
    eventually collided on two cancelled rows and returned a bare 500 to a
    customer simply toggling the dropdown. Any code that retires a reminder MUST
    null `active_slot`, not just write `status`; use the model's mark* methods.

    The API takes `offset_hours` (1,2,3,4,5,6,24 from
    `config/appointment_reminders.php`) and derives the instant from the
    appointment's own `start_time`. That is deliberate: `APP_TIMEZONE` is
    Asia/Baghdad while the salon runs Berlin hours, so a client-computed absolute
    `remind_at` means two different moments depending on how the client formats
    it. `remind_at` still works for published app builds and is the only path
    that carries the hazard. `POST /api/bookings` accepts
    `reminder_offset_hours`; a reminder failure there NEVER rolls back the
    booking — provider time is contended and unrecoverable, a reminder is one tap
    away. Guarded by `tests/Feature/Reminders/` (33 tests); full write-up:
    `docs/APPOINTMENT_REMINDER_CHANNELS_2026-09-11.md`.
