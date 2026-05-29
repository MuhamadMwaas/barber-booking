<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hex_code', 7)->default('#000000');
            $table->string('brand')->nullable();
            $table->string('unit', 20)->default('ml'); // ml, g, piece, etc.
            $table->decimal('stock_quantity', 8, 2)->nullable(); // Reference quantity
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colors');
    }
};
