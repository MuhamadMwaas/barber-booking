{{-- Shared booking details block (services table + totals + meta) --}}
{{-- Expects: $appointment, $currency --}}
@php
    $servicesRecords = $appointment->relationLoaded('services_record')
        ? $appointment->services_record->sortBy('sequence_order')
        : $appointment->services_record()->orderBy('sequence_order')->get();
    $providerName = optional($appointment->provider)->full_name ?? '—';
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px; font-size: 14px; color: #1f2937;">
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.booking_number') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->number }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.date') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->formatted_date }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.time') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->time_range }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.duration') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->formatted_duration }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.provider') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $providerName }}</td>
    </tr>
    <tr>
        <td style="padding: 6px 0; color: #6b7280;">{{ __('booking_email.payment_method') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->payment_method }}</td>
    </tr>
    @if($appointment->notes)
    <tr>
        <td style="padding: 6px 0; color: #6b7280; vertical-align: top;">{{ __('booking_email.notes') }}</td>
        <td style="padding: 6px 0; font-weight: 600; text-align: end;">{{ $appointment->notes }}</td>
    </tr>
    @endif
</table>

<h3 style="margin: 24px 0 8px; font-size: 15px; color: #111827;">{{ __('booking_email.services') }}</h3>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 14px; color: #1f2937;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th align="start" style="padding: 10px 12px; text-align: start; font-weight: 600; border-bottom: 1px solid #e5e7eb;">{{ __('booking_email.service') }}</th>
            <th style="padding: 10px 12px; text-align: center; font-weight: 600; border-bottom: 1px solid #e5e7eb;">{{ __('booking_email.duration') }}</th>
            <th align="end" style="padding: 10px 12px; text-align: end; font-weight: 600; border-bottom: 1px solid #e5e7eb;">{{ __('booking_email.price') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($servicesRecords as $item)
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #f1f1f1;">{{ $item->service_name }}</td>
            <td style="padding: 10px 12px; text-align: center; border-bottom: 1px solid #f1f1f1;">{{ $item->formatted_duration }}</td>
            <td style="padding: 10px 12px; text-align: end; border-bottom: 1px solid #f1f1f1;">{{ $currency }} {{ $item->formatted_price }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 16px 0 0; font-size: 14px; color: #1f2937;">
    <tr>
        <td style="padding: 4px 12px; color: #6b7280; text-align: end;">{{ __('booking_email.subtotal') }}</td>
        <td style="padding: 4px 12px; text-align: end; width: 120px;">{{ $currency }} {{ number_format((float) $appointment->subtotal, 2) }}</td>
    </tr>
    <tr>
        <td style="padding: 4px 12px; color: #6b7280; text-align: end;">{{ __('booking_email.tax') }}</td>
        <td style="padding: 4px 12px; text-align: end;">{{ $currency }} {{ number_format((float) $appointment->tax_amount, 2) }}</td>
    </tr>
    <tr>
        <td style="padding: 8px 12px; font-weight: 700; font-size: 16px; text-align: end; border-top: 2px solid #e5e7eb;">{{ __('booking_email.total') }}</td>
        <td style="padding: 8px 12px; font-weight: 700; font-size: 16px; text-align: end; border-top: 2px solid #e5e7eb;">{{ $currency }} {{ number_format((float) $appointment->total_amount, 2) }}</td>
    </tr>
</table>
