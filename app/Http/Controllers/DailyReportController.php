<?php

namespace App\Http\Controllers;

use App\Services\DailyReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Renders the printable Daily Report (Z-Report & Employee Summary).
 *
 * A standalone document rather than a Livewire page: the report is meant to be
 * opened in its own tab and printed, exactly like the invoice and appointment
 * tickets, so it carries its own <html> shell and print CSS instead of the
 * dashboard chrome.
 *
 * Route-level `EnsureStaffDashboardAccess` already requires
 * `StaffDashboard:access`; this controller adds the report-specific ability on
 * top, because the report exposes every employee's takings.
 */
class DailyReportController extends Controller
{
    /** Hard ceiling on the range, so one URL cannot try to render a decade. */
    private const MAX_RANGE_DAYS = 366;

    public function __construct(private readonly DailyReportService $reports)
    {
    }

    /**
     * GET /dashboard/report?from=Y-m-d&to=Y-m-d&providers[]=1&providers[]=2
     */
    public function show(Request $request)
    {
        $this->authorizeReports();

        $validated = $request->validate([
            'from'        => ['nullable', 'date_format:Y-m-d'],
            'to'          => ['nullable', 'date_format:Y-m-d'],
            'providers'   => ['nullable', 'array'],
            'providers.*' => ['integer'],
        ]);

        $from = $validated['from'] ?? Carbon::today()->toDateString();
        $to   = $validated['to'] ?? $from;

        [$from, $to] = $this->normaliseRange($from, $to);

        $report = $this->reports->build($from, $to, $validated['providers'] ?? []);

        $locale = app()->getLocale();

        return view('reports.daily-report', [
            'report' => $report,
            'locale' => $locale,
            'isRtl'  => $locale === 'ar',
        ]);
    }

    /**
     * The report is management information, so it needs its own ability on top
     * of dashboard access. SuperAdmin bypasses, mirroring the dashboard's own
     * `dashCan()` helper.
     */
    private function authorizeReports(): void
    {
        $user = auth()->user();

        abort_unless($user !== null, 403);

        if ($user->hasRole('SuperAdmin')) {
            return;
        }

        abort_unless($user->can('StaffDashboard:view_reports'), 403);
    }

    /**
     * Swap a reversed range instead of rendering an empty report, and refuse a
     * range longer than the ceiling rather than silently truncating it.
     *
     * @return array{0:string,1:string}
     */
    private function normaliseRange(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end   = Carbon::parse($to)->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) + 1 > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'to' => __('z_report.range_too_long', ['days' => self::MAX_RANGE_DAYS]),
            ]);
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
