<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // author
            $table->text('body');
            $table->boolean('is_pinned')->default(false); // true when author is admin → shown first
            $table->timestamp('expires_at')->nullable(); // optional auto-expiry
            $table->timestamps();
            $table->softDeletes(); // deleted_at = when the message was removed (history is never hard-deleted)
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete(); // who deleted it

            $table->index(['deleted_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_messages');
    }
};
