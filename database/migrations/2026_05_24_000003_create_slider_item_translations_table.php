<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * slider_item_translations — ترجمات كل شريحة (en, ar, de)
     * UNIQUE على (slider_item_id, language_id) لمنع التكرار
     */
    public function up(): void
    {
        Schema::create('slider_item_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('slider_item_id')
                ->constrained('slider_items')
                ->cascadeOnDelete();

            $table->foreignId('language_id')
                ->constrained('languages')
                ->cascadeOnDelete();

            // المحتوى المترجَم
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();

            $table->timestamps();

            // ترجمة واحدة فقط لكل لغة لكل شريحة
            $table->unique(['slider_item_id', 'language_id'], 'slider_item_lang_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_item_translations');
    }
};
