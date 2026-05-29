<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('color_id')->constrained('colors')->cascadeOnDelete();
            $table->decimal('quantity', 8, 2)->default(0); // Amount used in this appointment
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_colors');
    }
};
