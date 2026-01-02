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
        Schema::create('page_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_id')
                ->constrained('sample_pages')
                ->cascadeOnDelete();

            $table->string('lang', 10);

            $table->string('title');
            $table->text('content')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['page_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_translations');
    }
};
