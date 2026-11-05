<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A customer deleting their own account.
 *
 * The users row is removed for good (forceDelete, not the model's soft delete),
 * but the salon's records stay: bookings keep the customer's name, phone and
 * email as plain text, invoices keep their numbers and amounts. Both lose only
 * the link to the account (customer_id = NULL). The printed invoice reads the
 * name from the booking, so it still shows who it was issued to.
 */
class AccountDeletionService
{
    public function deleteCustomerAccount(User $user): void
    {
        $image = $user->profile_image;

        DB::transaction(function () use ($user): void {
            $email = $user->email;
            $phone = $user->phone;
            $userId = $user->getKey();

            $this->cancelFutureAppointments($userId);
            $this->detachAppointments($user);
            $this->detachInvoices($userId);
            $this->deleteOwnedData($user, $email, $phone, $userId);

            // Query delete on purpose: the model delete would fire File's hook
            // and remove the image from disk before the transaction is safe.
            $user->profile_image()->delete();
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->forceDelete();
        });

        // Only once the rows are really gone — a rollback cannot restore a file.
        if ($image) {
            Storage::disk($image->disk)->delete($image->path);
        }
    }

    private function cancelFutureAppointments(int $userId): void
    {
        DB::table('appointments')
            ->where('customer_id', $userId)
            ->where('start_time', '>', now())
            ->where('status', AppointmentStatus::PENDING->value)
            ->update([
                'status' => AppointmentStatus::USER_CANCELLED->value,
                'created_status' => 0,
                'cancellation_reason' => 'Account deleted by customer',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Copy the account's contact onto its bookings, then unlink them.
     *
     * Only empty fields are filled: a value already on the booking is what was
     * recorded when it was made (staff may have typed a different number for
     * that visit) and is kept. The marker stops GuestBookingClaimService from
     * treating these rows as guest bookings and handing them to the next
     * account that verifies the same phone.
     */
    private function detachAppointments(User $user): void
    {
        $contact = [
            'customer_name' => $user->full_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
        ];

        foreach ($contact as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            DB::table('appointments')
                ->where('customer_id', $user->getKey())
                ->where(fn ($query) => $query->whereNull($column)->orWhere($column, ''))
                ->update([$column => $value]);
        }

        DB::table('appointments')
            ->where('customer_id', $user->getKey())
            ->update([
                'customer_id' => null,
                'customer_account_deleted_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function detachInvoices(int $userId): void
    {
        DB::table('invoices')
            ->where('customer_id', $userId)
            ->update([
                'customer_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function deleteOwnedData(User $user, ?string $email, ?string $phone, int $userId): void
    {
        $user->tokens()->delete();
        $user->refreshTokens()->delete();
        $user->devices()->delete();
        $user->savedPaymentMethods()->delete();
        $user->serviceReviews()->delete();
        $user->notifications()->delete();

        DB::table('sessions')->where('user_id', $userId)->delete();

        if ($email) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        // An account registered by phone has no email; `where('email', null)`
        // would match nothing and skip the phone OTPs with it.
        $otpQuery = Otp::query()->where(function ($query) use ($email, $phone) {
            $query->whereRaw('1 = 0');

            if ($email) {
                $query->orWhere('email', $email);
            }

            if ($phone) {
                $query->orWhere('phone', $phone);
            }
        });

        $otpQuery->delete();
    }
}
