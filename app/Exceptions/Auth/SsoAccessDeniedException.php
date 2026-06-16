<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Thrown when an SSO login cannot be resolved to an existing, active user.
 *
 * Two messages are carried, deliberately separated to avoid user/status
 * enumeration (spec §4.2/§7):
 *   - {@see $reason} is user-safe and surfaced directly to the visitor on the
 *     login page by the SSO callback controller. To stop an attacker on a
 *     permitted SSO domain from distinguishing "no account" from "inactive
 *     account", the no-matching-user and inactive-user cases share one generic
 *     message; only operators with server-side access can tell them apart.
 *   - {@see $logReason} is a stable machine code (never displayed) carrying the
 *     specific cause for diagnostics/audit logging.
 */
class SsoAccessDeniedException extends RuntimeException
{
    /**
     * @param  string  $reason  A user-safe explanation suitable for display on the login page.
     * @param  string  $logReason  A stable machine code identifying the specific cause, for
     *                             server-side diagnostics/audit only — never displayed to the visitor.
     */
    public function __construct(
        public readonly string $reason,
        public readonly string $logReason,
    ) {
        parent::__construct($reason);
    }

    /**
     * Build the exception for a missing or unverified IdP email.
     */
    public static function unverifiedEmail(): self
    {
        return new self(
            __('We could not verify your email with that provider. Please contact your administrator.'),
            'unverified_email',
        );
    }

    /**
     * Build the exception for an email that matches no existing Signals user.
     *
     * Shares the generic {@see $reason} with {@see inactiveUser()} so the two
     * cases are indistinguishable to the visitor; the specific cause is carried
     * by {@see $logReason} for server-side diagnostics.
     */
    public static function noMatchingUser(): self
    {
        return new self(self::accessDeniedMessage(), 'no_matching_user');
    }

    /**
     * Build the exception for a matched user whose account is deactivated.
     *
     * Shares the generic {@see $reason} with {@see noMatchingUser()} so the two
     * cases are indistinguishable to the visitor; the specific cause is carried
     * by {@see $logReason} for server-side diagnostics.
     */
    public static function inactiveUser(): self
    {
        return new self(self::accessDeniedMessage(), 'inactive_user');
    }

    /**
     * Build the exception for an IdP email whose domain is not on the configured allow-list.
     *
     * Domain gating is the chosen mitigation for cross-tenant account takeover with
     * Microsoft's multitenant `common` setting (the IdP `email` claim is not a
     * trustworthy identity across arbitrary Azure tenants — "nOAuth"). When an
     * allow-list of owned domains is configured, only those domains may sign in.
     */
    public static function domainNotAllowed(): self
    {
        return new self(
            __('Your email domain is not permitted for single sign-on. Please contact your administrator.'),
            'domain_not_allowed',
        );
    }

    /**
     * The shared, generic user-facing message for the no-matching-user and
     * inactive-user cases — identical text prevents account/status enumeration.
     */
    private static function accessDeniedMessage(): string
    {
        return __('We could not sign you in with that account. Please contact your administrator.');
    }
}
