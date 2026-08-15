<?php

use App\Http\Controllers\Api\AboutUsPageController;
use App\Http\Controllers\Api\CmsPageController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DevicesController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PhoneVerificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProvidersController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SliderController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Services\VonageSdkSmsService;
use App\Http\Controllers\PrintController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| CMS Pages (public — no auth required)
|--------------------------------------------------------------------------
*/
Route::get('/pages/{slug}', [CmsPageController::class, 'show'])
    ->name('api.cms-pages.show');


Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    // Forgot-password over email or SMS. Throttles are per IP; PasswordResetController
    // adds a second, per-destination cooldown so SMS credit cannot be burned by a
    // client that rotates its address.
    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('api.password.forgot');
    Route::post('password/verify-otp', [PasswordResetController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('api.password.verify-otp');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])
        ->middleware('throttle:10,1')
        ->name('api.password.reset');

    Route::post('request-otp', [OtpController::class, 'requestOtp']);
    Route::post('verify-otp', [OtpController::class, 'verifyOtp']);
    Route::post('google', [SocialAuthController::class, 'google']);

    // Google Authentication
    Route::post('google/mobile', [SocialAuthController::class, 'googleMobile']);

    // اختياري: للويب
    Route::get('google/redirect', [SocialAuthController::class, 'googleWebRedirect']);
    Route::get('google/callback', [SocialAuthController::class, 'googleWebCallback']);


    Route::post('verify-email-otp', [OtpController::class, 'verifyEmailViaOtp']);
    Route::post('resend-verification-otp', [OtpController::class, 'resendVerificationOtp']);
});

Route::post('/test/vonage-sms', function (Request $request, VonageSdkSmsService $sms) {
    abort_unless(app()->environment('local') || config('app.debug'), 404);

    $payload = $request->validate([
        'phone' => ['required', 'string', 'max:20'],
        'text' => ['required', 'string', 'max:1000'],
    ]);

    try {
        $result = $sms->send($payload['phone'], $payload['text']);
    } catch (\RuntimeException $exception) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], 502);
    }

    if ($result['skipped']) {
        return response()->json([
            'success' => false,
            'message' => 'Vonage is not fully configured.',
        ], 422);
    }

    return response()->json([
        'success' => true,
        'message' => 'SMS sent successfully.',
        'to' => $result['to'],
        'from' => $result['from'],
        'message_ids' => $result['message_ids'],
        'remaining_balance' => $result['remaining_balance'],
    ]);
});






// ── Static Pages ──────────────────────────────────────────────────────────────
Route::get('/about-us', [AboutUsPageController::class, 'show'])->name('api.about-us.show');

// ── Sliders ───────────────────────────────────────────────────────────────────
// GET /api/sliders/{key}?lang=ar|en|de
// مثال: /api/sliders/home?lang=ar
Route::get('/sliders/{key}', [SliderController::class, 'show'])
    ->name('api.sliders.show')
    ->where('key', '[a-z0-9_\-]+');

Route::get('/providers', [ProvidersController::class, 'index']);
Route::get('/providers/{id}', [ProvidersController::class, 'show']);

// Availability lookups are public AND the most expensive reads in the API: every
// provider × day pair runs its own schedule/leave/appointment queries, so a single
// calendar call without `provider_id` fans out to hundreds of queries. Unthrottled
// that is a cheap way to flatten the database, hence a per-IP limit on each.
//
// The limits are deliberately generous rather than tight: these routes are
// unauthenticated, so the throttle key is the IP address, and mobile carriers put
// large numbers of real customers behind a single CGNAT address. A browsing user
// needs only a handful of calls per minute; the cap is there to stop scripts, not
// to police normal use.
Route::get('/availability/service', [AvailabilityController::class, 'getServiceAvailability'])
    ->middleware('throttle:40,1')
    ->name('api.availability.service');
Route::get('/availability/provider', [AvailabilityController::class, 'getProviderAvailability'])
    ->middleware('throttle:40,1')
    ->name('api.availability.provider');
// Half the allowance: this one multiplies the per-day cost by up to 31 days.
Route::get('/availability/calendar', [AvailabilityController::class, 'getAvailabilityCalendar'])
    ->middleware('throttle:30,1')
    ->name('api.availability.calendar');

// Services Routes
Route::prefix('services')->name('services.')->group(function () {

    Route::get('/', [ServicesController::class, 'index'])
        ->name('index');

    Route::get('/{id}', [ServicesController::class, 'show'])
        ->name('show')
        ->where('id', '[0-9]+');
});


Route::middleware(['auth:sanctum', 'verified.customer'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::post('profile/change-password', [ProfileController::class, 'changePassword']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    // Post-login phone-number verification (settings screen).
    Route::post('profile/phone/send-otp', [PhoneVerificationController::class, 'sendOtp'])
        ->middleware('throttle:6,1')
        ->name('profile.phone.send-otp');
    Route::post('profile/phone/verify-otp', [PhoneVerificationController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('profile.phone.verify-otp');


    Route::prefix('noticifation')->name('noticifation.')->group(function () {
        Route::post('/test-send-to-all', [NotificationController::class, 'testSendToAll'])->name('test-send-to-all');
        Route::post('/test-send-to-customers', [NotificationController::class, 'testSendToAllCustomers'])->name('test-send-to-customers');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('unread');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{notificationId}/read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
        Route::delete('/{notificationId}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    // User-facing application settings (reminder channels, …).
    // GET lists the catalog + current values; PATCH updates any single option.
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('settings/{key}', [SettingsController::class, 'update'])->name('settings.update');


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
    Route::prefix('bookings')->group(function () {
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
Route::middleware(['auth:sanctum', 'verified.customer'])->group(function () {

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
