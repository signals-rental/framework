<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeMagicLink;
use App\Data\Auth\ConsumeMagicLinkData;
use App\Events\AuditableEvent;
use App\Exceptions\Auth\InvalidMagicLinkException;
use App\Http\Controllers\Auth\Concerns\HandlesPasswordlessTwoFactor;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Consumes an emailed magic-link login token (spec §8).
 *
 * The controller is intentionally thin: token validation, eligibility re-checks
 * and single-use marking live in {@see ConsumeMagicLink}. The success path
 * mirrors {@see SsoController} exactly so the existing two-factor-challenge
 * component completes the login when 2FA is enabled, and the audit trail is
 * recorded identically on both the 2FA and non-2FA paths.
 */
class MagicLinkController extends Controller
{
    use HandlesPasswordlessTwoFactor;

    public function __construct(
        private readonly ConsumeMagicLink $consumeMagicLink,
    ) {}

    /**
     * Validate the token and log the resolved user in.
     *
     * Flow (spec §8):
     *   1. Consume the token (any failure → login with one generic message).
     *   2. If the user has 2FA enabled, hand off to the existing challenge flow
     *      (login is completed there); otherwise authenticate immediately.
     *
     * @param  string  $token  The plaintext magic-link secret from the URL.
     * @return RedirectResponse Redirect to the dashboard, two-factor challenge,
     *                          or back to the login page with a generic error.
     */
    public function consume(string $token): RedirectResponse
    {
        try {
            $user = ($this->consumeMagicLink)(new ConsumeMagicLinkData(secret: $token));
        } catch (InvalidMagicLinkException) {
            return redirect()->route('login')->withErrors([
                'email' => __('That sign-in link is invalid or has expired.'),
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->challengeTwoFactor($user, 'magic_link_login', true);
        }

        Auth::login($user);
        // Rotate the session id on the non-2FA path. The 2FA branch regenerates
        // inside the HandlesPasswordlessTwoFactor trait — don't try to consolidate
        // them: the trait rotation happens before the user is logged in (it's a
        // mid-flight hand-off to the challenge), this one happens after login.
        Session::regenerate();

        $this->recordMagicLinkLogin($user);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Record a successful magic-link login in the audit trail.
     *
     * Reuses the established {@see AuditableEvent} → `LogAction` mechanism. The
     * non-2FA path calls it directly here; the 2FA path defers to the two-factor
     * challenge component, which fires the same event after it completes login.
     */
    protected function recordMagicLinkLogin(User $user): void
    {
        event(new AuditableEvent(
            model: $user,
            action: 'auth.magic_link_login',
        ));
    }
}
