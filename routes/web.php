<?php

use App\Http\Controllers\Api\SalonScheduleController;
use App\Http\Controllers\Api\SocialApiAuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {

    DB::table('jobs')
  ->where('id', 5)
  ->update(['available_at' => now()->timestamp]);
});

// Salon Schedule API routes (protected by Filament auth)
Route::middleware(['web', 'auth'])->prefix('admin/api')->group(function () {
    Route::get('salon-schedules/{branchId}', [SalonScheduleController::class, 'show']);
    Route::post('salon-schedules/{branchId}', [SalonScheduleController::class, 'store']);
    Route::get('salon-schedules', [SalonScheduleController::class, 'index']);
});

Route::get('auth/google/redirect', [SocialApiAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [SocialApiAuthController::class, 'googleWebCallback']);

Route::get('/privacy',[PageController::class,'privacy'])->name('page.privacy');
Route::get('/terms',[PageController::class,'terms'])->name('page.terms');
