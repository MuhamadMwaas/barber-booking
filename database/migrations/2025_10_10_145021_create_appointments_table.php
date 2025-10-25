<?php

use App\Enum\AppointmentStatus;
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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')->references('id')->on('users');

            $table->unsignedBigInteger('provider_id');
            $table->foreign('provider_id')->references('id')->on('users');

            $table->dateTime('appointment_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->smallInteger('duration_minutes');
            $table->decimal('subtotal');
            $table->decimal('tax_amount');
            $table->decimal('total_amount');
            $table->tinyInteger('status')->comment(AppointmentStatus::CommentStatus());
            $table->string('payment_method')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('payment_status')->default(0);
            $table->tinyInteger('created_status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
