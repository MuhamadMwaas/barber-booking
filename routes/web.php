<?php

use App\Http\Controllers\Api\SalonScheduleController;
use App\Http\Controllers\Api\SocialApiAuthController;
use App\Http\Middleware\EnsureStaffDashboardAccess;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrintController;
use App\Models\Invoice;
use App\Services\TaxCalculatorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {

    $LineTypeRegistry= app(\App\Services\InvoiceTemplate\LineTypeRegistry::class);
    dd($LineTypeRegistry->getGroupedOptionsForSelect());
    dd(Invoice::find(2));


    $TaxCalculatorService = app(TaxCalculatorService::class);

    $tax_result = $TaxCalculatorService->extractTax(200, 19);
    dd($tax_result);
    $net = $tax_result['net'];
    //      DB::table('jobs')
    //   ->where('id', 5)
    //   ->update(['available_at' => now()->timestamp]);
});

// Salon Schedule API routes (protected by Filament auth)
Route::middleware(['web', 'auth'])->prefix('admin/api')->group(function () {
    Route::get('salon-schedules/{branchId}', [SalonScheduleController::class, 'show']);
    Route::post('salon-schedules/{branchId}', [SalonScheduleController::class, 'store']);
    Route::get('salon-schedules', [SalonScheduleController::class, 'index']);
});

Route::get('auth/google/redirect', [SocialApiAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [SocialApiAuthController::class, 'googleWebCallback']);

Route::get('/privacy', [PageController::class, 'privacy'])->name('page.privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('page.terms');



Route::get('/invoice-template/{template}/preview', [InvoiceTemplateController::class, 'preview'])
    ->name('invoice-template.preview');

/*
|--------------------------------------------------------------------------
| Print Web Routes (Browser Printing)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/invoice/{invoice}/print', [PrintController::class, 'print'])
        ->name('invoice.print');

    Route::get('/invoices/print-batch', [PrintController::class, 'printBatch'])
        ->name('invoices.print-batch');
});

Route::get('/dashboard', \App\Livewire\StaffDashboard::class)
    ->middleware(['web', EnsureStaffDashboardAccess::class])
    ->name('staff.dashboard');
