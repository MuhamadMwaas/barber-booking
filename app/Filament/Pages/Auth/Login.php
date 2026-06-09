<?php

namespace App\Filament\Pages\Auth;

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

        // ── Disabled account — show a clear notification instead of generic error ──
        if (! $user->is_active) {
            $this->fireFailedEvent($authGuard, $user, $credentials);

            Notification::make()
                ->danger()
                ->title(__('auth.account_disabled_title'))
                ->body(__('auth.account_disabled_body'))
                ->persistent()
                ->send();

            return null;
        }

        // ── Customer account — panel is for staff only ────────────────────────────
        if ($user->hasRole('customer')) {
            $this->fireFailedEvent($authGuard, $user, $credentials);

            Notification::make()
                ->danger()
                ->title(__('auth.customer_not_allowed_title'))
                ->body(__('auth.customer_not_allowed_body'))
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
