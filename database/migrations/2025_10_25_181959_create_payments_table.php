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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->string('payment_number');
            $table->decimal('amount');
            $table->decimal('subtotal');
            // $table->bigInteger('appointment_id');
            $table->tinyInteger('status')->default(0);
            $table->string('payment gateway_id')->nullable();
            $table->text('payment_metadata')->nullable();
            $table->decimal('tax_amount');
            $table->string('type');
            $table->bigInteger('paymentable_id');
            $table->string('paymentable_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
