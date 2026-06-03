<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Custom post-login redirect.
 *
 * Pure service providers (role = provider, without any admin/manager role) are
 * sent to the operational StaffDashboard instead of the Filament panel home,
 * because the dashboard — not /admin — is their primary workspace. Everyone
 * else keeps the default Filament behaviour (redirect to the panel home, or the
 * originally intended URL).
 *
 * NOTE: the return type mirrors Filament's own LoginResponse exactly
 * (RedirectResponse | Redirector). Login runs inside a Livewire component, so
 * the redirect() helper returns Livewire's Redirector — NOT a Symfony Response.
 */
class StaffLoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();

        if (
            $user
            && method_exists($user, 'hasRole')
            && $user->hasRole('provider')
            && ! $user->hasAnyRole(['admin', 'manager', 'SuperAdmin'])
        ) {
            return redirect()->to(route('staff.dashboard'));
        }

        return redirect()->intended(Filament::getUrl());
    }
}
