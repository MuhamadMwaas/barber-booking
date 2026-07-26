<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short-lived, single-use proof that "whoever holds this token has just
     * proven control of the account's email or phone".
     *
     * Laravel's stock `password_reset_tokens` table is keyed by email and cannot
     * represent a phone-only account, which is exactly the case this feature
     * exists to serve — hence a dedicated table keyed by user_id.
     */
    public function up(): void
    {
        Schema::create('password_reset_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Only the SHA-256 hash is stored: a database leak must not hand the
            // attacker usable reset tokens.
            $table->string('token_hash', 64)->unique();

            // OtpType value of the channel that proved ownership (1 = email, 2 = sms).
            $table->tinyInteger('channel');

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();

            // Kept for auditing / abuse investigation only; never used for auth.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_grants');
    }
};
