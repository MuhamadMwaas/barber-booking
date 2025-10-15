<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvidersController;
use App\Http\Controllers\Api\SocialAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('google', [SocialAuthController::class, 'google']);
});

Route::get('/providers', [ProvidersController::class, 'index']);
Route::get('/providers/{id}', [ProvidersController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::post('profile/change-password', [ProfileController::class, 'changePassword']);
});

Route::post('/auth/request-otp', [OtpController::class, 'requestOtp']);
Route::post('/auth/verify-otp', [OtpController::class, 'verifyOtp']);
Route::post('/auth/reset-password', [OtpController::class, 'resetPassword']);