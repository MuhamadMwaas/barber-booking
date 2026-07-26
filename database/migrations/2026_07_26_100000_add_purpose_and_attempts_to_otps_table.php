<?php

use App\Enum\OtpPurpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            // Every OTP issued before this migration was an activation code, so
            // that is the safe default for existing rows and for any caller that
            // has not been updated to pass a purpose explicitly.
            $table->string('purpose', 40)
                ->default(OtpPurpose::ACCOUNT_VERIFICATION->value)
                ->after('type');

            // Wrong-guess counter, reset per issued code. See config('otp.max_attempts').
            $table->unsignedTinyInteger('attempts')->default(0)->after('purpose');

            $table->index(['purpose', 'type', 'used'], 'otps_purpose_type_used_index');
            $table->index('phone', 'otps_phone_index');
        });

        DB::table('otps')->update(['purpose' => OtpPurpose::ACCOUNT_VERIFICATION->value]);
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropIndex('otps_purpose_type_used_index');
            $table->dropIndex('otps_phone_index');
            $table->dropColumn(['purpose', 'attempts']);
        });
    }
};
