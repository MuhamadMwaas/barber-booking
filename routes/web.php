<?php

use App\Http\Controllers\Api\SalonScheduleController;
use App\Http\Controllers\Api\SocialApiAuthController;
use App\Http\Controllers\AppointmentPrintController;
use App\Http\Middleware\EnsureStaffDashboardAccess;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrintController;
use App\Models\Language;
use App\Models\Invoice;
use App\Services\TaxCalculatorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Staff Dashboard Subdomain
|--------------------------------------------------------------------------
|
| dashboard.lookupfriseur.com
|
*/

Route::domain('dashboard.lookupfriseur.com')
    ->middleware([EnsureStaffDashboardAccess::class])
    ->group(function () {

        Route::livewire('/', \App\Livewire\StaffDashboard::class)
            ->name('staff.dashboard');

        Route::livewire('/customers', \App\Livewire\CustomerLookup::class)
            ->name('staff.dashboard.customers');

        Route::livewire('/stats', \App\Livewire\StaffStats::class)
            ->name('staff.dashboard.stats');

        Route::livewire('/reports', \App\Livewire\StaffReports::class)
            ->name('staff.dashboard.reports');

        // The printable Z-Report document itself (opens in its own tab).
        Route::get('/report', [DailyReportController::class, 'show'])
            ->name('staff.dashboard.report.print');

        Route::get('/language/{code}', function (string $code) {
            $language = Language::query()
                ->where('is_active', true)
                ->where('code', $code)
                ->firstOrFail();

            session([
                'locale' => $language->code,
            ]);

            return redirect()->back();
        })->name('staff.dashboard.language');
    });



Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {

    $perm = Permission::firstOrCreate(
        ['name' => 'view_stats', 'guard_name' => 'web']
    );
    dd($perm);
    $LineTypeRegistry= app(\App\Services\InvoiceTemplate\LineTypeRegistry::class);
    dd($LineTypeRegistry->getGroupedOptionsForSelect());


    $TaxCalculatorService = app(TaxCalculatorService::class);

    $tax_result = $TaxCalculatorService->extractTax(200, 19);
    dd($tax_result);
    $net = $tax_result['net'];
    //      DB::table('jobs')
    //   ->where('id', 5)
    //   ->update(['available_at' => now()->timestamp]);
});

Route::get('/grant-view-stats', function () {
    $permission = Permission::firstOrCreate(
        ['name' => 'StaffDashboard:view_stats', 'guard_name' => 'web']
    );

    foreach (\Spatie\Permission\Models\Role::all() as $role) {
        $role->givePermissionTo($permission);
    }

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return 'StaffDashboard:view_stats granted to all roles.';
});

// CMS Page preview (admin only)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/cms-preview/{page}', [\App\Http\Controllers\Admin\CmsPagePreviewController::class, 'show'])
        ->name('admin.cms-preview');
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

    Route::get('/appointment/{appointment}/print', [AppointmentPrintController::class, 'print'])
        ->name('appointment.print');
});

Route::middleware(['web', EnsureStaffDashboardAccess::class])->group(function () {
    Route::livewire('/dashboard', \App\Livewire\StaffDashboard::class)
        ->name('staff.dashboard');

    Route::livewire('/dashboard/customers', \App\Livewire\CustomerLookup::class)
        ->name('staff.dashboard.customers');

    Route::livewire('/dashboard/stats', \App\Livewire\StaffStats::class)
        ->name('staff.dashboard.stats');

    Route::livewire('/dashboard/reports', \App\Livewire\StaffReports::class)
        ->name('staff.dashboard.reports');

    // The printable Z-Report document itself (opens in its own tab).
    Route::get('/dashboard/report', [DailyReportController::class, 'show'])
        ->name('staff.dashboard.report.print');

    Route::get('/dashboard/language/{code}', function (string $code) {
        $language = Language::query()
            ->where('is_active', true)
            ->where('code', $code)
            ->firstOrFail();

        session(['locale' => $language->code]);

        return redirect()->back();
    })->name('staff.dashboard.language');
});

Route::get('/internal/clear-cache', function (Request $request) {


    $output = [];
    $exitCode = 0;

    exec(
        'sudo /var/www/lookup.com/clear-my-cache.sh 2>&1',
        $output,
        $exitCode
    );

    if ($exitCode !== 0) {
        return response()->json([
            'success' => false,
            'message' => 'Cache clear failed',
            'output' => $output,
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Cache cleared successfully',
    ]);
});
