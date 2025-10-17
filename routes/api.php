<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvidersController;
use App\Http\Controllers\Api\ServicesController;
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
Route::post('/auth/request-otp', [OtpController::class, 'requestOtp']);
Route::post('/auth/verify-otp', [OtpController::class, 'verifyOtp']);
Route::post('/auth/reset-password', [OtpController::class, 'resetPassword']);


Route::get('/providers', [ProvidersController::class, 'index']);
Route::get('/providers/{id}', [ProvidersController::class, 'show']);

Route::get('/availability/provider', [AvailabilityController::class, 'getProviderAvailability']);
Route::get('/availability/calendar', [AvailabilityController::class, 'getAvailabilityCalendar']);

// Services Routes
Route::prefix('services')->name('services.')->group(function () {

    Route::get('/', [ServicesController::class, 'index'])
        ->name('index');

    Route::get('/{id}', [ServicesController::class, 'show'])
        ->name('show')
        ->where('id', '[0-9]+');
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::post('profile/change-password', [ProfileController::class, 'changePassword']);


    Route::prefix('appointments')->name('appointments.')->group(function () {


        Route::get('/', [AppointmentController::class, 'index'])
            ->name('index');

        Route::get('/{id}', [AppointmentController::class, 'show'])
            ->name('show')
            ->where('id', '[0-9]+');

        Route::get('/past', [AppointmentController::class, 'past'])
            ->name('past');

        Route::get('/statistics', [AppointmentController::class, 'statistics'])
            ->name('statistics');


        Route::get('/search', [AppointmentController::class, 'search'])
            ->name('search');


        Route::post('/{id}/cancel', [AppointmentController::class, 'cancel'])
            ->name('cancel')
            ->where('id', '[0-9]+');
    });
});
