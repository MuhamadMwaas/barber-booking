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
        Schema::create('files', function (Blueprint $table) {
                $table->id();
                $table->string('instance_type');
                $table->unsignedBigInteger('instance_id');
                $table->string('name');
                $table->string('extension', 10)->nullable()->default(null);
                $table->string('path');
                $table->string('disk');
                $table->string('type', 75)->nullable()->default(null);
                $table->string('key')->nullable()->default(null);
                $table->string('group')->nullable()->default(null);
                $table->index(['instance_type', 'instance_id']);
                $table->softDeletes();
                $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
