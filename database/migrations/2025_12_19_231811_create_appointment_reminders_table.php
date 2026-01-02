<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('user_id');

            $table->timestamp('remind_at');
            $table->string('status', 20)->default('pending');

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->uuid('job_uuid')->nullable();
            $table->string('locale', 10)->nullable();

            $table->string('title_key');
            $table->string('message_key');
            $table->json('params')->nullable();
            $table->json('data')->nullable();


            $table->index(['remind_at']);
            $table->index(['appointment_id', 'status']);
            $table->unique(['appointment_id', 'user_id', 'remind_at', 'status'], 'appointment_reminders_unique_pending');

            $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
