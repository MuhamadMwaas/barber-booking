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
        Schema::create('fiskaly_tss', function (Blueprint $table) {
            $table->id();
            $table->string('tss_id')->unique();
            $table->text('puk')->nullable(); // Encrypted PUK
            $table->text('admin_pin')->nullable(); // Admin PIN
            $table->string('serial_number')->nullable();
            $table->text('certificate')->nullable();
            $table->string('state')->default('INITIALIZED'); // INITIALIZED, DISABLED, DEFECTIVE
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tss_id');
            $table->index('state');
        });

        Schema::create('fiskaly_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('tss_id');
            $table->string('serial_number');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('tss_id');

            $table->foreign('tss_id')
                ->references('tss_id')
                ->on('fiskaly_tss')
                ->onDelete('cascade');
        });

        Schema::create('fiskaly_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('tss_id');
            $table->string('client_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('transaction_number')->nullable();
            $table->string('state'); // ACTIVE, FINISHED, CANCELLED
            $table->timestamp('time_start')->nullable();
            $table->timestamp('time_end')->nullable();
            $table->json('signature')->nullable();
            $table->text('qr_code_data')->nullable();
            $table->string('tss_serial_number')->nullable();
            $table->string('client_serial_number')->nullable();
            $table->json('schema_data')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transaction_id');
            $table->index('tss_id');
            $table->index('client_id');
            $table->index('invoice_id');
            $table->index('state');
            $table->index('time_start');
            $table->index('time_end');

            $table->foreign('tss_id')
                ->references('tss_id')
                ->on('fiskaly_tss')
                ->onDelete('cascade');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('set null');
        });

        Schema::create('fiskaly_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // info, warning, error
            $table->string('action'); // authenticate, create_tss, start_transaction, etc.
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('tss_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('created_at');

            $table->index('level');
            $table->index('action');
            $table->index('tss_id');
            $table->index('transaction_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiskaly_logs');
        Schema::dropIfExists('fiskaly_transactions');
        Schema::dropIfExists('fiskaly_clients');
        Schema::dropIfExists('fiskaly_tss');
    }
};
