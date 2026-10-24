<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Services\Landing\LandingContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email channel for appointment reminders.
 *
 * Has its own branded layout and salon-approved wording (booking_email.reminder.*),
 * deliberately separate from the confirmation's shared details partial: no
 * booking number, payment method, notes, duration or prices. Push and SMS keep
 * the short shared reminder text (appointment_reminder.v2.*).
 *
 * Images are embedded (CID) by the view, so they show without a public APP_URL.
 */
class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Footer icons, in display order; a platform without a URL is hidden. */
    private const SOCIAL_PLATFORMS = ['instagram', 'facebook'];

    public function __construct(
        public Appointment $appointment,
        public string $companyName,
        string $locale,
    ) {
        $this->locale = $locale;
    }

    public function build(): self
    {
        return $this
            ->subject(__('booking_email.reminder.subject', ['company' => $this->companyName]))
            ->view('emails.appointment-reminder', [
                'appointment' => $this->appointment,
                'companyName' => $this->companyName,
                'cards'       => $this->cards(),
                'socialLinks' => $this->socialLinks(),
            ]);
    }

    /**
     * One card per appointment block, in time order. A split booking
     * (BOOKING-GAP-01) has several blocks with their own time and provider;
     * an ordinary booking is a single card.
     *
     * @return array<int, array{date: string, time: string, provider: string, services: string, salon: string}>
     */
    protected function cards(): array
    {
        $locale = $this->locale;
        $salon = $this->salonLine();

        return collect([$this->appointment])
            ->merge($this->appointment->relationLoaded('children') ? $this->appointment->children : collect())
            ->filter(fn (Appointment $block) => $block->isActiveInGroup())
            ->sortBy('start_time')
            ->map(function (Appointment $block) use ($locale, $salon) {
                // Shown in the email's language; the stored snapshot is in the
                // language the booking was made in. `translations` is NOT eager
                // loaded on purpose: getNameIn() then matches by language_id,
                // which every row has, instead of language_code, which older
                // rows may lack.
                $services = $block->services_record()->with('service')->orderBy('sequence_order')->get()
                    ->map(fn ($item) => $item->service?->getNameIn($locale) ?? $item->service_name)
                    ->filter()
                    ->implode(', ');

                return [
                    'date'     => $block->appointment_date->locale($locale)->translatedFormat(__('booking_email.reminder.date_format')),
                    'time'     => $block->start_time->format('H:i'),
                    'provider' => $block->provider?->first_name ?: ($block->provider?->full_name ?: '—'),
                    'services' => $services ?: '—',
                    'salon'    => $salon,
                ];
            })
            ->values()
            ->all();
    }

    /** "Look up, Musterstraße 1, 84028 Landshut" — the name alone when no address is set. */
    protected function salonLine(): string
    {
        $address = trim(preg_replace('/\s*\R\s*/', ', ', (string) get_setting('company_address', '')));

        return $address !== '' ? "{$this->companyName}, {$address}" : $this->companyName;
    }

    /**
     * Instagram/Facebook URLs from the landing page's contact section, so the
     * salon manages them in one place (Filament → Landing page).
     *
     * @return array<string, string> platform => url
     */
    protected function socialLinks(): array
    {
        return collect((new LandingContent($this->locale))->items('info.social_links'))
            ->filter(fn ($row) => in_array($row['platform'] ?? null, self::SOCIAL_PLATFORMS, true) && filled($row['url'] ?? null))
            ->mapWithKeys(fn ($row) => [$row['platform'] => $row['url']])
            ->sortBy(fn ($url, $platform) => array_search($platform, self::SOCIAL_PLATFORMS, true))
            ->all();
    }
}
