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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->foreign('appointment_id')->references('id')->on('appointments');

            $table->unsignedBigInteger('customer_id')->nullable()->default(null);
            $table->foreign('customer_id')->references('id')->on('users');

            $table->string('invoice_number')->nullable()->default(null);
            $table->decimal('subtotal');
            $table->decimal('tax_amount')->nullable();
            $table->decimal('tax_rate')->nullable();
            $table->decimal('total_amount');
            $table->tinyInteger('status');
            $table->string('notes')->nullable();
            $table->json('invoice_data')->nullable();
            $table->string('segnture')->nullable();
            $table->string('signature_missing_reason')->nullable();
            $table->integer('print_count')->default(0);
            $table->timestamp('first_printed_at')->nullable();
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
