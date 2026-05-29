<?php

namespace App\Http\Controllers;

use App\Enum\AppointmentStatus;
use App\Models\Appointment;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AppointmentPrintController extends Controller
{
    /**
     * Print appointment ticket (80mm thermal printer, B/W).
     * Renders an operational work-order, NOT an invoice.
     *
     * GET /appointment/{appointment}/print
     */
    public function print(Request $request, Appointment $appointment)
    {
        // Block printing for cancelled appointments — they are not operationally useful.
        $blockedStatuses = [
            AppointmentStatus::USER_CANCELLED,
            AppointmentStatus::ADMIN_CANCELLED,
        ];
        if (in_array($appointment->status, $blockedStatuses, true)) {
            abort(403, 'Cannot print a cancelled appointment.');
        }

        // Resolve the linked group root: if this is a child, print the parent's combined ticket.
        $root = $appointment->parent ?? $appointment;

        // Eager-load everything the ticket needs.
        $root->load([
            'provider',
            'customer',
            'services_record',
            'colorRecords.color',
            'children.provider',
            'children.services_record',
            'children.colorRecords.color',
        ]);

        // Build the appointment list to render (parent first, then children).
        $appointments = collect([$root])->merge($root->children ?? []);

        // Salon / business info from settings.
        $company = [
            'name'    => SettingsService::get('company_name', config('app.name')),
            'address' => SettingsService::get('company_address', ''),
            'phone'   => SettingsService::get('company_phone', ''),
            'email'   => SettingsService::get('company_email', ''),
        ];

        // Aggregate totals across all appointments in the group.
        $grandTotal = $appointments->sum(fn ($apt) => (float) $apt->total_amount);

        // Group-level payment status: paid if any successful payment exists on any appointment.
        $isPaid = $appointments->contains(
            fn ($apt) => $apt->payment_status?->isSuccessful() === true
        );

        // Countdown info based on the earliest start_time in the group.
        $earliestStart = $appointments
            ->pluck('start_time')
            ->filter()
            ->sort()
            ->first();
        $latestEnd = $appointments
            ->pluck('end_time')
            ->filter()
            ->sort()
            ->last();

        $now = now();
        $countdown = $this->buildCountdown($earliestStart, $latestEnd, $now);

        $locale = app()->getLocale();
        $isRtl  = $locale === 'ar';

        return view('appointments.print-ticket', [
            'root'         => $root,
            'appointments' => $appointments,
            'company'      => $company,
            'grandTotal'   => $grandTotal,
            'isPaid'       => $isPaid,
            'countdown'    => $countdown,
            'printedAt'    => $now,
            'locale'       => $locale,
            'isRtl'        => $isRtl,
        ]);
    }

    /**
     * Build countdown state for the ticket header.
     *
     * @return array{state:string,minutes:int|null}
     *   state: 'upcoming' | 'in_progress' | 'ended' | 'unknown'
     *   minutes: minutes until start (upcoming) or since end (ended) — null otherwise
     */
    protected function buildCountdown($start, $end, $now): array
    {
        if (! $start) {
            return ['state' => 'unknown', 'minutes' => null];
        }

        if ($now->lt($start)) {
            return [
                'state'   => 'upcoming',
                'minutes' => (int) $now->diffInMinutes($start, false),
            ];
        }

        if ($end && $now->lte($end)) {
            return ['state' => 'in_progress', 'minutes' => null];
        }

        if ($end && $now->gt($end)) {
            return [
                'state'   => 'ended',
                'minutes' => (int) $end->diffInMinutes($now, false),
            ];
        }

        return ['state' => 'in_progress', 'minutes' => null];
    }
}
