<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog of user-facing application options (definitions only — NOT the values).
 *
 * Each row describes ONE option that the customer can control from the app's
 * settings screen: its key, its translated label, the value type, the default
 * value (used until the user overrides it), and — per the requirement — the
 * validation rules stored AS DATA so the generic update route can validate any
 * option dynamically without code changes.
 *
 * The per-user chosen value lives in `user_settings`, not here. Splitting the two
 * avoids duplicating the label/translations on every user row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // e.g. reminder_email_enabled
            $table->json('label_translations');              // {"en":..., "ar":..., "de":...}
            $table->json('description_translations')->nullable();
            $table->string('type')->default('boolean');      // boolean|string|integer|decimal|json
            $table->json('default_value')->nullable();       // fallback when no user override
            $table->string('validation')->nullable();        // Laravel rule string, e.g. "required|boolean"
            $table->string('group')->nullable();             // e.g. notifications
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
