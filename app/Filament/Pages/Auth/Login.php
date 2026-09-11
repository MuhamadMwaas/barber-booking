<?php

namespace App\Filament\Pages\Auth;

use App\Support\StaffLoginDenial;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\MultiFactor\Contracts\HasBeforeChallengeHook;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Override authenticate() to intercept disabled accounts before the generic
     * "credentials do not match" error fires — so we can show a clear message.
     *
     * Structure mirrors Filament\Auth\Pages\Login::authenticate() with a single
     * is_active check injected after credential validation.
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard    = Filament::auth();
        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials  = $this->getCredentialsFromFormData($data);
        $user         = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        // ── May this account sign in to a staff surface at all? ───────────────────
        //
        // Asked through StaffLoginDenial so this page and the Staff Dashboard's
        // own login (StaffAuthController) cannot answer it differently — the same
        // reason User::isActiveStaff() exists. Two files answering one
        // authorisation question is how the original gap opened.
        //
        // Note this replaces an older `hasRole('customer')` check: a user with NO
        // role passed that and then failed canAccessPanel(), so they were told
        // "these credentials do not match our records" — a lie that sends them to
        // reset a password that was never the problem.
        if ($reason = StaffLoginDenial::for($user)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);

            Notification::make()
                ->danger()
                ->title(StaffLoginDenial::title($reason))
                ->body(StaffLoginDenial::body($reason))
                ->persistent()
                ->send();

            return null;
        }
        // ─────────────────────────────────────────────────────────────────────────

        // Multi-factor authentication challenge
        if (
            filled($this->userUndertakingMultiFactorAuthentication) &&
            decrypt($this->userUndertakingMultiFactorAuthentication) === $user->getAuthIdentifier()
        ) {
            if ($this->isMultiFactorChallengeRateLimited($user)) {
                return null;
            }

            $this->multiFactorChallengeForm->validate();
        } else {
            foreach (Filament::getMultiFactorAuthenticationProviders() as $mfaProvider) {
                if (! $mfaProvider->isEnabled($user)) {
                    continue;
                }

                $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());

                if ($mfaProvider instanceof HasBeforeChallengeHook) {
                    $mfaProvider->beforeChallenge($user);
                }

                break;
            }

            if (filled($this->userUndertakingMultiFactorAuthentication)) {
                $this->multiFactorChallengeForm->fill();

                return null;
            }
        }

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
