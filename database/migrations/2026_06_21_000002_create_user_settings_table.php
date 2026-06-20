<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user value overrides for the options defined in `app_settings`.
 *
 * A row exists only when a customer has actually changed an option away from its
 * default. `get()` falls back to app_settings.default_value when no row is found.
 * The (user_id, key) pair is unique so each user has at most one value per option.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');                 // references app_settings.key
            $table->json('value')->nullable();     // the user's chosen value
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
