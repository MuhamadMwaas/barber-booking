<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DevicesController;
use App\Http\Controllers\Api\NotificationController;
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

    // Google Authentication
    Route::post('google/mobile', [SocialAuthController::class, 'googleMobile']);

    // اختياري: للويب
    Route::get('google/redirect', [SocialAuthController::class, 'googleWebRedirect']);
    Route::get('google/callback', [SocialAuthController::class, 'googleWebCallback']);


    Route::post('verify-email-otp', [OtpController::class, 'verifyEmailViaOtp']);
    Route::post('resend-verification-otp', [OtpController::class, 'resendOtpForEmailVerification']);
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


Route::prefix('noticifation')->name('noticifation.')->group(function () {
    Route::post('/test-send-to-all', [NotificationController::class, 'testSendToAll'])->name('test-send-to-all');


});


    Route::post('/register-device', [DevicesController::class, 'registerDevice']);
    Route::post('/deregister-device', [DevicesController::class, 'unregisterDevice']);


    Route::middleware(['auth:sanctum', 'verified'])->prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('/', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/{id}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/{id}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    });
});

Route::middleware('auth:sanctum')->post('/appointments/reminders', [\App\Http\Controllers\Api\AppointmentReminderController::class, 'store']);
