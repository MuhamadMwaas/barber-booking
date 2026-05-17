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
        Schema::create('about_us_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('about_us_page_id')->constrained('about_us_pages')->cascadeOnDelete();

            $table->json('name');           // {"de": "Anna", "ar": "آنا", "en": "Anna"}
            $table->json('position');       // {"de": "Friseurin", "ar": "مصممة شعر", "en": "Hair Stylist"}
            $table->json('description');    // نص ترحيبي إلزامي
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_us_team_members');
    }
};
