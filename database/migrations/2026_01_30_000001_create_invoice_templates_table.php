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
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            // Template Settings
            $table->string('language', 10)->default('en'); // en, de, ar
            $table->string('paper_size', 20)->default('80mm'); // 80mm, 58mm
            $table->integer('paper_width')->default(80); // in mm
            $table->string('font_family')->default('Arial');
            $table->integer('font_size')->default(10);

            // Global Styles (JSON)
            $table->json('global_styles')->nullable();
            $table->text('static_body_html')->nullable();
            // {
            //   "primary_color": "#000000",
            //   "secondary_color": "#666666",
            //   "line_height": 1.2,
            //   "padding": 5
            // }

            // Company Settings (JSON)
            $table->json('company_info')->nullable();
            // {
            //   "name": "Company Name",
            //   "address": "Address",
            //   "phone": "Phone",
            //   "tax_number": "Tax Number",
            //   "email": "Email",
            //   "logo_path": "path/to/logo.png"
            // }

            // Metadata
            $table->json('metadata')->nullable(); // For any additional settings

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index('is_default');
            $table->index('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
