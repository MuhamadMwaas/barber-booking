<?php

use App\Http\Controllers\Api\SalonScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Salon Schedule API routes (protected by Filament auth)
Route::middleware(['web', 'auth'])->prefix('admin/api')->group(function () {
    Route::get('salon-schedules/{branchId}', [SalonScheduleController::class, 'show']);
    Route::post('salon-schedules/{branchId}', [SalonScheduleController::class, 'store']);
    Route::get('salon-schedules', [SalonScheduleController::class, 'index']);
});
