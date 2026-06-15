<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveSsoUser;
use App\Events\AuditableEvent;
use App\Exceptions\Auth\SsoAccessDeniedException;
use App\Http\Controllers\Auth\Concerns\HandlesPasswordlessTwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Drives the Single Sign-On OAuth handshake for staff login (spec §7).
 *
 * The controller is intentionally thin: provider availability and the Octane-safe
 * driver construction live in {@see SsoService}, and identity resolution (auto-link
 * by verified email, deny on no/inactive match) lives in {@see ResolveSsoUser}. The
 * callback mirrors the password-login two-factor flow exactly so the existing
 * two-factor-challenge component completes the login.
 */
class SsoController extends Controller
{
    use HandlesPasswordlessTwoFactor;

    public function __construct(
        private readonly SsoService $sso,
        private readonly ResolveSsoUser $resolveSsoUser,
    ) {}

    /**
     * Begin the OAuth handshake by redirecting to the provider's consent screen.
     *
     * Unavailable providers (unknown, disabled, or unconfigured) 404 — the route
     * constraint already rejects unknown provider keys, and this guards the case
     * where a known provider has been turned off or has no credentials.
     *
     * @param  string  $provider  The provider key (e.g. `google`, `microsoft`).
     * @return SymfonyRedirectResponse Redirect to the provider's OAuth consent screen.
     *
     * @throws NotFoundHttpException When the provider is disabled or unconfigured.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        if (! $this->sso->available($provider)) {
            abort(404);
        }

        return $this->sso->driver($provider)->redirect();
    }

    /**
     * Complete the OAuth handshake and log the resolved user in.
     *
     * Flow (spec §7):
     *   1. Guard the provider is still available.
     *   2. Exchange the authorization code for the IdP user (handshake failures →
     *      friendly error back on the login page).
     *   3. Resolve to an existing, active Signals user (deny → login with reason).
     *   4. If the user has 2FA enabled, hand off to the existing challenge flow
     *      (login is completed there); otherwise authenticate immediately.
     *
     * @param  string  $provider  The provider key (e.g. `google`, `microsoft`).
     * @return RedirectResponse Redirect to the dashboard, two-factor challenge, or
     *                          back to the login page with an error.
     *
     * @throws NotFoundHttpException When the provider is disabled or unconfigured.
     */
    public function callback(string $provider): RedirectResponse
    {
        if (! $this->sso->available($provider)) {
            abort(404);
        }

        try {
            $socialiteUser = $this->sso->driver($provider)->user();
        } catch (InvalidStateException|\Exception $e) {
            // Surface CSRF (InvalidStateException) and provider misconfiguration for
            // operators without leaking any details to the visitor.
            Log::warning('sso.callback_failed', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('Single sign-on failed. Please try again.'),
            ]);
        }

        try {
            $user = ($this->resolveSsoUser)($provider, $socialiteUser);
        } catch (SsoAccessDeniedException $e) {
            return redirect()->route('login')->withErrors([
                'email' => $e->reason,
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->challengeTwoFactor($user, 'sso_provider', $provider);
        }

        Auth::login($user);
        Session::regenerate();

        $this->recordSsoLogin($user, $provider);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Record a successful SSO login in the audit trail.
     *
     * Reuses the established {@see AuditableEvent} → `LogAction` mechanism, which
     * reads the authenticated user for `user_id`; this is only called once login
     * has completed. The non-2FA path calls it directly here; the 2FA path defers
     * to the two-factor challenge component, which fires the same event (keyed on
     * the `sso_provider` session value) after it completes the login.
     */
    protected function recordSsoLogin(User $user, string $provider): void
    {
        event(new AuditableEvent(
            model: $user,
            action: 'auth.sso_login',
            metadata: ['provider' => $provider],
        ));
    }
}
