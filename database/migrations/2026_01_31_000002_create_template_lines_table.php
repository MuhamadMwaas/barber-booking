<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_lines', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->unsignedBigInteger('template_id');
            $table->foreign('template_id')->references('id')->on('invoice_templates')->onDelete('cascade');

            // Line Configuration
            $table->string('section', 20); // header, body, footer
            $table->string('type', 50); // text, separator, invoice_number, items_table, etc.
            $table->integer('order')->default(0); // Order of appearance
            $table->boolean('is_enabled')->default(true);

            // Line Properties (JSON)
            // Each line type has different properties stored here
            $table->json('properties');
            // Examples:
            // For 'text' type:
            // {
            //   "content_type": "dynamic", // static or dynamic
            //   "static_value": "Welcome!",
            //   "dynamic_field": "company.name",
            //   "prefix": "Company: ",
            //   "suffix": "",
            //   "font_size": 12,
            //   "font_weight": "bold",
            //   "font_style": "normal",
            //   "alignment": "center",
            //   "color": "#000000",
            //   "margin_top": 0,
            //   "margin_bottom": 5
            // }
            //
            // For 'separator' type:
            // {
            //   "style": "solid", // solid, dashed, dotted
            //   "width": 1,
            //   "color": "#000000",
            //   "margin_top": 5,
            //   "margin_bottom": 5
            // }
            //
            // For 'items_table' type:
            // {
            //   "show_item_numbers": true,
            //   "show_quantity": true,
            //   "show_unit_price": true,
            //   "show_tax": true,
            //   "show_total": true,
            //   "table_border": true
            // }

            $table->timestamps();

            // Indexes
            $table->index(['template_id', 'section', 'order']);
            $table->index('type');
            $table->index('is_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_lines');
    }
};
