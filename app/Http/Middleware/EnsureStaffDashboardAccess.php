<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = filament()->auth();

        if (! $auth->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        if (! $auth->user()->can('StaffDashboard:access')) {
            abort(403);
        }

        return $next($request);
    }
}
