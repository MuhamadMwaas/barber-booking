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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('service_categories');


            $table->string('name');
            $table->text('description');
            $table->decimal('price');
            $table->decimal('discount_price')->nullable();
            $table->integer('duration_minutes');
            $table->boolean('is_active');
            $table->integer('sort_order');
            $table->string('image_url')->nullable();
            $table->string('color_code', 50);
            $table->string('icon_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
