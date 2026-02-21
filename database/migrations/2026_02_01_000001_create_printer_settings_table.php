<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('printer_settings', function (Blueprint $table) {
            $table->id();

            // Basic Info
            $table->string('name');
            $table->string('printer_name')->nullable();
            $table->text('description')->nullable();

            // Connection Settings
            $table->enum('connection_type', ['usb', 'network'])->default('usb');
            $table->string('ip_address')->nullable();
            $table->integer('port')->nullable()->default(9100);
            $table->string('device_path')->nullable();

            $table->enum('paper_size', ['80mm', '58mm'])->default('80mm');
            $table->integer('default_copies')->default(1);

            $table->enum('print_method', ['browser', 'escpos', 'raw'])->default('browser');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->timestamp('last_test_at')->nullable();
            $table->enum('last_test_status', ['success', 'failed'])->nullable();
            $table->text('last_test_message')->nullable();

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('is_active');
            $table->index('is_default');
            $table->index('connection_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('printer_settings');
    }
};
