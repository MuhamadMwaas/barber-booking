<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('booking_source', 20)
                ->default('in_person')
                ->after('created_status')
                ->comment('online: from API/mobile app | in_person: from staff dashboard or filament admin');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('booking_source');
        });
    }
};
