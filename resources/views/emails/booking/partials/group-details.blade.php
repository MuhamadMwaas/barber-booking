{{-- Booking details for a booking split into several blocks (BOOKING-GAP-01) --}}
{{-- No duration, no prices and no payment method in any email: the salon asked for all three to be left out. --}}
{{-- Each block shows its start time only — an end time would reveal the duration. --}}
{{-- Expects: $appointment (the group root), $blocks (active blocks, time order), $currency --}}
@php
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
    @if($appointment->notes)
    <tr>
        <td style="padding: 6px 0; color: #6b7280; vertical-align: top;">{{ __('booking_email.notes') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->notes }}</td>
    </tr>
    @endif
</table>

<h3 style="margin: 24px 0 4px; font-size: 15px; color: #111827;">{{ __('booking_email.appointments_heading', ['count' => $blocks->count()]) }}</h3>
<p style="margin: 0 0 12px; font-size: 13px; color: #6b7280;">{{ __('booking_email.appointments_intro') }}</p>

@foreach($blocks as $block)
    @php
        $blockServices = $block->relationLoaded('services_record')
            ? $block->services_record->sortBy('sequence_order')
            : $block->services_record()->orderBy('sequence_order')->get();
    @endphp
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 16px; border: 1px solid #e5e7eb; border-collapse: collapse; font-size: 14px; color: #1f2937;">
        <tr style="background-color: #f3f4f6;">
            <td style="padding: 10px 12px; font-weight: 700;">
                {{ __('booking_email.appointment_n', ['n' => $loop->iteration]) }} — <span dir="ltr">{{ $emailTime($block) }}</span>
            </td>
            <td style="padding: 10px 12px; text-align: end; color: #6b7280;">
                {{ __('booking_email.provider') }}: <strong style="color: #1f2937;">{{ optional($block->provider)->full_name ?? '—' }}</strong>
            </td>
        </tr>
        @foreach($blockServices as $item)
        <tr>
            <td colspan="2" style="padding: 8px 12px; border-top: 1px solid #f1f1f1;">{{ $item->service_name }}</td>
        </tr>
        @endforeach
        <tr>
            <td colspan="2" style="padding: 6px 12px; border-top: 1px solid #f1f1f1; color: #6b7280;">{{ __('booking_email.booking_number') }}: {{ $block->number }}</td>
        </tr>
    </table>
@endforeach

