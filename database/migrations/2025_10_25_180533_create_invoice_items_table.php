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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices');

            $table->text('description')->nullable();
            $table->smallInteger('quantity');
            $table->decimal('unit_price');
            $table->decimal('tax_amount')->nullable();
            $table->decimal('tax_rate')->nullable();
            $table->decimal('total_amount');
            $table->bigInteger('itemable_id');
            $table->string('itemable_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
