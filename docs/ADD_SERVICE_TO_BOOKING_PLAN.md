# 📐 Plan: Add Service To Existing Booking — Smart Cascade Push Edition

> **Status:** Draft v1.0
> **Author:** Senior Architect Plan
> **Date:** 2026-05-24
> **Estimated effort:** Scenario A (Primary): 5–8 working days · Scenario B (Drop): 15–20 working days
> **Type:** Major feature + schema refactor

---

## 📑 Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Detailed Task Definition](#2-detailed-task-definition)
3. [Current State Analysis](#3-current-state-analysis)
4. [Target State](#4-target-state)
5. [Scope (In / Out / Deferred)](#5-scope)
6. [Architectural Decisions](#6-architectural-decisions)
7. [Database Schema Changes](#7-database-schema-changes)
8. [Provider_id Strategy — Two Scenarios](#8-provider_id-strategy--two-scenarios)
9. [Affected Files — Complete Catalog](#9-affected-files--complete-catalog)
10. [Execution Plan — Phases & Tasks](#10-execution-plan)
11. [The Smart Push Algorithm](#11-the-smart-push-algorithm)
12. [UX Flow — Detailed](#12-ux-flow-detailed)
13. [Validation Matrix](#13-validation-matrix)
14. [Edge Cases & Error Handling](#14-edge-cases--error-handling)
15. [Permission & Authorization](#15-permission--authorization)
16. [Testing Strategy](#16-testing-strategy)
17. [Rollback & Recovery Plan](#17-rollback--recovery-plan)
18. [Open Questions / Future Work](#18-open-questions--future-work)

---

## 1. Executive Summary

This feature adds the ability for a manager (admin) — working from the `StaffDashboard` Livewire screen — to **add a new service to an existing PENDING appointment**, where:

- The new service may belong to a **different provider** than the appointment's current provider(s).
- The system intelligently detects **time conflicts** with subsequent bookings.
- When a conflict occurs, the manager is offered two clearly framed options:
  1. **Shrink** the new service's duration to the maximum that fits in the available gap.
  2. **Cascade-push** the conflicting subsequent bookings forward by the minimal necessary amount, recursively (a "push only what is needed" algorithm).
- For every pushed booking, the **original time is preserved exactly once** in two new columns (`original_start_time`, `original_end_time`) so that staff can later explain to the customer: *"Your booking was originally at 10:40 — we shifted it by 10 minutes because…"*.

This unlocks two structural changes the system has lacked:
- A booking can now legitimately span **multiple providers** (each `appointment_service` row will know its own `provider_id`).
- The timeline view will render **one card per service-per-provider** so the same `appointment_id` appears in each provider's column — visually linked via a connector line.

There are **two strategic paths** for handling the legacy `appointments.provider_id` column, both fully planned in this document:

- **Scenario A — Keep as "primary provider" (denormalized).** Recommended. The column remains but its semantic shifts to "the provider of the first service (sequence_order = 1)" and is auto-synced via observer. All existing screens (Reports, Filament resources, API, etc.) keep working untouched.
- **Scenario B — Drop the column entirely.** Pure but invasive. Every read site must migrate to `appointment_services.provider_id`. ~30+ files touched.

Every phase below clearly marks which steps differ between the two scenarios.

---

## 2. Detailed Task Definition

### 2.1 Functional Requirements

| # | Requirement | Priority |
|---|-------------|----------|
| FR-1 | Manager can open an existing appointment from the timeline and add a new service via a dedicated "+ Add Service" affordance inside the Appointment Modal. | MUST |
| FR-2 | The add-service form lets the manager choose: **category → service → start_time → provider**. Provider list is filtered to those who actually offer the service AND have a working schedule on that date. | MUST |
| FR-3 | The form auto-pulls `duration_minutes` and `price` from the chosen service (with `discount_price` fallback) but the manager can override the price if needed (current behavior in `appointment_services.price`). | MUST |
| FR-4 | The system validates the proposed time slot against: salon open hours, provider schedule, full-day time off, hourly time off, existing appointments of that provider. | MUST |
| FR-5 | If there is **no conflict** → service is added immediately, totals recomputed, DRAFT invoice updated, timeline refreshed. | MUST |
| FR-6 | If the new service collides only with the appointment's own current services (overlap inside the booking on the same provider) → **outright reject**, never push. | MUST |
| FR-7 | If the new service collides with a subsequent **other booking** of that same provider → open the Conflict Dialog (see UX Section). | MUST |
| FR-8 | The Conflict Dialog computes and displays the **maximum allowable duration** so the user can accept a shrink (option 1), and computes the **full cascade-push preview** (which bookings move, by how many minutes) so the user can accept push (option 2). | MUST |
| FR-9 | The cascade-push algorithm shifts only the minimal needed amount per affected booking. It must respect: provider work-end time, salon close time, hourly time-offs, and never push a booking whose `payment_status ∈ {PAID_ONLINE, PAID_ONSTIE_CASH, PAID_ONSTIE_CARD}` or `status = COMPLETED`. | MUST |
| FR-10 | Push semantic is **booking-level**: when a booking is pushed, *all* its services (across all providers) shift by the same delta. The push is one logical operation per booking. | MUST |
| FR-11 | The very first time a booking is pushed, its current `start_time`/`end_time` are snapshotted to `original_start_time`/`original_end_time`. Subsequent pushes do **not** overwrite the snapshot. | MUST |
| FR-12 | Adding a service before the appointment's current `start_time` is permitted (extends the booking backwards) — only if there's no conflict with prior appointments on that provider's column. | MUST |
| FR-13 | **No gaps allowed** inside a single appointment: each new service must touch (start = end of previous service) or overlap-by-zero with at least one existing same-provider service window OR be the first/last in the chain. | MUST |
| FR-14 | The whole operation is atomic — single `DB::transaction` with `lockForUpdate` on every affected appointment row, full rollback on any error. | MUST |
| FR-15 | A new permission `appointment.add-service` gates the operation. Default assignment: `admin` role only. | MUST |
| FR-16 | The timeline renders one card per `appointment_service` row, placed in the column of that service's `provider_id`. Cards sharing the same `appointment_id` are visually connected (link icon + tooltip). | MUST |
| FR-17 | Pushed bookings display a small "shifted" badge with a tooltip showing the original time. | MUST |
| FR-18 | No customer-facing notifications in this milestone. | OUT OF SCOPE |

### 2.2 Non-Functional Requirements

| # | Requirement |
|---|-------------|
| NFR-1 | Concurrency-safe: two staff members modifying overlapping appointments must not corrupt state. Achieved via `SELECT ... FOR UPDATE`. |
| NFR-2 | The Smart Push algorithm runs in O(N) where N is the number of subsequent appointments on the affected provider for the day. Realistic N < 30 → execution well under 100ms. |
| NFR-3 | All money math uses `bcmath` to remain consistent with `BookingService::calculateTotals`. |
| NFR-4 | All existing automated tests continue to pass after the change. |
| NFR-5 | Backwards compatibility: legacy bookings (before this change) must continue to render correctly. The migration backfills `appointment_services.provider_id` from `appointments.provider_id` for all existing rows. |

---

## 3. Current State Analysis

### 3.1 What Exists Today

#### Data Layer

```
appointments
├── id, number, customer_id, provider_id ← single provider per booking
├── customer_name, customer_email, customer_phone
├── appointment_date, start_time, end_time, duration_minutes
├── subtotal, tax_amount, total_amount
├── status, payment_status, payment_method, created_status
├── cancellation_reason, cancelled_at, notes
└── timestamps

appointment_services
├── id, appointment_id, service_id
├── service_name (snapshot), duration_minutes, price
├── sequence_order   ← 1, 2, 3 ...
└── timestamps        (NO provider_id!)
```

The schema forces a single provider per booking. Every `appointment_service` inherits the appointment's provider implicitly. This is the **first blocker** for the requested feature.

#### Booking Creation Path

`POST /api/bookings` → `BookingController@store` → `BookingService::createBooking()` → in one DB transaction:
1. Validates via `BookingValidationService`.
2. Sorts services by start_time.
3. For each service, validates provider-offers-service, sequential timing, time-slot availability, no duplicate.
4. Computes totals via `bcmath`.
5. Creates `appointments` row.
6. Creates one `appointment_services` row per service.
7. Calls `InvoiceService::createDtaftInvoiceFromAppointment` → creates a DRAFT `invoices` row + one `invoice_items` row per service.
8. Returns the loaded appointment.

`created_status` is set to `1` (confirmed) when `payment_method = cash`, otherwise `0`. The dashboard's add-booking path sets `is_confirmed = true` explicitly.

#### Staff Dashboard

`/dashboard` → `EnsureStaffDashboardAccess` middleware → `StaffDashboard` Livewire component, rendered with `layouts.dashboard`.

- The Alpine layer holds local UI state (modals, timeline scale, drag-to-create).
- Livewire holds server state and method endpoints.
- `DashboardService` feeds providers, schedules, appointments, time-offs, and the "available providers at this time" lookup.

The Appointment Modal currently shows: number, customer, services (concatenated string), provider, status, payment status, and inputs for `editStartTime` + `editDuration`. Action buttons: Cancel, Delete, Pay, Save.

There is **no** "Add Service" capability anywhere in this modal today.

#### Timeline Rendering

`getTimelineDataFromProviders()` (`StaffDashboard.php` lines 605–692):
- Loads all not-cancelled, `created_status=1` appointments for the selected date and visible providers.
- Groups them by `appointment.provider_id`.
- For each booking emits a single card whose data carries: id, number, start_time, end_time, duration, customer_name, services (as concatenated string of all service names in the booking), status, payment_status, total_amount, service_color_code (from the first appointment_service's service relation).

So today, **one appointment → one card → one column**. Cards never repeat.

#### Conflict Detection (today)

`BookingValidationService::validateTimeSlotAvailability()`:
```php
Appointment::where('provider_id', $provider->id)
    ->whereDate('appointment_date', $date)
    ->where('created_status', 1)
    ->whereIn('status', [PENDING, COMPLETED])
    ->where(function($q) use ($startTime, $endTime) {
        $q->where('start_time', '<', $endTime)
          ->where('end_time', '>', $startTime);
    })
    ->exists();
```
This checks against the **appointment's** start/end, not against individual services. After this feature it will need to consider per-service windows for multi-provider bookings.

#### Invoice Lifecycle

```
DRAFT (created at booking) ──► PAID (created at payment via InvoiceFinalizationService)
```

`InvoiceItem` has `boot::saving` that calls `calculateTotal()` and `boot::saved`/`deleted` that calls `invoice->calculateTotals()`. This is excellent — adding/removing items auto-rebalances the invoice.

`Invoice::calculateTotals()`:
```php
subtotal = sum(items.subtotal)
tax_amount = subtotal * tax_rate / 100
total_amount = subtotal + tax_amount
$this->save();
```

### 3.2 Pain Points the Feature Reveals

| # | Problem | Impact |
|---|---------|--------|
| P1 | `appointment_services` has no `provider_id` | Cannot represent multi-provider bookings at all |
| P2 | Timeline groups by `appointment.provider_id` only | A booking with services from two providers would be invisible in the second provider's column |
| P3 | No history of original time before push | After a push, the original time is irrecoverable |
| P4 | Conflict detection uses appointment-level time | Inaccurate for multi-provider bookings (provider A might be free while provider B is busy in the same minute) |
| P5 | No "Add Service" capability post-booking | Forces the manager to cancel + recreate a booking for every change |
| P6 | No cascade-push capability | Manual reshuffling is error-prone and time-consuming |

---

## 4. Target State

After this feature ships:

1. **Multi-provider bookings are first-class.** A single `appointment` can have services for any number of providers; each `appointment_service` knows its own `provider_id`.
2. **The timeline shows one card per service-per-provider.** Same `appointment_id` may appear in multiple provider columns; cards are linked visually.
3. **The Appointment Modal exposes "+ Add Service".** Inline form: category → service → start_time → provider (auto-filtered). Add button triggers validation + cascade-push flow.
4. **A Conflict Dialog** appears when needed: shows max-fit duration AND cascade-push preview, lets user pick one action.
5. **`appointments.original_start_time` and `appointments.original_end_time`** preserve the pre-push state, set once on the first push.
6. **A new permission `appointment.add-service`** is required for the operation, seeded to `admin`.
7. **All existing flows (API, Filament, Reports, Reminders) keep working.** In Scenario A unchanged. In Scenario B they're all migrated to read from `appointment_services.provider_id`.

---

## 5. Scope

### 5.1 In Scope

- Schema migration: `appointment_services.provider_id`, `appointments.original_start_time`, `appointments.original_end_time`.
- Backfill data migration for existing `appointment_services` rows.
- Service class: `AppointmentServiceAppender` (new) — handles the add-service operation.
- Service class: `BookingPushService` (new) — implements the cascade-push algorithm.
- Service class: `AppointmentRecalculator` (new) — recomputes appointment totals/duration/start/end from the canonical `appointment_services` rows.
- Livewire methods on `StaffDashboard`: `prepareAddServiceForm`, `previewAddService`, `confirmAddService`, `confirmAddServiceWithShrink`, `confirmAddServiceWithPush`.
- New Blade partial: `appointment-add-service-form` + Conflict Dialog modal.
- Timeline rendering refactor: cards keyed by `(appointment_id, provider_id)` instead of `appointment_id`.
- Push badge in the timeline card + tooltip.
- Permission seeding.
- Feature tests for: add-service no-conflict, add-service-shrink, add-service-push (single + cascade), failed-push (paid blocker), failed-push (work-end boundary), concurrency lock test.

### 5.2 Out of Scope (Deferred)

- Customer notifications when pushed.
- Editing the price or duration of an already-added service (only add/delete).
- Reordering services manually via drag-and-drop in the modal.
- Re-implementing the API booking endpoint with multi-provider support — only the **read-side** of the API will display correctly; mobile clients still send single-provider payloads.
- Reports redesign for per-provider metrics from `appointment_services` (handled separately in Scenario B only).
- The `provider_service.custom_duration` resurrection (still a dead-code TODO).
- Removing the typo `createDtaftInvoiceFromAppointment` (separate cleanup PR).

### 5.3 Explicit Non-Goals

- The feature is **not** a generic "reschedule" tool — it is *add a service that may push subsequent bookings*. Stand-alone manual reschedule of an existing booking remains via the existing edit-time inputs.
- The feature does **not** modify existing services in a booking, only adds.

---

## 6. Architectural Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Provider on `appointment_services` | Add `provider_id` (FK → users, **NOT NULL** after backfill) | Canonical source of truth for multi-provider bookings |
| Provider on `appointments` | **Two scenarios planned below**; Scenario A keeps as denormalized "primary provider" | Trade-off: scope vs. purity |
| Original time storage | Two columns `original_start_time`, `original_end_time` on `appointments` (nullable) | Simple, query-friendly, fits the "set once" requirement |
| Push semantic | Booking-level (whole appointment moves together) | User's explicit instruction; simplifies algorithm |
| Concurrency | Pessimistic lock (`lockForUpdate`) on affected appointment rows inside the transaction | Required to prevent race when two managers act simultaneously |
| Invoice update | Update existing DRAFT invoice items + observers recompute totals | Minimal disruption, observer chain already correct |
| Validation reuse | Heavily reuse `BookingValidationService` to keep one source of truth for rules | Avoid duplicate rule drift |
| New permission | `appointment.add-service`, gated via Spatie `can()` in Livewire method and in middleware-ish guard inside service | Defense in depth |
| Card rendering | One card per `appointment_service` row, keyed by `(appointment_id, provider_id, service_index)` | Matches user's explicit choice; visually unambiguous |
| Linking cards visually | SVG/CSS connector overlay between cards sharing `appointment_id` + icon badge | Helps managers see they belong together |
| Push depth limit | Hard limit of 50 cascading bookings; abort with error if exceeded | Defensive sanity bound |

---

## 7. Database Schema Changes

### 7.1 New Migration: `2026_05_25_000001_add_provider_id_to_appointment_services_table.php`

```php
Schema::table('appointment_services', function (Blueprint $table) {
    $table->unsignedBigInteger('provider_id')
        ->nullable()
        ->after('service_id');
    $table->foreign('provider_id')
        ->references('id')
        ->on('users')
        ->onDelete('restrict');
    $table->index(['provider_id', 'appointment_id']);
});
```

**Backfill data migration** (runs immediately after — same migration file's `up`):

```php
DB::statement('
    UPDATE appointment_services AS aps
    INNER JOIN appointments AS a ON a.id = aps.appointment_id
    SET aps.provider_id = a.provider_id
    WHERE aps.provider_id IS NULL
');
```

**After backfill** — separate migration `2026_05_25_000002_make_appointment_services_provider_id_not_null.php`:

```php
Schema::table('appointment_services', function (Blueprint $table) {
    $table->unsignedBigInteger('provider_id')->nullable(false)->change();
});
```

(Two-step to keep the deploy safe even if the backfill is slow on a large table.)

### 7.2 New Migration: `2026_05_25_000003_add_original_times_to_appointments_table.php`

```php
Schema::table('appointments', function (Blueprint $table) {
    $table->dateTime('original_start_time')->nullable()->after('end_time');
    $table->dateTime('original_end_time')->nullable()->after('original_start_time');
    $table->index('original_start_time');
});
```

### 7.3 Scenario B Only — Drop `appointments.provider_id`

`2026_05_25_000004_drop_provider_id_from_appointments_table.php`

```php
Schema::table('appointments', function (Blueprint $table) {
    $table->dropForeign(['provider_id']);
    $table->dropColumn('provider_id');
});
```

> ⚠️ **Run this migration only after every code reference to `appointment.provider_id` has been removed and the application has been validated end-to-end.**

### 7.4 Indexing Strategy

| Index | Purpose |
|-------|---------|
| `appointment_services (provider_id, appointment_id)` | Fast lookup "all services this provider has in a booking" |
| `appointment_services (provider_id, appointment_id, sequence_order)` (optional) | Optimize sorted reads for the timeline |
| `appointments (appointment_date, original_start_time)` (optional) | If pushed-bookings reports are needed |

### 7.5 Down-migrations

Each migration ships a working `down()` that drops the added columns / re-creates the dropped column. For the data backfill there is no reverse migration (idempotent forward only).

---

## 8. Provider_id Strategy — Two Scenarios

### 8.1 Scenario A: Keep `appointments.provider_id` as Denormalized "Primary Provider"

**Semantic redefinition:** `appointments.provider_id` = the provider of the service whose `sequence_order = 1` (i.e., the earliest service). It is automatically kept in sync via an observer.

**Pros:**
- All existing code keeps reading `appointment.provider_id` and continues to work.
- Reports, Filament filters, API responses unchanged.
- Smaller PR, faster delivery.

**Cons:**
- Two sources of truth for "who serves this booking": `appointment.provider_id` (primary) vs `appointment_services.provider_id[]` (canonical). Must always keep them in sync.
- Reports that aggregate per-provider count bookings under the primary provider only — slightly inaccurate when a booking has services for multiple providers, but tolerable.

**Implementation steps unique to Scenario A:**
1. Create `AppointmentServiceObserver` with `saved` and `deleted` callbacks.
2. On save/delete, call `AppointmentRecalculator::syncPrimaryProvider($appointment)`:
   ```php
   $first = $appointment->services_record()->orderBy('sequence_order')->first();
   if ($first && $appointment->provider_id !== $first->provider_id) {
       $appointment->update(['provider_id' => $first->provider_id]);
   }
   ```
3. Wire the observer in `AppServiceProvider::boot()`.

### 8.2 Scenario B: Drop `appointments.provider_id` Completely

**Semantic:** `appointment_services.provider_id` is the only source. Any "who serves this booking" question is answered by `SELECT DISTINCT provider_id FROM appointment_services WHERE appointment_id = X`.

**Pros:**
- Pure data model. No denormalization. No sync risk.
- Reports and queries reflect real multi-provider reality.

**Cons:**
- **30+ files touched.** Filament resources, API controllers, Reports services, scheduled jobs, reminders, even the `User::appointmentsAsProvider()` relation must be rewritten.
- The `AppointmentResource` (API JSON) must change response shape.
- Reports may need rewrites (e.g., "revenue per provider" cannot use one provider per booking; must split revenue per service-provider).
- API client breaking changes for mobile.

**Implementation steps unique to Scenario B:**

#### Step B-1: Replace `User::appointmentsAsProvider()`

`app/Models/User.php` — replace the `hasMany(Appointment::class, 'provider_id')` with a derived relation:

```php
public function appointmentsAsProvider()
{
    return $this->hasManyThrough(
        Appointment::class,
        AppointmentService::class,
        'provider_id',     // foreign on AppointmentService
        'id',              // local on Appointment
        'id',              // local on User
        'appointment_id'   // foreign on AppointmentService
    )->distinct();
}

public function appointmentServicesAsProvider()
{
    return $this->hasMany(AppointmentService::class, 'provider_id');
}
```

#### Step B-2: Rewrite `BookingValidationService::validateTimeSlotAvailability`

The conflict-detection query changes from:
```php
Appointment::where('provider_id', $provider->id)->where(...)
```
to:
```php
AppointmentService::where('provider_id', $provider->id)
    ->whereHas('appointment', function($q) use ($date) {
        $q->whereDate('appointment_date', $date)
          ->where('created_status', 1)
          ->whereIn('status', [PENDING, COMPLETED]);
    })
    ->where(function($q) use ($startTime, $endTime) {
        // Need per-service time windows — but appointment_services doesn't store start/end!
    })
    ->exists();
```

**Critical issue:** `appointment_services` only stores `duration_minutes`, not start/end times. We need to compute service start times from the appointment's start_time + cumulative durations, OR add `start_time`/`end_time` columns to `appointment_services`.

Adding `start_time`/`end_time` columns to `appointment_services` is the cleaner long-term fix and is **required** for Scenario B to be coherent.

Migration `2026_05_25_000005_add_times_to_appointment_services_table.php` (Scenario B only):
```php
Schema::table('appointment_services', function (Blueprint $table) {
    $table->dateTime('start_time')->nullable()->after('duration_minutes');
    $table->dateTime('end_time')->nullable()->after('start_time');
    $table->index(['provider_id', 'start_time', 'end_time']);
});

DB::statement('
    UPDATE appointment_services AS aps
    INNER JOIN appointments AS a ON a.id = aps.appointment_id
    SET aps.start_time = a.start_time,
        aps.end_time   = a.end_time
    WHERE aps.start_time IS NULL
');
// then NOT NULL in a follow-up migration
```

#### Step B-3: Files to refactor (full list for Scenario B)

| File | Change |
|------|--------|
| `app/Models/Appointment.php` | Remove `provider()` relation; replace with `providers()` (BelongsToMany through `appointment_services`). Remove `provider_id` from `$fillable`. Add `services_record_for($providerId)` scope helper. |
| `app/Models/User.php` | Rewrite `appointmentsAsProvider()` as shown above. |
| `app/Services/BookingService.php` | Stop writing `provider_id` to the `appointments` row in `createBooking()`. Push it to each `appointment_services` row instead. |
| `app/Services/BookingValidationService.php` | Rewrite `validateTimeSlotAvailability` to query `appointment_services` with provider/start/end columns. |
| `app/Services/DashboardService.php` | `getProvidersWithStatus()` booking-count query: switch to `appointment_services.provider_id`. `getAppointmentsForDate()` eager-load by `appointment_services.provider_id`. `getAvailableProvidersForServiceAtTime()` conflict check switches to `appointment_services`. |
| `app/Services/ServiceAvailabilityService.php` | Rewrite all `Appointment::where('provider_id', …)` to use `appointment_services`. |
| `app/Services/ProviderReportService.php` | All 13 occurrences (lines 48, 67, 101, 129, 146, 172, 195, 220, 238, 272, 374, 395, 413) refactored to join through `appointment_services`. |
| `app/Services/ReportsService.php` | Lines 86–92 (revenue per provider), 106–117 (services per provider), 302–311 (worked minutes) rewritten to use `appointment_services`. |
| `app/Services/ProviderService.php` | Replace provider listing queries. |
| `app/Services/AppointmentReminderService.php` | If it filters by appointment.provider_id for "who to notify", switch to first-service provider or send to all distinct providers. |
| `app/Services/Appointments/AppointmentCreationService.php` | Provider write path rewrite. |
| `app/Http/Controllers/Api/BookingController.php` | The API still receives `provider_id` per service; ensure it persists to `appointment_services` only. |
| `app/Http/Controllers/Api/AppointmentController.php` | Response shape: switch from one provider per appointment to providers array. |
| `app/Http/Controllers/Api/AvailabilityController.php` | Conflict checks via `appointment_services`. |
| `app/Http/Controllers/Api/ProvidersController.php` | If exposing "bookings count per provider", rewrite query. |
| `app/Http/Controllers/BookingController.php` (web) | Same write-path adjustment. |
| `app/Http/Resources/AppointmentResource.php` | Switch from `provider` (single) to `providers` (array) + per-service provider on each service item. |
| `app/Http/Resources/ServiceResource.php` | Update if exposing provider in service context. |
| `app/Http/Resources/SingleServiceResource.php` | Same. |
| `app/Http/Requests/Api/BookingCreateRequest.php` | Confirm validation rules align with per-service provider (already does — `services.*.provider_id`). |
| `app/Http/Requests/Api/BookingValidateRequest.php` | Same. |
| `app/Filament/Resources/Appointments/Schemas/AppointmentForm.php` | Remove the appointment-level `provider_id` field; add a per-service provider repeater. |
| `app/Filament/Resources/Appointments/Pages/CreateAppointment.php` | Adjust state mapping when saving. |
| `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php` | Switch the provider column from `provider.full_name` to a custom column that lists all distinct providers per appointment. |
| `app/Filament/Resources/Providers/Pages/EditProvider.php` | Counts and lists. |
| `app/Filament/Resources/Providers/Schemas/ProviderInfolist.php` | If shows appointments count. |
| `app/Filament/Resources/Providers/Widgets/ProviderStatsOverviewWidget.php` | Stats query refactor. |
| `app/Filament/Resources/Providers/Tables/ProvidersTable.php` | Same. |
| `app/Filament/Resources/Providers/RelationManagers/AppointmentsRelationManager.php` | Switch the relation to go through `appointment_services`. |
| `app/Filament/Resources/Services/RelationManagers/AppointmentsRelationManager.php` | Similar adjustment if it filters by provider. |
| `app/Filament/Resources/Users/RelationManagers/ServicesRelationManager.php` | Mostly unaffected, double-check. |
| `app/Filament/Pages/Reports.php` | All chart/table queries that reference provider_id on appointments → through services. |
| `app/Filament/Pages/ProviderReport.php` | Same. |
| `app/Filament/Pages/ViewProviderScheduleTimeline.php` | Provider's appointments query refactor. |
| `app/Livewire/StaffDashboard.php` | All references to `apt.provider_id` switch to `apt.services_record.first().provider_id` or iterate per service. |
| `app/Services/BookingService2.php` | If still used, refactor; otherwise delete (looks legacy). |

**Decision recommended:** Scenario A is the right call for this PR. Scenario B should be planned as a separate, larger initiative ("Multi-Provider Data Model Migration") and tracked in its own roadmap.

The **rest of this document assumes Scenario A is chosen**, with Scenario-B-only deltas called out explicitly in shaded **B-only** callouts.

---

## 9. Affected Files — Complete Catalog

### 9.1 Files Touched in **Both** Scenarios

| Path | Change Summary |
|------|----------------|
| `database/migrations/2026_05_25_000001_add_provider_id_to_appointment_services_table.php` | NEW — add column + backfill |
| `database/migrations/2026_05_25_000002_make_appointment_services_provider_id_not_null.php` | NEW — NOT NULL constraint |
| `database/migrations/2026_05_25_000003_add_original_times_to_appointments_table.php` | NEW — add original_start_time, original_end_time |
| `app/Models/AppointmentService.php` | Add `$fillable` entry `provider_id`, add `provider()` relation (`BelongsTo User`), add `$casts` for `start_time`/`end_time` if added |
| `app/Models/Appointment.php` | Add `$fillable` for `original_start_time`, `original_end_time`. Add casts. Add `was_pushed` accessor (`return !is_null($this->original_start_time)`). Add `getShiftMinutesAttribute()` accessor. |
| `app/Services/BookingService.php` | `createBooking()` now writes `provider_id` to each `appointment_services` row from the per-service payload. (In Scenario A also keeps writing the appointment-level `provider_id` as before.) |
| `app/Services/BookingValidationService.php` | `validateTimeSlotAvailability()` extended to optionally exclude a target appointment id (so we don't compare a booking with itself when adding a service). Add a new public method `validateAddedServiceTimeSlot()`. |
| `app/Services/AppointmentRecalculator.php` | NEW service class — recomputes appointment.start_time / end_time / duration_minutes / subtotal / tax_amount / total_amount from its `appointment_services` rows. Mirrors `BookingService::calculateTotals` math. |
| `app/Services/AppointmentServiceAppender.php` | NEW service class — orchestrates the add-service operation. |
| `app/Services/BookingPushService.php` | NEW service class — implements the cascade-push algorithm. |
| `app/Livewire/StaffDashboard.php` | New methods: `prepareAddServiceForm`, `previewAddService`, `confirmAddService`, `confirmAddServiceWithShrink`, `confirmAddServiceWithPush`, `closeAddServiceForm`, `closeConflictDialog`. New public state: `showAddServiceForm`, `addServiceForm`, `addServicePreview`, `showConflictDialog`. |
| `app/Services/DashboardService.php` | `getTimelineDataFromProviders` rewrite → emit one card per `appointment_service`. Update `getAppointmentDetails` eager-load `services_record.provider`. |
| `app/Http/Middleware/EnsureStaffDashboardAccess.php` | No change to access logic, but document that `appointment.add-service` is an inner permission. |
| `database/seeders/RoleSeeder.php` | Add `appointment.add-service` permission and grant to `admin`. |
| `resources/views/livewire/staff-dashboard.blade.php` | Add: "+ Add Service" button in appointment modal. Add: inline add-service form Blade. Add: Conflict Dialog Blade. Update timeline card rendering to handle one-card-per-service + linked cards visual. Add: "shifted" badge + tooltip. Update appointment modal services list to show per-service provider. |
| `resources/views/livewire/partials/add-service-form.blade.php` | NEW — extracted partial for the inline form |
| `resources/views/livewire/partials/conflict-dialog.blade.php` | NEW — the conflict resolution modal |
| `lang/en/dashboard.php`, `lang/ar/dashboard.php`, `lang/de/dashboard.php` | NEW translation keys (see Section 10.8) |
| `tests/Feature/AddServiceToBookingTest.php` | NEW — feature tests |
| `tests/Feature/BookingPushServiceTest.php` | NEW — unit tests for cascade push |

### 9.2 Files Touched **Only in Scenario A**

| Path | Change Summary |
|------|----------------|
| `app/Observers/AppointmentServiceObserver.php` | NEW — keeps `appointment.provider_id` synced to the first service's provider. |
| `app/Providers/AppServiceProvider.php` | Register observer in `boot()`. |

### 9.3 Files Touched **Only in Scenario B**

(See Section 8.2 Step B-3 for the full enumeration. ~30 files.)

### 9.4 Files **NOT** Touched

- `routes/api.php`, `routes/web.php` — no new endpoints needed (Livewire handles everything).
- `app/Models/Invoice.php`, `app/Models/InvoiceItem.php` — observers already correct.
- `app/Models/Service.php`, `app/Models/ServiceCategory.php` — unchanged.

---

## 10. Execution Plan

### Phase Overview

| # | Phase | Days (A) | Days (B add'l) | Depends on |
|---|-------|----------|----------------|------------|
| 1 | Schema Foundation | 0.5 | +1 | — |
| 2 | Model Layer & Observers | 0.5 | +1.5 | 1 |
| 3 | AppointmentRecalculator Service | 0.5 | — | 2 |
| 4 | AppointmentServiceAppender Service | 1.0 | — | 3 |
| 5 | BookingPushService — Cascade Push Algorithm | 1.5 | — | 3 |
| 6 | Validation Layer Extensions | 0.5 | +0.5 | 2 |
| 7 | Permission Wiring | 0.25 | — | — |
| 8 | DashboardService Timeline Refactor | 0.5 | +0.5 | 2 |
| 9 | StaffDashboard Livewire Methods | 1.0 | — | 4, 5, 7 |
| 10 | Blade UI — Add Service Form + Conflict Dialog + Timeline Cards | 1.5 | — | 9 |
| 11 | Existing-Booking Path Adjustments (write provider_id to services) | 0.25 | — | 1 |
| 12 | Filament/API/Reports Refactor | — | +10 to 12 | — (Scenario B only) |
| 13 | Testing | 1.0 | +1 | 4, 5, 9, 10 |
| 14 | Deployment & Backfill | 0.5 | +0.5 | All |

**Total Scenario A:** ~9 working days (with buffer ≈ 10–12).
**Total Scenario B:** ~25 working days (with buffer ≈ 28–32).

---

### Phase 1 — Schema Foundation

**Goal:** Land the migrations with safe backfill so all subsequent phases have the new columns.

#### Task 1.1 — Create migration: add `provider_id` to `appointment_services`

📁 NEW FILE: `database/migrations/2026_05_25_000001_add_provider_id_to_appointment_services_table.php`

TODO:
- [ ] Add column `provider_id BIGINT UNSIGNED NULL` after `service_id`.
- [ ] Add foreign key referencing `users(id)` with `onDelete('restrict')`.
- [ ] Add composite index `(provider_id, appointment_id)`.
- [ ] In the same migration's `up()`, run the backfill SQL after the column is created.
- [ ] In `down()`, drop the foreign key, drop the index, drop the column.

#### Task 1.2 — Create migration: NOT NULL on `appointment_services.provider_id`

📁 NEW FILE: `database/migrations/2026_05_25_000002_make_appointment_services_provider_id_not_null.php`

TODO:
- [ ] `$table->unsignedBigInteger('provider_id')->nullable(false)->change();`
- [ ] In `down()` revert to nullable.
- [ ] Add a pre-check that no nulls remain; abort with clear error if any exist.

#### Task 1.3 — Create migration: `original_start_time` + `original_end_time`

📁 NEW FILE: `database/migrations/2026_05_25_000003_add_original_times_to_appointments_table.php`

TODO:
- [ ] Add `dateTime('original_start_time')->nullable()->after('end_time')`.
- [ ] Add `dateTime('original_end_time')->nullable()->after('original_start_time')`.
- [ ] (Optional) Add index on `original_start_time`.
- [ ] In `down()`, drop both columns.

#### Task 1.4 — Verification

TODO:
- [ ] Run `php artisan migrate` on local PostgreSQL test database.
- [ ] Run a manual SELECT to assert all existing `appointment_services` rows now have a non-null `provider_id` matching their parent `appointment.provider_id`.
- [ ] Run `php artisan migrate:rollback` and confirm no errors.

---

### Phase 2 — Model Layer & Observers

#### Task 2.1 — Update `AppointmentService` model

📁 EDIT: `app/Models/AppointmentService.php`

TODO:
- [ ] Add `'provider_id'` to `$fillable` array (line 14).
- [ ] Add `provider()` relation method:
  ```php
  public function provider(): BelongsTo
  {
      return $this->belongsTo(User::class, 'provider_id');
  }
  ```
- [ ] (Scenario B only) Add `'start_time'` and `'end_time'` to `$fillable` and to `$casts` as `'datetime'`.
- [ ] Update the `boot::creating` to copy `provider_id` to the parent appointment if Scenario A AND it's the first service (handled by observer instead — see Task 2.3).

#### Task 2.2 — Update `Appointment` model

📁 EDIT: `app/Models/Appointment.php`

TODO:
- [ ] Add `'original_start_time'`, `'original_end_time'` to `$fillable` (line 21–42 array).
- [ ] Add casts for both as `'datetime'` in `$casts` (line 45–56).
- [ ] Add accessor:
  ```php
  public function getWasPushedAttribute(): bool
  {
      return ! is_null($this->original_start_time);
  }
  ```
- [ ] Add accessor that returns minutes of total shift:
  ```php
  public function getShiftMinutesAttribute(): ?int
  {
      if (! $this->was_pushed) return null;
      return $this->original_start_time->diffInMinutes($this->start_time);
  }
  ```
- [ ] Add helper scope:
  ```php
  public function scopePushed($query) {
      return $query->whereNotNull('original_start_time');
  }
  ```
- [ ] (Scenario B only) Remove `provider()` relation, replace with `providers()` BelongsToMany through `appointment_services`. Remove `'provider_id'` from `$fillable`.

#### Task 2.3 — Create observer (Scenario A only)

📁 NEW FILE: `app/Observers/AppointmentServiceObserver.php`

Skeleton:
```php
namespace App\Observers;

use App\Models\AppointmentService;
use App\Services\AppointmentRecalculator;

class AppointmentServiceObserver
{
    public function saved(AppointmentService $aptService): void
    {
        app(AppointmentRecalculator::class)
            ->syncPrimaryProvider($aptService->appointment);
    }

    public function deleted(AppointmentService $aptService): void
    {
        $appointment = $aptService->appointment;
        if ($appointment && $appointment->exists) {
            app(AppointmentRecalculator::class)
                ->syncPrimaryProvider($appointment);
        }
    }
}
```

TODO:
- [ ] Implement class as above.
- [ ] Register in `app/Providers/AppServiceProvider.php` `boot()`:
  ```php
  \App\Models\AppointmentService::observe(\App\Observers\AppointmentServiceObserver::class);
  ```
- [ ] Wrap each call with a guard against infinite recursion (the recalculator must use `Appointment::withoutEvents(...)` when writing back).

---

### Phase 3 — AppointmentRecalculator Service

📁 NEW FILE: `app/Services/AppointmentRecalculator.php`

**Responsibility:** Single source of truth for "recompute everything on an appointment from its `appointment_services` rows".

#### Task 3.1 — Method `recalculate(Appointment $appointment): void`

TODO:
- [ ] Load `$appointment->load('services_record')`.
- [ ] If no services → throw `\LogicException('Cannot recalculate appointment with zero services')`.
- [ ] Compute:
  - `start_time` = min of all `services_record.start_time` if Scenario B, else compute sequentially from `appointment.start_time + cumulative durations`. *In Scenario A we add a per-service start_time computed in memory using sequence_order and cumulative durations on the same provider.*
  - `end_time` = max of all services' end_time.
  - `duration_minutes` = `end_time - start_time` in minutes (wall-clock total, NOT sum of durations, because services for different providers may run in parallel).
- [ ] Use `BookingService::calculateTotals` (extract its logic into a public helper of `AppointmentRecalculator` to avoid circular dependency — or inject `BookingService` itself).
- [ ] Persist via `$appointment->update([...])`.

#### Task 3.2 — Method `syncPrimaryProvider(Appointment $appointment): void` (Scenario A only)

TODO:
- [ ] Load first service by `sequence_order ASC`.
- [ ] If `$appointment->provider_id !== $first->provider_id`:
  ```php
  Appointment::withoutEvents(fn() => $appointment->update(['provider_id' => $first->provider_id]));
  ```

#### Task 3.3 — Method `resequence(Appointment $appointment): void`

After adding a new service, the `sequence_order` must be recomputed so services are 1, 2, 3, … in chronological start order across the whole appointment.

TODO:
- [ ] Load all `services_record` for the appointment.
- [ ] Sort by computed start_time ascending.
- [ ] Iterate and assign `sequence_order = $i + 1`.
- [ ] Save each with `withoutEvents` to avoid recursive observer fires.

#### Task 3.4 — Method `updateInvoiceItems(Appointment $appointment): void`

TODO:
- [ ] Load `$appointment->invoice`.
- [ ] If no invoice exists → no-op (will be created at payment time).
- [ ] If invoice exists AND is `DRAFT`:
  - Delete all current `InvoiceItem`s of this invoice (use `withoutEvents` on Invoice to avoid double-recalc; but items' own observers will fire `calculateTotals` after the last insert).
  - For each `appointment_service`, create an `InvoiceItem` using the same logic as `InvoiceService::createInvoiceItems` (reverse-tax via `TaxCalculatorService`).
  - Allow the `saved` observer on `InvoiceItem` to recompute invoice totals automatically.
- [ ] If invoice is `PAID` → throw `\DomainException('Cannot modify a paid invoice')` (defensive — should never reach here).

---

### Phase 4 — AppointmentServiceAppender Service

📁 NEW FILE: `app/Services/AppointmentServiceAppender.php`

**Responsibility:** Public entry point for "add a service to an existing appointment". Orchestrates validation → conflict detection → choice routing → persistence.

#### Task 4.1 — Method `prepare(Appointment $appointment, array $payload): AddServicePreview`

`AddServicePreview` is a small DTO that contains:
```
- bool $canAddWithoutConflict
- array $blockingErrors  // hard rejects: provider doesn't offer service, time-off, etc.
- ?int $maxAllowedDurationMinutes  // for shrink option (null if not applicable)
- ?CascadePushPlan $pushPlan       // for push option (null if no push possible)
- string $proposedStartTime
- string $proposedEndTime
- int $proposedDurationMinutes
```

TODO:
- [ ] Validate appointment is `PENDING`. Else throw.
- [ ] Validate appointment is not paid. Else throw.
- [ ] Validate the requested service exists and is active.
- [ ] Validate the requested provider exists, is active, and offers the service.
- [ ] Validate `start_time` is within the salon open hours for that date.
- [ ] Validate provider has a working schedule for that day-of-week, and the requested window is inside their shift.
- [ ] Validate no full-day time off for provider on that date.
- [ ] Validate no hourly time off overlaps the requested window.
- [ ] **Internal-collision check:** ensure the new service does not overlap with the appointment's own existing services on the same provider, and that it satisfies the no-gap rule (must touch a sibling service or be the new first / last).
- [ ] **External-collision check:** find the smallest start_time among the provider's *other* bookings on that date that overlap the proposed window (or follow it within 0 minutes). Call this `nextConflict`.
  - If no conflict → set `canAddWithoutConflict = true`.
  - Else:
    - Compute `maxAllowedDurationMinutes = nextConflict.start_time - proposedStart` (in minutes). If ≤ 0 → no shrink possible.
    - Delegate to `BookingPushService::buildCascadePlan(provider, proposedEnd, list_of_subsequent_bookings)` → get `CascadePushPlan`.
- [ ] Return `AddServicePreview`.

#### Task 4.2 — Method `commitNoConflict(Appointment $appointment, array $payload): AppointmentService`

TODO:
- [ ] Wrap in `DB::transaction`.
- [ ] `Appointment::lockForUpdate()->find($appointment->id)`.
- [ ] Re-run `prepare()` inside the transaction (defensive — the world may have changed since the preview).
- [ ] If preview now shows conflict → throw and rollback.
- [ ] Insert new `AppointmentService` row with `provider_id`, computed `start_time` (Scenario B) or none (Scenario A), `duration_minutes`, `price`, `sequence_order = 0` (will be re-sequenced).
- [ ] Call `AppointmentRecalculator::resequence($appointment)`.
- [ ] Call `AppointmentRecalculator::recalculate($appointment)`.
- [ ] Call `AppointmentRecalculator::updateInvoiceItems($appointment)`.
- [ ] Return the new `AppointmentService`.

#### Task 4.3 — Method `commitWithShrink(Appointment $appointment, array $payload, int $maxAllowedDurationMinutes): AppointmentService`

TODO:
- [ ] Override `duration_minutes` in `$payload` to `min(payload.duration, $maxAllowedDurationMinutes)`.
- [ ] Delegate to `commitNoConflict()`.

#### Task 4.4 — Method `commitWithPush(Appointment $appointment, array $payload, CascadePushPlan $plan): AppointmentService`

TODO:
- [ ] Wrap in `DB::transaction`.
- [ ] Lock the host appointment + every appointment in `$plan->affectedAppointmentIds()` with `lockForUpdate`.
- [ ] **Re-validate the plan inside the lock** by re-running the push computation. If the plan diverges (because reality changed) → throw and rollback.
- [ ] For each affected appointment in chronological order (apply push from earliest to latest):
  - If `$appointment->original_start_time` is `NULL`, set it to current `start_time` (snapshot).
  - If `$appointment->original_end_time` is `NULL`, set it to current `end_time` (snapshot).
  - Update `start_time = old + delta`, `end_time = old + delta`.
  - For each of its `appointment_services` rows, in Scenario B also update their `start_time`/`end_time` by the same delta.
  - Re-run `AppointmentRecalculator::updateInvoiceItems` (no-op if no DRAFT invoice — totals don't change since prices unchanged, but worth refreshing the recorded times if items reference dates).
- [ ] Insert the new service (same as `commitNoConflict` body, no shrink).
- [ ] Resequence + recalculate + invoice items.
- [ ] Return the new `AppointmentService`.

---

### Phase 5 — BookingPushService — Cascade Push Algorithm

📁 NEW FILE: `app/Services/BookingPushService.php`

#### Task 5.1 — DTO: `CascadePushPlan`

```php
class CascadePushPlan {
    /** @var CascadePushStep[] */
    public array $steps;
    public bool $feasible;
    public ?string $rejectionReason;

    public function affectedAppointmentIds(): array;
    public function totalAffected(): int;
}

class CascadePushStep {
    public int $appointmentId;
    public string $appointmentNumber;
    public Carbon $oldStart;
    public Carbon $oldEnd;
    public Carbon $newStart;
    public Carbon $newEnd;
    public int $deltaMinutes;
}
```

#### Task 5.2 — Method `buildCascadePlan(User $provider, Carbon $newServiceEnd, Carbon $date): CascadePushPlan`

The algorithm:

```
1. cursor = newServiceEnd  (the moment after which there must be no conflict)
2. plan = empty list
3. Load all subsequent appointments of $provider on $date where start_time < cursor + (any non-trivial buffer)
   AND ordered by start_time ASC.
   These are "candidates" for pushing.
4. For each candidate in order:
   a. If candidate.start_time >= cursor → done, no further push needed. Break.
   b. If candidate is paid OR completed → plan.feasible = false; reason = "Cannot push paid booking #X"; return.
   c. delta = cursor - candidate.start_time   (positive minutes)
   d. tentativeNewStart = candidate.start_time + delta = cursor
   e. tentativeNewEnd = candidate.end_time + delta
   f. Validate boundaries:
      - tentativeNewEnd must not exceed provider's work_end for that date.
      - tentativeNewEnd must not exceed salon close_time.
      - tentativeNewStart..tentativeNewEnd must not overlap any hourly time off of provider on that date
        (already-pushed candidates excluded).
      If any boundary check fails → feasible = false; reason; return.
   g. Append CascadePushStep(candidate.id, candidate.start_time, candidate.end_time, tentativeNewStart, tentativeNewEnd, delta) to plan.
   h. cursor = tentativeNewEnd
   (No need to push more than necessary: if the next candidate already starts at >= cursor, the cascade halts.)
5. If steps.length > MAX_CASCADE_DEPTH (50) → feasible = false; reason "Too many cascading shifts"; return.
6. feasible = true; return plan.
```

#### Task 5.3 — Method `executePlan(CascadePushPlan $plan): void`

TODO:
- [ ] (Called inside an open transaction from `AppointmentServiceAppender::commitWithPush`.)
- [ ] Iterate `$plan->steps` and apply (snapshot original times if null, then update start/end).
- [ ] Touch each appointment to log the push in `notes` or in a separate `push_log` table (out of scope for now; just rely on `original_*` columns).

#### Task 5.4 — Edge tests for the algorithm

Cases to plan for in unit tests:

| Case | Setup | Expected |
|------|-------|----------|
| No conflict | Next booking starts after newServiceEnd | feasible=true, steps=[] |
| Single push | One conflicting booking | feasible=true, 1 step |
| Cascade × 3 | Three back-to-back conflicting bookings | 3 steps, last cursor matches last new_end |
| Halt at gap | After pushing 1, next booking already past cursor | 1 step only (not all bookings push) |
| Blocked by paid | Conflicting booking is paid | feasible=false |
| Blocked by work end | Push would exceed shift end | feasible=false |
| Blocked by time off | Push would land inside hourly time off | feasible=false |
| Blocked by salon close | Push would exceed close_time | feasible=false |
| Depth limit | 51 cascading bookings | feasible=false |

---

### Phase 6 — Validation Layer Extensions

#### Task 6.1 — Extend `BookingValidationService`

📁 EDIT: `app/Services/BookingValidationService.php`

TODO:
- [ ] Add optional `?int $excludeAppointmentId = null` parameter to `validateTimeSlotAvailability()`. When non-null, the conflict query adds `->where('id', '!=', $excludeAppointmentId)`. This is needed when adding a service so the appointment doesn't conflict with itself.
- [ ] Add new public method:
  ```php
  public function validateAddedServiceFitsAppointment(
      Appointment $appointment,
      User $provider,
      Service $service,
      Carbon $startTime,
      Carbon $endTime
  ): void
  ```
  which:
  - Checks no overlap with the *same provider's* services already in this appointment.
  - Checks the no-gap rule: the new service's window must touch a sibling service's window or extend the chain (start ==  current min start - duration OR start == current max end).
  - Note: gaps allowed across *different* providers (they may serve simultaneously).
- [ ] Add unit tests.

#### Task 6.2 (Scenario B only) — Rewrite conflict query to use `appointment_services`

See Section 8.2 Step B-2.

---

### Phase 7 — Permission Wiring

#### Task 7.1 — Seed the new permission

📁 EDIT: `database/seeders/RoleSeeder.php`

TODO:
- [ ] Locate the `permissions` array (already contains `StaffDashboard:access`).
- [ ] Add `'appointment.add-service'`.
- [ ] Grant it to the `admin` role by default.
- [ ] (Optional) Also grant to `provider` if business decides — leave as commented suggestion in the seeder.

#### Task 7.2 — Run seeder & verify

TODO:
- [ ] `php artisan db:seed --class=RoleSeeder`.
- [ ] Verify permission exists in `permissions` table.

#### Task 7.3 — Enforce in Livewire methods

📁 EDIT: `app/Livewire/StaffDashboard.php`

TODO:
- [ ] At the start of every new add-service method (`prepareAddServiceForm`, `previewAddService`, `confirmAddService*`):
  ```php
  abort_unless(auth()->user()?->can('appointment.add-service'), 403);
  ```

#### Task 7.4 — Hide the UI affordance for unauthorized users

📁 EDIT: `resources/views/livewire/staff-dashboard.blade.php`

TODO:
- [ ] Wrap the "+ Add Service" button with `@can('appointment.add-service')`.

---

### Phase 8 — DashboardService Timeline Refactor

#### Task 8.1 — Rewrite `getTimelineDataFromProviders` in `StaffDashboard.php`

📁 EDIT: `app/Livewire/StaffDashboard.php` (lines 605–692)

The current implementation emits one card per `appointment`, keyed by `appointment.provider_id`. We refactor to emit one card per `appointment_service`, keyed by `(appointment.id, service.provider_id)`.

Algorithm:
```
Load appointments for date and visible providers (current behavior).
For each appointment:
    Group its services_record by provider_id.
    For each provider group:
        Compute groupStart = min(service.start_time) for this provider in this appointment.
        Compute groupEnd   = max(service.end_time) for this provider in this appointment.
        Build a card:
            appointment_id (same!), appointment_number, provider_id (of this card),
            start_time, end_time, duration,
            services (comma-joined names of THIS provider's services in this appointment),
            status, payment_status, total_amount,
            service_color_code (first service of THIS provider),
            was_pushed (bool from appointment.was_pushed),
            shift_minutes (from appointment.shift_minutes),
            original_start_time (from appointment.original_start_time),
            original_end_time (from appointment.original_end_time),
            link_group_id = appointment.id  // used by frontend to draw connector
        Append to appointmentsByProvider[provider_id].
```

In Scenario A, where individual services don't yet have stored start_time / end_time, compute them in memory by walking `services_record` in `sequence_order` and accumulating durations starting from the appointment's `start_time`. **However**, this assumption only works for sequential single-provider services; for true multi-provider services it needs explicit start_time per service.

> ⚠️ **Important deviation:** the no-gap rule constrains services on the *same provider* to be sequential, but across providers they may be parallel. Therefore *each provider's slice* of the appointment is a contiguous chain. We compute slice start = `appointment.start_time + sum(durations of earlier services in this slice)`. The "earlier services in this slice" are those with `sequence_order < current` AND `provider_id = current.provider_id`.

#### Task 8.2 — Update card eager-loading

TODO:
- [ ] In `DashboardService::getAppointmentsForDate`, ensure `services_record.provider` is eager-loaded:
  ```php
  ->with(['services', 'services_record.service', 'services_record.provider', 'customer', 'provider', 'invoice'])
  ```

#### Task 8.3 — Update `getAvailableProvidersForServiceAtTime`

📁 EDIT: `app/Services/DashboardService.php` (lines 221–283)

TODO:
- [ ] Update the conflict query against `Appointment::...` so it considers ANY appointment where the provider has at least one `appointment_service` with `provider_id = $provider->id` AND overlapping window.
- [ ] In Scenario B, query through `appointment_services` directly.

---

### Phase 9 — StaffDashboard Livewire Methods

📁 EDIT: `app/Livewire/StaffDashboard.php`

#### Task 9.1 — New public state

TODO:
- [ ] `public bool $showAddServiceForm = false;`
- [ ] `public ?int $addServiceForAppointmentId = null;`
- [ ] `public array $addServiceForm = ['category_id' => null, 'service_id' => null, 'provider_id' => null, 'start_time' => '', 'duration' => 0, 'price' => 0.0];`
- [ ] `public bool $showConflictDialog = false;`
- [ ] `public array $conflictDialogData = [];` (will hold the preview DTO as array)

#### Task 9.2 — Method `prepareAddServiceForm(int $appointmentId)`

TODO:
- [ ] Check permission.
- [ ] Set `$this->addServiceForAppointmentId = $appointmentId;`
- [ ] Reset `$this->addServiceForm`.
- [ ] Set `$this->showAddServiceForm = true;`.
- [ ] Hide the appointment modal so the form takes focus.

#### Task 9.3 — Method `closeAddServiceForm()`

TODO:
- [ ] `$this->showAddServiceForm = false;`
- [ ] `$this->addServiceForAppointmentId = null;`
- [ ] Reset form.

#### Task 9.4 — Method `previewAddService()`

TODO:
- [ ] Check permission.
- [ ] Load appointment.
- [ ] Validate form filled.
- [ ] `try { $preview = AppointmentServiceAppender::prepare($appointment, $payload); }`
- [ ] If `$preview->canAddWithoutConflict` → call `confirmAddService()` directly.
- [ ] Else open Conflict Dialog with serialized preview.

#### Task 9.5 — Method `confirmAddService()` (no-conflict path)

TODO:
- [ ] `$service = $appender->commitNoConflict($appointment, $payload);`
- [ ] `$this->dispatch('notify', type: 'success', message: __('dashboard.add_service.added'));`
- [ ] `$this->closeAddServiceForm();`
- [ ] `$this->dispatch('refreshTimeline');`

#### Task 9.6 — Method `confirmAddServiceWithShrink(int $maxDurationMinutes)`

TODO:
- [ ] Override duration in payload.
- [ ] `$appender->commitWithShrink(...)`.
- [ ] Notify + close + refresh.

#### Task 9.7 — Method `confirmAddServiceWithPush()`

TODO:
- [ ] Rebuild plan via `AppointmentServiceAppender::prepare` (since stored preview may be stale).
- [ ] If still feasible → `$appender->commitWithPush(...)`.
- [ ] If newly infeasible → reopen Conflict Dialog with new data.
- [ ] Notify + close + refresh.

#### Task 9.8 — Method `closeConflictDialog()`

TODO:
- [ ] Reset state, return user to the form.

---

### Phase 10 — Blade UI: Add Service Form + Conflict Dialog + Timeline Cards

📁 EDIT: `resources/views/livewire/staff-dashboard.blade.php` (and new partials)

#### Task 10.1 — "+ Add Service" Button in Appointment Modal

TODO:
- [ ] Locate the Appointment Modal section (line 626).
- [ ] Inside the modal body, after the "Edit Time" block (around line 749), add:
  ```blade
  @can('appointment.add-service')
  @if ($selectedAppointment->status->value === 0)
  <div class="border-t pt-4">
      <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">
          {{ __('dashboard.add_service.section_title') }}
      </h4>
      <button wire:click="prepareAddServiceForm({{ $selectedAppointment->id }})"
              class="w-full px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200">
          + {{ __('dashboard.add_service.button') }}
      </button>
  </div>
  @endif
  @endcan
  ```

#### Task 10.2 — Inline Add-Service Form

📁 NEW FILE: `resources/views/livewire/partials/add-service-form.blade.php`

TODO:
- [ ] Modal-like overlay (use the same `modal-overlay` class).
- [ ] Form fields:
  - Category select (uses `$preloadedData['categories']`).
  - Service select (filtered by category from `$preloadedData['services']`).
  - Start time input with `step="{{ $timelineScale * 60 }}"` (consistent with current modal).
  - Provider select — auto-populated by a Livewire computed property `availableProvidersForAddService()` based on the current form state (service_id, start_time, duration).
  - Duration display (read-only, taken from selected service).
  - Price input (editable).
- [ ] Buttons: "Cancel" → `closeAddServiceForm`, "Add" → `previewAddService`.
- [ ] Include the partial conditionally from the main view: `@if ($showAddServiceForm) @include('livewire.partials.add-service-form') @endif`.

#### Task 10.3 — Conflict Dialog

📁 NEW FILE: `resources/views/livewire/partials/conflict-dialog.blade.php`

TODO:
- [ ] Modal-style overlay.
- [ ] Display:
  - Warning icon + headline `dashboard.conflict_dialog.title`.
  - Description: "The proposed time overlaps with one or more subsequent bookings."
  - Section "Option 1 — Shrink" (only if `maxAllowedDurationMinutes > 0`):
    - Text: `"Max allowed duration here: X min"`.
    - Button: "Add with shrunken duration" → `wire:click="confirmAddServiceWithShrink({{ $maxAllowed }})"`.
  - Section "Option 2 — Push" (only if `pushPlan.feasible`):
    - Subheader: "Affected bookings:"
    - Table of `pushPlan.steps`: appointment number, old time → new time, delta minutes.
    - Footer line: "Total: {steps.length} booking(s) shifted by {sum(delta)} minutes."
    - Button: "Push & Add" → `wire:click="confirmAddServiceWithPush"`.
  - Section "Cancel" — always shown.
  - If both options unfeasible (no shrink room AND push blocked):
    - Display rejection reason.
    - Only show "Cancel" button.

#### Task 10.4 — Timeline Card Refactor

TODO:
- [ ] In the timeline rendering Blade section (find the loop iterating `timelineData.appointments[providerId]`):
  - Cards are already keyed; now they include `link_group_id` (= appointment id), `was_pushed`, `shift_minutes`, `original_start_time`.
- [ ] Add a small icon `🔗` if the card belongs to a multi-provider appointment (`link_group_id` appears more than once across visible providers' card lists). Compute this client-side in Alpine: `linkedAppointmentIds()` returns the set of appointment IDs that appear in multiple provider columns. Cards whose `link_group_id` is in that set show the link icon.
- [ ] Add a "shifted" badge (e.g., `⏱`) when `was_pushed` is true, with `title="{{ __('dashboard.timeline.shifted_tooltip', ['original' => $card.original_start_time]) }}"`.

#### Task 10.5 — Visual Connector Line (Optional Polish)

TODO (deferred to a later polish phase if time-boxed):
- [ ] Add an SVG overlay layer in the timeline grid.
- [ ] After Alpine renders cards, compute pixel positions of cards sharing `link_group_id`.
- [ ] Draw a thin dashed line between them in the SVG layer.

#### Task 10.6 — Appointment Modal Services List (Update)

TODO:
- [ ] Change the line in the appointment modal that currently joins service names with commas:
  ```php
  $selectedAppointment->services_record->map(fn($s) => $s->service_name)->implode(', ')
  ```
- [ ] Replace with a small bullet list that shows each service with its provider:
  ```blade
  <ul class="space-y-1 text-sm">
    @foreach ($selectedAppointment->services_record->sortBy('sequence_order') as $s)
        <li class="flex justify-between">
            <span>{{ $s->service_name }}</span>
            <span class="text-xs text-gray-400">{{ $s->provider?->full_name }} · {{ $s->duration_minutes }}m</span>
        </li>
    @endforeach
  </ul>
  ```

---

### Phase 11 — Existing Booking Path Adjustments

#### Task 11.1 — `BookingService::createBooking` writes `provider_id` to each service

📁 EDIT: `app/Services/BookingService.php` (line 94–104)

TODO:
- [ ] When creating each `AppointmentService` row in the loop, add `'provider_id' => $serviceData['provider_id']` to the array.
- [ ] Verify backfill migration has already populated old rows.

#### Task 11.2 — Quick verification of `AppointmentResource` API response

📁 EDIT: `app/Http/Resources/AppointmentResource.php`

TODO:
- [ ] Confirm response shape still works (Scenario A: yes, untouched).
- [ ] (Optional) Add `'providers' => $this->services_record->pluck('provider_id')->unique()->values()` for forward-compat.

---

### Phase 12 — Scenario B Only — Filament/API/Reports Refactor

(Detailed enumeration in Section 8.2 Step B-3.)

#### Task 12.1 — Migrate `appointmentsAsProvider()` relation

TODO:
- [ ] Replace `hasMany` with `hasManyThrough` (see Step B-1).
- [ ] Run a quick grep for `appointmentsAsProvider` to find all callers.
- [ ] Each caller may need adjustment (e.g., `->distinct()` is now implicit; counts will differ).

#### Task 12.2 — Migrate `BookingValidationService::validateTimeSlotAvailability`

(See Step B-2 — requires `appointment_services.start_time` and `end_time` columns first.)

TODO:
- [ ] Land migration `2026_05_25_000005_add_times_to_appointment_services_table.php`.
- [ ] Backfill from `appointments.start_time`/`end_time` (correct only for single-service appointments; for multi-service this fixes per-service times on add).
- [ ] Make NOT NULL.
- [ ] Rewrite the conflict query.

#### Task 12.3 — Migrate `ReportsService`

📁 EDIT: `app/Services/ReportsService.php` (lines 86–117 and 302–311)

TODO:
- [ ] `getRevenuePerProvider` — change FROM `appointments` → JOIN `appointment_services` and split total_amount per service. **Decision needed:** is revenue attributed per service (by service.price) or split equally across providers? Recommendation: per-service price.
- [ ] `getServicesPerProvider` — already joins; just drop reliance on `appointments.provider_id`.
- [ ] `getWorkedMinutes` — sum `appointment_services.duration_minutes` grouped by `provider_id`.

#### Task 12.4 — Migrate `ProviderReportService`

📁 EDIT: `app/Services/ProviderReportService.php` (all 13 occurrences of `provider_id`)

TODO for each occurrence: rewrite the query to filter through `appointment_services`.

#### Task 12.5 — Migrate Filament resources

For each of the ~10 Filament files listed in Section 8.2 Step B-3:
- [ ] Update the table column / form field / relation manager / widget query.
- [ ] Manually open the page in the admin panel and verify.

#### Task 12.6 — Migrate API resources

- [ ] Update `AppointmentResource` to expose `providers` array.
- [ ] Update mobile API documentation.
- [ ] Coordinate with mobile team for response shape change.

#### Task 12.7 — Drop `appointments.provider_id`

Once all the above is green:
- [ ] Land migration `2026_05_25_000004_drop_provider_id_from_appointments_table.php`.
- [ ] Run the full test suite. Run the staff dashboard manually.

---

### Phase 13 — Testing

#### Task 13.1 — Unit tests

📁 NEW FILE: `tests/Unit/AppointmentRecalculatorTest.php`

TODO:
- [ ] Recalculate sets correct start/end/duration for single-provider appointment.
- [ ] Recalculate sets correct start/end/duration for multi-provider appointment.
- [ ] Resequence reorders by start time across providers.
- [ ] (Scenario A only) `syncPrimaryProvider` updates `appointments.provider_id`.

📁 NEW FILE: `tests/Unit/BookingPushServiceTest.php`

TODO:
- [ ] Cover every case in Section 5 Task 5.4 table.

📁 NEW FILE: `tests/Unit/AppointmentServiceAppenderTest.php`

TODO:
- [ ] Prepare returns canAddWithoutConflict=true for clear slot.
- [ ] Prepare returns maxAllowed=10 for a 10-minute gap with 20-minute request.
- [ ] CommitNoConflict creates `AppointmentService` row with correct provider_id and sequence_order.
- [ ] CommitWithShrink writes the shrunken duration.
- [ ] CommitWithPush updates all affected bookings and snapshots original times.

#### Task 13.2 — Feature tests (Livewire)

📁 NEW FILE: `tests/Feature/StaffDashboardAddServiceTest.php`

TODO:
- [ ] Admin sees the "+ Add Service" button when viewing a PENDING appointment.
- [ ] Non-admin (without `appointment.add-service` permission) does not see the button.
- [ ] Submitting the form for a free slot adds the service and refreshes the timeline.
- [ ] Submitting for a conflicting slot opens the Conflict Dialog with correct preview.
- [ ] Selecting "shrink" reduces the new service's duration.
- [ ] Selecting "push" shifts all affected bookings.
- [ ] First-time push snapshots original times; second push does not overwrite.
- [ ] Attempting to push a paid booking yields a rejection in the dialog (cannot select Push).

#### Task 13.3 — Concurrency test

📁 NEW FILE: `tests/Feature/AddServiceConcurrencyTest.php`

TODO:
- [ ] Simulate two parallel `commitWithPush` operations on overlapping bookings using two DB transactions.
- [ ] Assert the second one either succeeds with adjusted plan or fails cleanly.

#### Task 13.4 — Manual QA checklist

- [ ] Open dashboard with admin login.
- [ ] Create a fresh PENDING booking (single service, single provider).
- [ ] Open the booking; click "+ Add Service".
- [ ] Add a service in a free slot, different provider → verify two cards appear in timeline (one per provider) sharing the same `appointment_id`.
- [ ] Add a service that conflicts → Conflict Dialog appears → choose Shrink → service added with reduced duration.
- [ ] Add a service that conflicts → choose Push → subsequent booking shifted → original-time tooltip appears on shifted card.
- [ ] Mark a subsequent booking as paid; try to push → push option disabled with explanation.
- [ ] Try to push beyond shift end → push option disabled with explanation.
- [ ] Cancel the host appointment → both cards (multi-provider) disappear together.
- [ ] Pay the host appointment → both cards remain together; invoice contains all services.

---

### Phase 14 — Deployment & Backfill

#### Task 14.1 — Pre-deploy checklist

- [ ] Database backup taken.
- [ ] Run migrations in staging; smoke-test for 24 hours.
- [ ] All unit and feature tests green.
- [ ] PR reviewed by at least one peer; security review for the new permission.

#### Task 14.2 — Deploy order

1. Deploy code with new migrations.
2. Run `php artisan migrate` → `001` (add nullable column + backfill).
3. Verify backfill: `SELECT COUNT(*) FROM appointment_services WHERE provider_id IS NULL;` → must be 0.
4. Run migration `002` (NOT NULL).
5. Run migration `003` (original times).
6. Run `php artisan db:seed --class=RoleSeeder` to add the new permission.
7. Clear Laravel + Filament caches.
8. (Scenario B) only after a successful soak period: run `004` (drop `appointments.provider_id`).

#### Task 14.3 — Post-deploy verification

- [ ] Open `/dashboard` as admin; verify timeline renders correctly.
- [ ] Smoke-test add-service flow end-to-end.
- [ ] Monitor application logs for `Failed to push booking` or `Recalculator` errors for 48 hours.

---

## 11. The Smart Push Algorithm — Formal Specification

### 11.1 Inputs

- `provider` — the provider whose timeline is being modified.
- `date` — the day in question.
- `newWindow = [proposedStart, proposedEnd]` — the new service's requested time range.

### 11.2 Pseudocode

```
function buildCascadePlan(provider, date, newWindow):
    plan = []
    cursor = newWindow.end

    subsequent = Appointment::query()
        .where('appointment_date', date)
        .where(condition_for_provider_in_either_scenario)  // see note below
        .where('created_status', 1)
        .whereNotIn('status', [USER_CANCELLED, ADMIN_CANCELLED])
        .where('start_time', '<', cursor)  // overlaps the cursor
        .orderBy('start_time', 'asc')
        .lockForUpdate()
        .get()

    # Filter to those whose effective per-provider window overlaps the new window
    # In Scenario B, look at appointment_services to find the provider-specific window
    # In Scenario A (when not using per-service times), use appointment.start_time/end_time

    for each apt in subsequent:
        aptProviderWindow = computeProviderWindow(apt, provider)
        if aptProviderWindow.start >= cursor:
            break  # no further conflict

        if apt.payment_status in [PAID_ONLINE, PAID_ONSTIE_CASH, PAID_ONSTIE_CARD]
           or apt.status == COMPLETED:
            return CascadePushPlan(feasible=false,
                                   rejectionReason="Cannot push paid booking #" + apt.number)

        delta = cursor - aptProviderWindow.start  # minutes
        newAptStart = apt.start_time + delta
        newAptEnd = apt.end_time + delta

        # Boundary checks
        if newAptEnd > provider.work_end_for_date(date):
            return CascadePushPlan(feasible=false,
                                   rejectionReason="Would exceed provider work hours")
        if newAptEnd > salon.close_for_date(date):
            return CascadePushPlan(feasible=false,
                                   rejectionReason="Would exceed salon close time")
        if overlaps_with_time_off(provider, newAptStart, newAptEnd, date):
            return CascadePushPlan(feasible=false,
                                   rejectionReason="Would land inside time off")

        plan.append(CascadePushStep(apt.id, apt.start_time, apt.end_time,
                                     newAptStart, newAptEnd, delta))
        cursor = newAptEnd

        if len(plan) > MAX_DEPTH:
            return CascadePushPlan(feasible=false,
                                   rejectionReason="Too many cascading shifts (> 50)")

    return CascadePushPlan(feasible=true, steps=plan, rejectionReason=null)
```

### 11.3 Edge: Determining `computeProviderWindow`

For a multi-provider appointment, the provider's "slice" is the contiguous block of `appointment_services` rows for that provider. We compute:

```
providerServices = apt.services_record.where('provider_id', provider.id).sortBy('sequence_order')
sliceStart = ... // smallest start time among providerServices
sliceEnd   = ... // largest end time among providerServices
```

In Scenario B (with stored times on `appointment_services`), this is direct.
In Scenario A, compute by walking `sequence_order` across the appointment and accumulating durations from `appointment.start_time`, then take the per-provider min/max.

### 11.4 Execution

`executePlan(plan)` is invoked inside the same transaction as the new service insert. For each step:

```
apt = Appointment::lockForUpdate()->find(step.appointmentId)
if apt.original_start_time is NULL:
    apt.original_start_time = apt.start_time
if apt.original_end_time is NULL:
    apt.original_end_time = apt.end_time
apt.start_time = step.newStart
apt.end_time = step.newEnd
apt.save()

// In Scenario B, also update each appointment_services row's start/end by the same delta.
```

### 11.5 Why "minimum push per booking" works

Because each cascade step pushes the next booking only by the difference between the new cursor and its current start, the algorithm naturally:
- Stops as soon as one booking happens to already start after the cursor.
- Never pushes any booking more than the gap demands.

This satisfies the user's "don't push more than needed" requirement.

---

## 12. UX Flow Detailed

### 12.1 Happy Path (No Conflict)

```
1. Manager clicks an appointment card in the timeline.
2. Appointment Modal opens (existing modal).
3. Below the "Edit Time" section: "+ Add Service" button (NEW).
4. Manager clicks "+ Add Service".
   → Appointment Modal hides.
   → Add Service Form opens (NEW).
5. Manager picks: category → service → start_time → provider auto-loads available list → picks provider.
6. Manager clicks "Add".
   → `previewAddService()` runs.
   → No conflict found → server commits immediately.
7. Toast: "Service added".
8. Form closes; appointment modal optionally reopens (or just closes).
9. Timeline refreshes; new card appears in the new provider's column.
```

### 12.2 Conflict Path — Shrink

```
1..5 same as above.
6. Manager clicks "Add".
   → `previewAddService()` returns conflict with maxAllowedDurationMinutes = 10, pushPlan = feasible (3 steps).
7. Conflict Dialog opens.
8. Manager reads: "The next booking starts at 10:40 — max allowed duration here is 10 minutes."
9. Manager clicks "Add with shrunken duration (10m)".
   → `confirmAddServiceWithShrink(10)` runs.
   → Server commits the service with duration_minutes = 10.
10. Toast: "Service added with reduced duration".
11. Timeline refreshes.
```

### 12.3 Conflict Path — Push

```
1..7 same as Shrink path through the Dialog appearing.
8. Manager reviews the affected-bookings preview:
   • #APT-001 → shift 10 min (10:40 → 10:50)
   • #APT-002 → shift 7 min (11:00 → 11:07)
9. Manager clicks "Push & Add".
   → `confirmAddServiceWithPush()` runs.
   → Server re-validates plan inside lock, executes.
10. Toast: "Service added; 2 bookings shifted".
11. Timeline refreshes. Shifted cards now show ⏱ badge.
12. Hovering the badge: "Original time: 10:40 — 11:10".
```

### 12.4 Conflict Path — Reject (Push Blocked)

```
8. Conflict Dialog opens with:
   • Shrink section enabled (max 10m).
   • Push section disabled, showing reason: "Cannot push #APT-002 — it is already paid."
9. Manager either shrinks, cancels, or rethinks.
```

---

## 13. Validation Matrix

| Layer | Validation | Where | Error message key |
|-------|------------|-------|-------------------|
| Permission | `appointment.add-service` | Livewire method top | `dashboard.add_service.errors.permission` |
| Appointment status | `status == PENDING` | `AppointmentServiceAppender::prepare` | `dashboard.add_service.errors.not_pending` |
| Appointment payment | `payment_status == PENDING` | `prepare` | `dashboard.add_service.errors.already_paid` |
| Service exists & active | `service.is_active` | `prepare` | `dashboard.add_service.errors.service_inactive` |
| Provider exists & active | `provider.is_active` | `prepare` | `dashboard.add_service.errors.provider_inactive` |
| Provider offers service | `provider_service` pivot | `prepare` (reuse `BookingValidationService::validateProviderOffersService`) | `dashboard.add_service.errors.provider_no_service` |
| Provider has schedule today | `provider_scheduled_works` | `prepare` | `dashboard.add_service.errors.provider_no_schedule` |
| Window inside shift | `start >= shift.start && end <= shift.end` | `prepare` | `dashboard.add_service.errors.outside_shift` |
| No full-day time off | `ProviderTimeOff::TYPE_FULL_DAY` | `prepare` | `dashboard.add_service.errors.full_day_off` |
| No hourly time-off overlap | `ProviderTimeOff::TYPE_HOURLY` | `prepare` | `dashboard.add_service.errors.hourly_off_conflict` |
| Inside salon open hours | `SalonSchedule.is_open && between open_time/close_time` | `prepare` | `dashboard.add_service.errors.salon_closed` |
| Window not in the past | `start > now()` | `prepare` (skipped if appointment is today and time was already in the past — manager may add retroactively?) | `dashboard.add_service.errors.in_past` |
| No internal collision | New window doesn't overlap same-provider services already in this appointment | `validateAddedServiceFitsAppointment` | `dashboard.add_service.errors.internal_collision` |
| No gap on same provider | New window touches a sibling or extends the chain | `validateAddedServiceFitsAppointment` | `dashboard.add_service.errors.gap_not_allowed` |
| External conflict → push or shrink | Subsequent provider bookings | `BookingPushService::buildCascadePlan` | `dashboard.conflict_dialog.headline` |

> **Decision needed (carry to Open Questions):** should "Add Service" be allowed when the proposed time is in the past (for retroactive bookkeeping)? Recommendation: **allow** for managers (since `appointment.add-service` is a privileged operation), since they often log walk-ins after the fact.

---

## 14. Edge Cases & Error Handling

| # | Edge case | Handling |
|---|-----------|----------|
| E1 | Two managers add a service to the same appointment simultaneously | Pessimistic lock on the appointment row prevents both from succeeding; the second sees a stale-state error and is asked to re-preview. |
| E2 | New service's provider goes on time-off between preview and confirm | Re-validation inside lock catches it; Conflict Dialog reopens with new reason. |
| E3 | The host appointment is paid between preview and confirm | Re-validation catches it; operation aborted, user notified. |
| E4 | Cascade pushes a booking whose customer just received a reminder push notification | Out of scope — no notification on push (per FR-18). Mention in customer interaction guidelines. |
| E5 | Push would shift a booking past midnight | Salon schedules don't span midnight; the check against salon close time catches this. |
| E6 | Backfill migration encounters an `appointment_services` row whose parent appointment was hard-deleted | The migration is an `UPDATE ... JOIN`; orphaned rows are skipped. After the migration we run a sanity query for null `provider_id` and either fix manually or hard-delete orphans. |
| E7 | `services_record` somehow has services sorted by `sequence_order` differently from chronological order | After every add we run `AppointmentRecalculator::resequence` which guarantees sequence_order matches chronology. |
| E8 | Adding a service makes the host appointment span > 8 hours | No system limit, but flag for UX review later. |
| E9 | The added service's `provider_id` equals one already in the appointment | Allowed — same provider may serve multiple services in same booking. No-gap rule applies between same-provider services. |
| E10 | The host appointment has zero services (which shouldn't be possible) | `AppointmentRecalculator::recalculate` throws `\LogicException`; operation aborts. |
| E11 | Race condition: the appointment was cancelled by an admin between dashboard render and click | Re-load + status check in `prepare()` catches it; user sees a clear error. |
| E12 | `original_start_time` already populated (booking previously pushed) and a NEW push happens | We do NOT overwrite the original snapshot. The `original_*` always reflects the first pre-push time, as per requirement. |
| E13 | The new service has `price = 0` because Service has no price set | Validate `price > 0` (defensive). Reject with clear error. |
| E14 | The new service's duration exceeds 8 hours | Add a soft cap of 480 minutes; reject above (configurable). |
| E15 | Push delta is so large it shifts a booking outside the visible day | Salon close-time guard prevents this. |
| E16 | Appointment has `created_status = 0` (unconfirmed online booking) | Excluded from cascade (since current code filters `created_status = 1`). Safe to ignore. |

---

## 15. Permission & Authorization

### 15.1 New permission

- **Name:** `appointment.add-service`
- **Spatie guard:** `web` (Livewire uses session auth)
- **Default assignment:** `admin` role

### 15.2 Seeder change

📁 EDIT: `database/seeders/RoleSeeder.php`

Pseudocode:
```php
$permissions = [
    // ... existing
    'appointment.add-service',
];

foreach ($permissions as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
}

$adminRole->givePermissionTo('appointment.add-service');
// Optionally: $providerRole->givePermissionTo('appointment.add-service');
```

### 15.3 Enforcement points

| Point | Mechanism |
|-------|-----------|
| Livewire method entry | `abort_unless(auth()->user()?->can('appointment.add-service'), 403)` |
| Blade button visibility | `@can('appointment.add-service')` |
| Service class | `AppointmentServiceAppender::prepare` also re-checks defensively (in case called outside Livewire) |

### 15.4 Audit

For now we rely on the `original_*` columns + Laravel logs. A full audit trail (who added what, when) is captured by an info log inside `commit*` methods.

---

## 16. Testing Strategy

### 16.1 Unit Tests

Coverage targets:
- `AppointmentRecalculator` — 100% line coverage of public methods.
- `BookingPushService::buildCascadePlan` — every branch tested with named scenarios (Section 5 Task 5.4).
- `AppointmentServiceAppender` — happy + 4 conflict paths + 3 rejection paths.

### 16.2 Feature Tests

- `tests/Feature/StaffDashboardAddServiceTest.php` — covers UI affordance + form submission flows.
- `tests/Feature/CascadePushIntegrationTest.php` — end-to-end test creating real appointments, attempting various pushes, asserting DB state.

### 16.3 Concurrency Tests

- `tests/Feature/AddServiceConcurrencyTest.php` — uses two simultaneous DB transactions.

### 16.4 Regression

- Run full existing suite. All current tests must remain green.

### 16.5 Manual QA

- Use the checklist in Phase 13 Task 13.4 in staging before production deploy.

---

## 17. Rollback & Recovery Plan

### 17.1 Code rollback

The feature is encapsulated in:
- New service classes (no consumers outside the new Livewire methods).
- New Livewire methods (existing methods untouched).
- New Blade partials (included conditionally).
- New migrations (each with working `down()`).

Code rollback = `git revert` the feature commits. Existing flows remain.

### 17.2 Database rollback

| Migration | Down step | Risk |
|-----------|-----------|------|
| 001 — add `appointment_services.provider_id` | drop column | Loses backfilled provider_id; benign if rolled back before feature use. |
| 002 — NOT NULL | revert nullable | Safe. |
| 003 — `original_*` columns | drop columns | Loses original-time history of any pushed bookings (acceptable for rollback). |
| 004 (Scenario B only) — drop `appointments.provider_id` | re-add column + backfill from `appointment_services` first row | Requires running the inverse backfill carefully. |

### 17.3 Feature flag (optional)

Add a `SalonSetting` key `add_service_enabled` (default `1`). The Livewire `prepareAddServiceForm` checks this before proceeding. To disable: set to `0`, clear cache. The button is hidden + the methods refuse.

### 17.4 Recovery from a bad cascade push

If a push corrupted appointment times:
- The `original_start_time` and `original_end_time` columns let admins write a quick artisan command:
  ```php
  Appointment::pushed()->each(function($a) {
      $a->update([
          'start_time' => $a->original_start_time,
          'end_time' => $a->original_end_time,
          'original_start_time' => null,
          'original_end_time' => null,
      ]);
  });
  ```
- Provide an artisan command `appointments:undo-pushes` that does exactly this for a target date.

---

## 18. Open Questions / Future Work

### 18.1 Open Questions (decide before coding)

1. **Final scenario choice — A or B?** Plan is written for A by default. B costs ~3× more time.
2. **Retroactive add allowed?** Should "add service" be allowed when proposed time is in the past? Recommendation: yes for managers.
3. **Permission to providers?** Should the `provider` role also have `appointment.add-service`, or admin-only?
4. **Visual connector line between cards** — must-have or polish-deferred?
5. **Maximum service duration** — should we cap at 8h, or trust the manager?
6. **Should the host appointment's modal re-open after the new service is added?** Or just close and let the manager click the card again?

### 18.2 Future Work (out of this PR)

- Customer notifications on push (SMS / push / email).
- Drag-and-drop reordering of services inside an appointment.
- Per-service edit (currently only add; deletion/edit deferred).
- Multi-day appointments (Saturday → Sunday spans).
- Refactor `BookingService2` (looks legacy).
- Resurrect `provider_service.custom_duration` (still dead code).
- Fix typo `createDtaftInvoiceFromAppointment` → `createDraftInvoiceFromAppointment` across codebase.
- Scenario B execution (separate roadmap initiative).
- True audit trail for push history (separate `appointment_push_logs` table with who/when/why).
- Reports rewrite for true per-service-provider attribution.

---

## 📌 Appendix A — Translation Keys to Add

📁 EDIT: `lang/en/dashboard.php`, `lang/ar/dashboard.php`, `lang/de/dashboard.php`

```php
'add_service' => [
    'section_title' => 'Add another service',
    'button' => 'Add service',
    'modal_title' => 'Add service to booking',
    'category_label' => 'Category',
    'service_label' => 'Service',
    'start_time_label' => 'Start time',
    'provider_label' => 'Provider',
    'duration_label' => 'Duration',
    'price_label' => 'Price',
    'add_button' => 'Add',
    'cancel_button' => 'Cancel',
    'added' => 'Service added successfully',
    'added_with_shrink' => 'Service added with reduced duration',
    'added_with_push' => 'Service added; subsequent bookings shifted',
    'errors' => [
        'permission' => 'You do not have permission to add services',
        'not_pending' => 'Only pending appointments can be modified',
        'already_paid' => 'Cannot modify a paid appointment',
        'service_inactive' => 'Service is not active',
        'provider_inactive' => 'Provider is not active',
        'provider_no_service' => 'Provider does not offer this service',
        'provider_no_schedule' => 'Provider does not work on this day',
        'outside_shift' => 'Time is outside provider working hours',
        'full_day_off' => 'Provider has a full-day off',
        'hourly_off_conflict' => 'Provider has time off during this slot',
        'salon_closed' => 'Salon is closed at this time',
        'in_past' => 'Time is in the past',
        'internal_collision' => 'Overlaps another service of the same provider in this booking',
        'gap_not_allowed' => 'Services for the same provider must be sequential — no gaps',
    ],
],
'conflict_dialog' => [
    'title' => 'Conflict detected',
    'description' => 'The proposed time overlaps with one or more subsequent bookings.',
    'option_shrink' => 'Shrink service',
    'shrink_max' => 'Maximum allowed duration: :minutes min',
    'shrink_button' => 'Add with :minutes min',
    'option_push' => 'Push subsequent bookings',
    'push_table_header' => 'Affected bookings',
    'push_summary' => ':count bookings shifted by :total minutes total',
    'push_button' => 'Push & Add',
    'push_blocked' => 'Push not possible: :reason',
    'cancel' => 'Cancel',
],
'timeline' => [
    'shifted_badge' => 'Shifted',
    'shifted_tooltip' => 'Originally at :original',
    'linked_badge' => 'Multi-provider booking',
],
```

---

## 📌 Appendix B — Sample DTO Code Skeletons

### CascadePushPlan / CascadePushStep

```php
<?php

namespace App\Services\Push;

use Carbon\Carbon;

final class CascadePushStep
{
    public function __construct(
        public readonly int $appointmentId,
        public readonly string $appointmentNumber,
        public readonly Carbon $oldStart,
        public readonly Carbon $oldEnd,
        public readonly Carbon $newStart,
        public readonly Carbon $newEnd,
        public readonly int $deltaMinutes,
    ) {}

    public function toArray(): array {
        return [
            'appointment_id' => $this->appointmentId,
            'appointment_number' => $this->appointmentNumber,
            'old_start' => $this->oldStart->format('H:i'),
            'old_end' => $this->oldEnd->format('H:i'),
            'new_start' => $this->newStart->format('H:i'),
            'new_end' => $this->newEnd->format('H:i'),
            'delta_minutes' => $this->deltaMinutes,
        ];
    }
}

final class CascadePushPlan
{
    /**
     * @param CascadePushStep[] $steps
     */
    public function __construct(
        public readonly bool $feasible,
        public readonly array $steps = [],
        public readonly ?string $rejectionReason = null,
    ) {}

    public function affectedAppointmentIds(): array {
        return array_map(fn($s) => $s->appointmentId, $this->steps);
    }

    public function totalAffected(): int {
        return count($this->steps);
    }

    public function totalDeltaMinutes(): int {
        return array_sum(array_map(fn($s) => $s->deltaMinutes, $this->steps));
    }

    public function toArray(): array {
        return [
            'feasible' => $this->feasible,
            'rejection_reason' => $this->rejectionReason,
            'steps' => array_map(fn($s) => $s->toArray(), $this->steps),
            'total_affected' => $this->totalAffected(),
            'total_delta_minutes' => $this->totalDeltaMinutes(),
        ];
    }
}
```

### AddServicePreview

```php
<?php

namespace App\Services\AddService;

use App\Services\Push\CascadePushPlan;
use Carbon\Carbon;

final class AddServicePreview
{
    public function __construct(
        public readonly bool $canAddWithoutConflict,
        public readonly array $blockingErrors,
        public readonly ?int $maxAllowedDurationMinutes,
        public readonly ?CascadePushPlan $pushPlan,
        public readonly Carbon $proposedStart,
        public readonly Carbon $proposedEnd,
        public readonly int $proposedDuration,
    ) {}

    public function toArray(): array {
        return [
            'can_add_without_conflict' => $this->canAddWithoutConflict,
            'blocking_errors' => $this->blockingErrors,
            'max_allowed_duration_minutes' => $this->maxAllowedDurationMinutes,
            'push_plan' => $this->pushPlan?->toArray(),
            'proposed_start' => $this->proposedStart->format('H:i'),
            'proposed_end' => $this->proposedEnd->format('H:i'),
            'proposed_duration' => $this->proposedDuration,
        ];
    }
}
```

---

## 📌 Appendix C — Risk Register

| ID | Risk | Probability | Impact | Mitigation |
|----|------|-------------|--------|------------|
| R1 | Backfill migration takes too long on production | Low | Medium | Run during off-hours; split into batches if necessary. |
| R2 | Observer infinite recursion (Scenario A) | Medium | High | Use `withoutEvents` consistently in the recalculator. Cover with unit tests. |
| R3 | Cascade push corrupts times under concurrency | Medium | High | Pessimistic lock + transaction; re-validate plan inside lock. |
| R4 | UI confusion with multi-provider cards | Medium | Medium | Clear linked icon + tooltip. User test with one manager before rollout. |
| R5 | Scope creep into Scenario B during the PR | High | High | Strictly hold to Scenario A; create a follow-up ticket for Scenario B. |
| R6 | Existing tests break due to provider_id semantic change | Medium | Medium | Run full suite early in development; fix incrementally. |
| R7 | Filament admin panel doesn't update its appointment view | Low | Low | Manual QA; if needed, add a small Resource patch. |

---

## 📌 Appendix D — Final Decision Checklist

Before starting implementation, confirm:

- [ ] **Scenario A** is the chosen path (or explicitly choose B and 3× the schedule).
- [ ] Permission name `appointment.add-service` is approved.
- [ ] `original_start_time` / `original_end_time` column names are approved.
- [ ] One card per `appointment_service` (with visual link) for multi-provider is approved.
- [ ] DRAFT invoice updated on add-service (not recreated) is approved.
- [ ] No customer notifications in this milestone is confirmed.
- [ ] Retroactive add (past times) — decision recorded.
- [ ] Maximum service duration — decision recorded.

---

**End of plan.**
