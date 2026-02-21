<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained('printer_settings')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Print Info
            $table->integer('print_number')->default(1);
            $table->integer('copies')->default(1);
            $table->enum('print_type', ['original', 'copy', 'reprint'])->default('original');

            // Status
            $table->enum('status', ['pending', 'printing', 'success', 'failed'])->default('pending');
            $table->text('error_message')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable(); 

            // Metadata
            $table->json('print_data')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('printer_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('print_type');
            $table->index(['invoice_id', 'print_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('print_logs');
    }
};
