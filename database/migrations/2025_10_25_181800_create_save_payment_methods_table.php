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
        Schema::create('save_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');

            $table->unsignedBigInteger('payment_method_id');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');

            $table->string('display_name');
            $table->string('last_four', 12)->nullable();
            $table->string('email')->nullable();
            $table->string('token');
            $table->dateTime('expires_at');
            $table->boolean('is_default');
            $table->mediumText('data')->nullable();
            $table->string('zip')->nullable();
            $table->smallInteger('exp_month')->nullable();
            $table->smallInteger('exp_year')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('save_payment_methods');
    }
};
