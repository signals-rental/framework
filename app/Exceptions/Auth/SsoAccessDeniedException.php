<?php

namespace App\Exceptions\Auth;

use RuntimeException;

/**
 * Thrown when an SSO login cannot be resolved to an existing, active user.
 *
 * The message carried by this exception is user-safe — it is surfaced directly
 * to the visitor on the login page by the SSO callback controller (spec §4.2/§7).
 * Reasons include: a missing or unverified IdP email, no matching Signals user,
 * or a matched user whose account is inactive.
 */
class SsoAccessDeniedException extends RuntimeException
{
    /**
     * @param  string  $reason  A user-safe explanation suitable for display on the login page.
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    /**
     * Build the exception for a missing or unverified IdP email.
     */
    public static function unverifiedEmail(): self
    {
        return new self(__('We could not verify your email with that provider. Please contact your administrator.'));
    }

    /**
     * Build the exception for an email that matches no existing Signals user.
     */
    public static function noMatchingUser(): self
    {
        return new self(__('No Signals account matches that login. Please contact your administrator for access.'));
    }

    /**
     * Build the exception for a matched user whose account is deactivated.
     */
    public static function inactiveUser(): self
    {
        return new self(__('Your account is inactive. Please contact your administrator.'));
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
        return new self(__('Your email domain is not permitted for single sign-on. Please contact your administrator.'));
    }
}
