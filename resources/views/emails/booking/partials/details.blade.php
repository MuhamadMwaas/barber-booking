{{-- Shared booking details block (services list + meta) --}}
{{-- No duration, no prices and no payment method in any email: the salon asked for all three to be left out. --}}
{{-- The time is the start only — an end time would reveal the duration. --}}
{{-- Expects: $appointment, $currency --}}
@php
    // A booking split into several blocks (BOOKING-GAP-01) lists every block;
    // a single-block booking renders exactly as it always has, below.
    $blocks = collect([$appointment])
        ->merge($appointment->relationLoaded('children') ? $appointment->children : collect())
        ->filter(fn ($block) => $block->isActiveInGroup())
        ->sortBy('start_time')
        ->values();
@endphp
@if ($blocks->count() > 1)
@include('emails.booking.partials.group-details', ['appointment' => $appointment, 'blocks' => $blocks, 'currency' => $currency])
@else
@php
    $servicesRecords = $appointment->relationLoaded('services_record')
        ? $appointment->services_record->sortBy('sequence_order')
        : $appointment->services_record()->orderBy('sequence_order')->get();
    $providerName = optional($appointment->provider)->full_name ?? '—';
    // Customer-facing headings ("Ihre ...") never leak into the salon's copy.
    $isCustomer = ($audience ?? 'company') === 'customer';
    // Dates/times in the email's own locale (German: 30/12/2026, 14:30). The
    // model accessors stay untouched: the API serves them as they are. The
    // time is wrapped in dir="ltr" so an Arabic email keeps "10:00 AM" in order.
    $emailDate = fn ($a) => $a->appointment_date->format(\App\Support\DateFormat::date('M d, Y'));
    $emailTime = fn ($a) => $a->start_time->format(\App\Support\DateFormat::time('h:i A'));
@endphp

@if($isCustomer)
<h3 style="margin: 0 0 8px; font-size: 15px; color: #111827;">{{ __('booking_email.details_heading') }}</h3>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; font-size: 14px; color: #1f2937;">
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.booking_number') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->number }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.date') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $emailDate($appointment) }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.time') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;"><span dir="ltr">{{ $emailTime($appointment) }}</span></td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.provider') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $providerName }}</td>
    </tr>
    @if($appointment->notes)
    <tr>
        <td style="padding: 6px 0; color: #6b7280; vertical-align: top;">{{ __('booking_email.notes') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->notes }}</td>
    </tr>
    @endif
</table>

<h3 style="margin: 24px 0 8px; font-size: 15px; color: #111827;">{{ $isCustomer ? __('booking_email.services_customer') : __('booking_email.services') }}</h3>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 14px; color: #1f2937;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th align="start" style="padding: 10px 12px; text-align: start; font-weight: 600; border-bottom: 1px solid #e5e7eb;">{{ __('booking_email.service') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($servicesRecords as $item)
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #f1f1f1;">{{ $item->service_name }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@endif
