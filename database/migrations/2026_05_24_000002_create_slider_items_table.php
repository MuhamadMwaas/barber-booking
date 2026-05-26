<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * slider_items — كل شريحة (slide) داخل السلايدر
     * الصورة تُخزَّن في جدول files عبر MorphOne (instance_type/instance_id)
     * النصوص تُخزَّن في slider_item_translations
     *
     * قاعدة الظهور:
     *   is_active = true
     *   AND (starts_at IS NULL OR starts_at <= NOW())
     *   AND (ends_at   IS NULL OR ends_at   >= NOW())
     */
    public function up(): void
    {
        Schema::create('slider_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('slider_id')
                ->constrained('sliders')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // جدولة النشر — null يعني "دائم" بدون قيود
            $table->dateTime('starts_at')->nullable()
                ->comment('null = no start restriction (permanent)');
            $table->dateTime('ends_at')->nullable()
                ->comment('null = no end restriction (permanent)');

            $table->timestamps();
            $table->index(['slider_id', 'is_active', 'sort_order'], 'slider_items_active_sorted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slider_items');
    }
};
