<?php

/**
 * DEL-01 — deleting an appointment from the Filament admin crashed with
 * SQLSTATE 1451: `appointment_services.appointment_id` and
 * `invoices.appointment_id` are RESTRICT foreign keys, and the edit page called
 * a bare `$record->delete()`. Only the StaffDashboard removed the children
 * first. All three surfaces now go through AppointmentDeletionService.
 *
 * ⚠️ SQLite enforces the same foreign keys, so the original crash reproduces
 * here — but `lockForUpdate()` is a no-op, so only the logic of the in-lock
 * re-check is verified, not MySQL's row locking.
 */

use App\Enum\AppointmentStatus;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Exceptions\AppointmentNotDeletableException;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\AppointmentDeletionService;
use App\Services\BookingService;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
});

afterEach(function () {
    Carbon::setTestNow();
});

/** A booking made through the real flow: services + a DRAFT invoice with items. */
function bookForDeletion(string $startTime = '10:00'): Appointment
{
    $salon = test()->salon;

    return app(BookingService::class)->createBooking($salon->customer, [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $salon->service->id,
            'provider_id' => $salon->available->id,
            'start_time' => $startTime,
        ]],
    ]);
}

function deletionService(): AppointmentDeletionService
{
    return app(AppointmentDeletionService::class);
}

function blockedReasons(callable $fn): array
{
    try {
        $fn();
    } catch (AppointmentNotDeletableException $e) {
        return array_column($e->blocked, 'reason');
    }

    throw new RuntimeException('Expected AppointmentNotDeletableException');
}

// ── 1. The crash itself ──────────────────────────────────────────────────────

it('deletes an appointment together with its services and draft invoice', function () {
    $appointment = bookForDeletion();
    $invoice = $appointment->fresh()->invoice;

    expect(AppointmentService::where('appointment_id', $appointment->id)->exists())->toBeTrue()
        ->and($invoice)->not->toBeNull()
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->exists())->toBeTrue();

    deletionService()->delete($appointment);

    expect(Appointment::find($appointment->id))->toBeNull()
        ->and(AppointmentService::where('appointment_id', $appointment->id)->exists())->toBeFalse()
        ->and(Invoice::find($invoice->id))->toBeNull()
        ->and(InvoiceItem::where('invoice_id', $invoice->id)->exists())->toBeFalse();
});

it('still refuses a bare model delete — the RESTRICT keys are the safety net', function () {
    $appointment = bookForDeletion();

    expect(fn () => $appointment->delete())->toThrow(\Illuminate\Database\QueryException::class);
});

// ── 2. The rules ─────────────────────────────────────────────────────────────

it('refuses to delete a paid appointment', function () {
    $appointment = bookForDeletion();
    $appointment->update(['payment_status' => PaymentStatus::PAID_ONSTIE_CASH]);

    expect(blockedReasons(fn () => deletionService()->delete($appointment)))
        ->toBe([AppointmentNotDeletableException::REASON_PAID])
        ->and(Appointment::find($appointment->id))->not->toBeNull();
});

it('treats a finalized invoice as paid even if payment_status disagrees', function () {
    $appointment = bookForDeletion();
    $appointment->invoice->update(['status' => InvoiceStatus::PAID, 'invoice_number' => 'INV-2026-000001']);

    expect(blockedReasons(fn () => deletionService()->delete($appointment)))
        ->toBe([AppointmentNotDeletableException::REASON_PAID])
        ->and(Invoice::where('invoice_number', 'INV-2026-000001')->exists())->toBeTrue();
});

it('refuses to delete a completed appointment', function () {
    $appointment = bookForDeletion();
    $appointment->update(['status' => AppointmentStatus::COMPLETED]);

    expect(blockedReasons(fn () => deletionService()->delete($appointment)))
        ->toBe([AppointmentNotDeletableException::REASON_COMPLETED]);
});

it('refuses to delete a parent that still has an active child', function () {
    $parent = bookForDeletion();
    $child = $this->salon->bookSlot($this->salon->filler, SalonFixture::DATE, '10:00', '11:00');
    $child->update(['parent_appointment_id' => $parent->id]);

    try {
        deletionService()->delete($parent);
        $this->fail('Expected AppointmentNotDeletableException');
    } catch (AppointmentNotDeletableException $e) {
        expect($e->blocked[0]['reason'])->toBe(AppointmentNotDeletableException::REASON_HAS_ACTIVE_CHILDREN)
            ->and($e->blocked[0]['children_numbers'])->toBe([$child->number]);
    }

    expect(Appointment::find($parent->id))->not->toBeNull();
});

it('deletes a parent whose children are all cancelled, leaving them standalone', function () {
    $parent = bookForDeletion();
    $child = $this->salon->bookSlot(
        $this->salon->filler, SalonFixture::DATE, '10:00', '11:00',
        status: AppointmentStatus::ADMIN_CANCELLED,
    );
    $child->update(['parent_appointment_id' => $parent->id]);

    deletionService()->delete($parent);

    expect(Appointment::find($parent->id))->toBeNull()
        ->and($child->fresh()->parent_appointment_id)->toBeNull();
});

it('deletes a child and keeps its parent and the parent invoice', function () {
    $parent = bookForDeletion();
    $child = $this->salon->bookSlot($this->salon->filler, SalonFixture::DATE, '10:00', '11:00');
    $child->update(['parent_appointment_id' => $parent->id]);

    deletionService()->delete($child);

    expect(Appointment::find($child->id))->toBeNull()
        ->and(Appointment::find($parent->id))->not->toBeNull()
        ->and($parent->fresh()->invoice)->not->toBeNull();
});

// ── 3. All-or-nothing ────────────────────────────────────────────────────────

it('deletes nothing when one appointment in the batch is blocked, and names it', function () {
    $free = bookForDeletion('10:00');
    $paid = bookForDeletion('12:00');
    $paid->update(['payment_status' => PaymentStatus::PAID_ONSTIE_CARD]);

    try {
        deletionService()->deleteMany([$free, $paid]);
        $this->fail('Expected AppointmentNotDeletableException');
    } catch (AppointmentNotDeletableException $e) {
        expect(array_column($e->blocked, 'number'))->toBe([$paid->number]);
    }

    expect(Appointment::find($free->id))->not->toBeNull()
        ->and(Appointment::find($paid->id))->not->toBeNull();
});

it('deletes every appointment in a batch when none is blocked', function () {
    $a = bookForDeletion('10:00');
    $b = bookForDeletion('12:00');

    expect(deletionService()->deleteMany([$a, $b]))->toBe(2)
        ->and(Appointment::whereKey([$a->id, $b->id])->count())->toBe(0);
});

// ── 4. Filament: the button that crashed, and who may press it ───────────────

function panelUser(string $role, array $permissions): User
{
    Role::findOrCreate($role, 'web');

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::create([
        'first_name' => 'Panel',
        'last_name' => ucfirst($role),
        'email' => $role.'-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permissions);

    return $user->fresh();
}

const APPOINTMENT_EDITOR = ['Appointment:access', 'Appointment:view', 'Appointment:edit'];

it('deletes from the Filament edit page without the 1451 crash', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(panelUser('admin', [...APPOINTMENT_EDITOR, 'Appointment:delete']));

    $appointment = bookForDeletion();

    Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect(Appointment::find($appointment->id))->toBeNull();
});

it('shows the reason instead of deleting a paid appointment from the edit page', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(panelUser('admin', [...APPOINTMENT_EDITOR, 'Appointment:delete']));

    $appointment = bookForDeletion();
    $appointment->update(['payment_status' => PaymentStatus::PAID_ONSTIE_CASH]);

    Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertNotified(__('resources.appointment.delete_blocked_title'));

    expect(Appointment::find($appointment->id))->not->toBeNull();
});

it('hides the delete button from a user without Appointment:delete', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(panelUser('manager', APPOINTMENT_EDITOR));

    $appointment = bookForDeletion();

    Livewire::test(EditAppointment::class, ['record' => $appointment->getRouteKey()])
        ->assertActionHidden(DeleteAction::class);

    expect(Appointment::find($appointment->id))->not->toBeNull();
});

it('bulk-deletes from the Filament table, all-or-nothing', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(panelUser('admin', [...APPOINTMENT_EDITOR, 'Appointment:delete']));

    $free = bookForDeletion('10:00');
    $paid = bookForDeletion('12:00');
    $paid->update(['payment_status' => PaymentStatus::PAID_ONSTIE_CASH]);

    Livewire::test(ListAppointments::class)
        ->callTableBulkAction('delete', [$free, $paid])
        ->assertNotified(__('resources.appointment.bulk_delete_blocked_title'));

    expect(Appointment::whereKey([$free->id, $paid->id])->count())->toBe(2);

    Livewire::test(ListAppointments::class)
        ->callTableBulkAction('delete', [$free]);

    expect(Appointment::find($free->id))->toBeNull();
});
