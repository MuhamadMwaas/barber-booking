<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function deleteCustomerAccount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $email = $user->email;
            $phone = $user->phone;
            $userId = $user->getKey();

            $this->cancelFutureAppointments($userId);
            $this->anonymizeAppointments($userId);
            $this->anonymizeInvoices($userId);
            $this->deleteOwnedData($user, $email, $phone, $userId);

            $user->profile_image()->delete();
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });
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

    private function anonymizeAppointments(int $userId): void
    {
        DB::table('appointments')
            ->where('customer_id', $userId)
            ->update([
                'customer_id' => null,
                'customer_name' => null,
                'customer_email' => null,
                'customer_phone' => null,
                'notes' => null,
                'updated_at' => now(),
            ]);
    }

    private function anonymizeInvoices(int $userId): void
    {
        DB::table('invoices')
            ->where('customer_id', $userId)
            ->update([
                'customer_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function deleteOwnedData(User $user, string $email, ?string $phone, int $userId): void
    {
        $user->tokens()->delete();
        $user->refreshTokens()->delete();
        $user->devices()->delete();
        $user->savedPaymentMethods()->delete();
        $user->serviceReviews()->delete();
        $user->notifications()->delete();

        DB::table('sessions')->where('user_id', $userId)->delete();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $otpQuery = Otp::query()->where('email', $email);

        if ($phone) {
            $otpQuery->orWhere('phone', $phone);
        }

        $otpQuery->delete();
    }
}