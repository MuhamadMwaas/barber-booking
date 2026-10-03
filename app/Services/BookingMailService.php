<?php

namespace App\Services;

use App\Enum\BookingSource;
use App\Mail\BookingConfirmationMail;
use App\Mail\BookingNotificationMail;
use App\Models\Appointment;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Centralizes the "send emails after a booking is created" logic.
 *
 * Two emails are produced:
 *   1. Customer confirmation — ONLY for online (API) bookings.
 *   2. Company notification  — for EVERY booking.
 *
 * Both mailables implement ShouldQueue, so this class only pushes jobs onto
 * the queue. Every step is guarded so a mail problem can never break or roll
 * back the booking itself.
 */
class BookingMailService
{
    /**
     * The salon is in Germany: a customer email in a language with no
     * translation goes out in German.
     */
    public const FALLBACK_CUSTOMER_LOCALE = 'de';

    /**
     * Entry point called right after a booking is successfully committed.
     */
    public function sendForNewBooking(Appointment $appointment): void
    {
        try {
            // Make sure the relations the templates need are present. The
            // children are the other blocks of a split booking (BOOKING-GAP-01):
            // ONE email lists every block, instead of one email per block.
            // SerializesModels re-loads these nested relations on the queue.
            $appointment->loadMissing([
                'services_record', 'provider', 'customer',
                'children.services_record', 'children.provider',
            ]);

            $companyName = $this->companyName();
            $currency    = $this->currency();

            $this->sendCustomerConfirmation($appointment, $companyName, $currency);
            $this->sendCompanyNotification($appointment, $companyName, $currency);
        } catch (\Throwable $e) {
            // Never let an email failure bubble up into the booking flow.
            Log::error('Booking email dispatch failed', [
                'appointment_id' => $appointment->id ?? null,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Customer confirmation — only for online bookings, and only when we
     * actually have an email address to send to.
     */
    protected function sendCustomerConfirmation(Appointment $appointment, string $companyName, string $currency): void
    {
        if ($appointment->booking_source !== BookingSource::ONLINE) {
            return;
        }

        $email = $appointment->customer_email;
        if (empty($email)) {
            Log::warning('Booking confirmation skipped: customer has no email', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        Mail::to($email)->queue(new BookingConfirmationMail(
            appointment: $appointment,
            companyName: $companyName,
            currency: $currency,
            locale: self::customerLocale(app()->getLocale()),
        ));
    }

    /**
     * Company notification — for every booking. Recipient resolved from the
     * company_email setting, falling back to the global MAIL_FROM_ADDRESS.
     */
    protected function sendCompanyNotification(Appointment $appointment, string $companyName, string $currency): void
    {
        $companyEmail = get_setting('company_email') ?: config('mail.from.address');
        if (empty($companyEmail)) {
            Log::warning('Booking notification skipped: no company email configured', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        Mail::to($companyEmail)->queue(new BookingNotificationMail(
            appointment: $appointment,
            companyName: $companyName,
            currency: $currency,
            locale: $this->companyLocale(),
        ));
    }

    /**
     * The language a customer email is rendered in.
     *
     * The caller passes the language the customer is actually using — for a
     * booking, the request's locale (SetApiLocale: ?lang, Accept-Language). It
     * is NOT read from users.locale: registration never fills that column, so
     * it holds the 'en' default for nearly everyone and would send every
     * customer an English email.
     */
    public static function customerLocale(?string $locale): string
    {
        return $locale !== null && Lang::has('booking_email.subject_customer', $locale, false)
            ? $locale
            : self::FALLBACK_CUSTOMER_LOCALE;
    }

    /**
     * Brand name shown in customer emails; the company_name setting, else APP_NAME.
     */
    public function companyName(): string
    {
        return (string) (get_setting('company_name', '') ?: config('app.name'));
    }

    public function currency(): string
    {
        return (string) get_setting('currency_symbol', '€');
    }

    /**
     * Default language for internal/company-facing emails.
     */
    protected function companyLocale(): string
    {
        return (string) (get_setting('default_language') ?: config('app.locale', 'en'));
    }
}
