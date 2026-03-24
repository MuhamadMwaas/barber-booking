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
use App\Http\Controllers\PrintController;
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

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{notificationId}/read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
    });


    Route::post('/register-device', [DevicesController::class, 'registerDevice']);
    Route::post('/deregister-device', [DevicesController::class, 'unregisterDevice']);


    // Appointments Routes
    Route::prefix('appointments')->name('appointments.')->group(function () {

        // List appointments with filters
        Route::get('/', [AppointmentController::class, 'index'])
            ->name('index');

        // Get appointment statistics
        Route::get('/statistics', [AppointmentController::class, 'statistics'])
            ->name('statistics');

        // Get upcoming appointments
        Route::get('/upcoming', [AppointmentController::class, 'upcoming'])
            ->name('upcoming');

        // Get past appointments
        Route::get('/past', [AppointmentController::class, 'past'])
            ->name('past');

        // Search appointments
        Route::get('/search', [AppointmentController::class, 'search'])
            ->name('search');

        // Show single appointment
        Route::get('/{id}', [AppointmentController::class, 'show'])
            ->name('show')
            ->where('id', '[0-9]+');

        // Cancel appointment
        Route::post('/{id}/cancel', [AppointmentController::class, 'cancel'])
            ->name('cancel')
            ->where('id', '[0-9]+');
    });

    // Appointment Reminders
    Route::post('/appointments/reminders', [\App\Http\Controllers\Api\AppointmentReminderController::class, 'store'])
        ->name('appointments.reminders.store');


    Route::middleware([ 'verified'])->prefix('bookings')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('/', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('/{id}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/{id}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    });
});

/*
|--------------------------------------------------------------------------
| Print API Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // Print endpoints
    Route::post('/invoice/{invoice}/print', [PrintController::class, 'apiPrint'])
        ->name('api.invoice.print');

    Route::post('/invoices/print-batch', [PrintController::class, 'apiPrintBatch'])
        ->name('api.invoices.print-batch');

    Route::get('/invoice/{invoice}/print-url', [PrintController::class, 'getPrintUrl'])
        ->name('api.invoice.print-url');

    // Printer management
    Route::post('/printer/{printer}/test', [PrintController::class, 'testPrinter'])
        ->name('api.printer.test');

    // Statistics & Logs
    Route::get('/print/statistics', [PrintController::class, 'statistics'])
        ->name('api.print.statistics');

    Route::get('/print/logs', [PrintController::class, 'logs'])
        ->name('api.print.logs');
});
