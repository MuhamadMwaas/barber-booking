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
        Schema::create('about_us_pages', function (Blueprint $table) {
            $table->id();

            // Locale — صفحة واحدة per locale (en, ar, de)
            // $table->string('locale', 5)->unique();

            // Hero Section — الصور تُحفظ في جدول files عبر علاقة morphMany
            $table->json('hero_title');
            $table->json('hero_subtitle');
            $table->json('hero_description');

            // Contact Info (multilingual where needed)
            $table->json('contact_phone');        // {"value": "+49...", "label": {"de":"Telefon","ar":"الهاتف"},"icon":"phone"}
            $table->json('contact_address');      // {"value": "Berlin...", "label": {"de":"Addresse","ar":"العنوان"}}
            $table->string('contact_email')->nullable();
            $table->json('opening_hours');        // {"de": "Mo-Fr 9-18", "ar": "السبت-الخميس"}

            // Social & Legal Links
            $table->json('social_title');        // {"de": "Folge uns", "ar": "تابعنا"}

            $table->json('social_links');         // [{"platform":"instagram","url":"...","icon":"instagram"}]
            $table->json('legal_links');          // [{"key":"impressum","label":{},"url":"..."}]

            // Features (3 مميزات ديناميكية)
            $table->json('features');             // [{"icon":"star","title":{},"description":{}}]

            // Newsletter Section
            $table->json('newsletter_title');     // {"de":"Bleibe auf dem Laufenden","ar":"ابق على اطلاع"}
            $table->json('newsletter_description')->nullable();
            $table->boolean('newsletter_enabled')->default(true);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_us_pages');
    }
};
