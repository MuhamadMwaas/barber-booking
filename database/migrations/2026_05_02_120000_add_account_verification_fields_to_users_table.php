<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('registration_method', 20)->nullable()->after('phone');
            $table->string('email')->nullable()->change();
        });

        DB::table('users')
            ->whereNull('registration_method')
            ->update([
                'registration_method' => DB::raw("CASE WHEN phone IS NOT NULL AND email IS NULL THEN 'phone' ELSE 'email' END"),
            ]);

        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('phone_verified_at')
            ->update([
                'email_verified_at' => now(),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone_verified_at', 'registration_method']);
            $table->string('email')->nullable(false)->change();
        });
    }
};