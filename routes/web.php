<?php

use App\Http\Controllers\Api\SalonScheduleController;
use App\Http\Controllers\Api\SocialApiAuthController;
use App\Http\Controllers\AppointmentPrintController;
use App\Http\Middleware\EnsureStaffDashboardAccess;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PrintController;
use App\Models\Language;
use App\Models\Invoice;
use App\Services\TaxCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Staff Dashboard Subdomain
|--------------------------------------------------------------------------
|
| dashboard.lookupfriseur.com
|
*/

// Registered ONCE. Previously this block existed twice — once under
// Route::domain('dashboard.lookufriseur.com') with paths /reports, /stats … and
// once under a /dashboard prefix — and BOTH used the same route names. Laravel
// keeps a single name → route lookup, so route('staff.dashboard.reports') always
// resolved to the SUBDOMAIN variant and produced http://<host>/reports, which
// 404s on the local/main host. Driving the domain from config keeps one set of
// names that is always correct for the host this environment serves.
$staffDashboardDomain = config('app.staff_dashboard_domain');

Route::domain($staffDashboardDomain ?: null)
    ->prefix($staffDashboardDomain ? '' : 'dashboard')
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



/*
|--------------------------------------------------------------------------
| Public Marketing Site
|--------------------------------------------------------------------------
|
| The landing page and the app-download page it links to. Content for both
| lives in `landing_sections` and is edited from the "Landing Page" screen in
| the admin panel. The active language comes from SetLocaleFromSession, which
| the /language/{code} route below writes to.
|
*/

Route::get('/', [LandingController::class, 'index'])
    ->name('landing');

Route::get('/app', [LandingController::class, 'app'])
    ->name('landing.app');

Route::get('/language/{code}', [LandingController::class, 'switchLanguage'])
    ->name('landing.language');

// Legal / informational pages (Impressum, Datenschutz, AGB). The content is the
// same `cms_pages` payload the mobile app reads; this route renders it inside
// the public site so the footer links resolve to a real page.
Route::get('/page/{slug}', [LandingController::class, 'page'])
    ->name('landing.page');

Route::get('/test', function () {

    // NOTE: this route used to open with
    //   Permission::firstOrCreate(['name' => 'view_stats', 'guard_name' => 'web']);
    // which minted an UNPREFIXED `view_stats` permission (the real one is
    // `StaffDashboard:view_stats`). Because the Roles screen groups permissions by
    // the prefix before ":", that stray row showed up as its own junk tab and
    // matched nothing in the code. Removed — do not reintroduce.
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

// REMOVED: GET /grant-view-stats — an UNAUTHENTICATED route that granted
// StaffDashboard:view_stats to EVERY role (customers included) and cleared the
// permission cache. Anyone who hit the URL silently undid whatever the admin had
// just revoked on the Roles screen, which is exactly the "I can't deny this
// permission" symptom. Grant permissions from /admin/roles instead.

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

// NOTE: the duplicate /dashboard/* group that used to live here was merged into
// the single config-driven group at the top of this file. Do not re-add it — two
// groups sharing the same route names is what broke route('staff.dashboard.*').

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
