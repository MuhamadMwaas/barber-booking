<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branchs')
            ->where('name', 'Main Branch - Downtown')
            ->update(['name' => 'Look up - Landshut-Rupprechtstraße']);
    }

    public function down(): void
    {
        DB::table('branchs')
            ->where('name', 'Look up - Landshut-Rupprechtstraße')
            ->update(['name' => 'Main Branch - Downtown']);
    }
};
