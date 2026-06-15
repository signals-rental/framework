<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\SsoController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

/**
 * Shared two-factor hand-off for passwordless logins (SSO and magic-link).
 *
 * Both the {@see SsoController} and
 * {@see MagicLinkController} hand a 2FA-enabled user to
 * the existing two-factor-challenge component rather than authenticating directly:
 * the user is NOT logged in here — the challenge calls `Auth::loginUsingId()` once
 * the second factor is verified. A session audit flag is stashed so the challenge
 * can fire the right audit event after it completes the login.
 *
 * The set of known passwordless audit flags is owned here so contamination cannot
 * occur: every hand-off clears ALL of them before setting the one it needs, so a
 * stale flag from an abandoned login can never mis-audit the next one.
 */
trait HandlesPasswordlessTwoFactor
{
    /**
     * Expose the canonical passwordless audit-flag registry.
     *
     * The list of session keys lives in one place: callers that hand off (this
     * trait) and callers that audit (the two-factor challenge Volt component)
     * both read the same array, so a flag added here is automatically known to
     * both ends. Returned by value rather than held as a `$this` property to
     * stay Octane-safe (no shared static state to accumulate or mutate across
     * long-lived requests).
     *
     * @return list<string>
     */
    public static function passwordlessAuditFlags(): array
    {
        return ['sso_provider', 'magic_link_login'];
    }

    /**
     * Forget every passwordless audit flag in the session.
     *
     * Used by the two-factor challenge after it has audited the login so a stale
     * flag can never bleed into a later one, and internally by
     * {@see self::challengeTwoFactor()} so a hand-off cannot inherit an abandoned
     * attempt's flag.
     */
    public static function clearPasswordlessAuditFlags(): void
    {
        foreach (self::passwordlessAuditFlags() as $flag) {
            Session::forget($flag);
        }
    }

    /**
     * Hand a 2FA-enabled user off to the existing two-factor challenge.
     *
     * Clears every known passwordless audit flag first (so an abandoned login can
     * never leave a stale flag behind), sets the one for this login, rotates the
     * session, and stores the pending user id for the challenge to complete.
     *
     * @param  string  $auditFlagKey  Session key identifying the login type
     *                                (`sso_provider` or `magic_link_login`).
     * @param  mixed  $auditFlagValue  Value the challenge audits against (the SSO
     *                                 provider key, or `true` for magic-link).
     * @return RedirectResponse Redirect to the two-factor challenge page.
     */
    protected function challengeTwoFactor(User $user, string $auditFlagKey, mixed $auditFlagValue): RedirectResponse
    {
        Session::forget('two_factor_confirmed');

        self::clearPasswordlessAuditFlags();

        Session::regenerate();
        Session::put('two_factor_user_id', $user->id);
        Session::put($auditFlagKey, $auditFlagValue);

        return redirect()->route('two-factor.challenge');
    }
}
