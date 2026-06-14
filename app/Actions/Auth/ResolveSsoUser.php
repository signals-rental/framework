<?php

namespace App\Actions\Auth;

use App\Exceptions\Auth\SsoAccessDeniedException;
use App\Models\OAuthIdentity;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Resolves an authenticated Socialite identity to an existing Signals user.
 *
 * Implements the auto-link-by-verified-email policy (spec §4.2, decision D1):
 * there is no self-registration. An IdP login only succeeds for a user that
 * already exists and is active; unknown or unverified emails are denied.
 */
class ResolveSsoUser
{
    /**
     * Resolve the Signals user for an authenticated Socialite identity.
     *
     * Resolution order:
     *   1. Existing link — match an {@see OAuthIdentity} by `(provider, provider_id)`.
     *   2. Auto-link — require a verified IdP email and match an active user by email,
     *      creating the link on first login.
     *
     * The configured email-domain allow-list (spec §10 — cross-tenant takeover
     * mitigation) is enforced on BOTH paths: when the allow-list is non-empty the
     * IdP email's domain must be on it, so a now-disallowed domain cannot sign in
     * even with a pre-existing link.
     *
     * @param  string  $provider  The SSO provider key (e.g. `google`, `microsoft`).
     * @param  SocialiteUser  $socialiteUser  The authenticated Socialite identity.
     *
     * @throws SsoAccessDeniedException When the identity cannot be resolved to an active user.
     */
    public function __invoke(string $provider, SocialiteUser $socialiteUser): User
    {
        $this->assertEmailDomainAllowed($socialiteUser);

        $identity = OAuthIdentity::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $socialiteUser->getId())
            ->with('user')
            ->first();

        if ($identity !== null) {
            $user = $identity->user;

            if ($user === null) {
                throw SsoAccessDeniedException::noMatchingUser();
            }

            if (! $user->isActive()) {
                throw SsoAccessDeniedException::inactiveUser();
            }

            return $user;
        }

        if (! $this->emailIsVerified($provider, $socialiteUser)) {
            throw SsoAccessDeniedException::unverifiedEmail();
        }

        $email = (string) $socialiteUser->getEmail();

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw SsoAccessDeniedException::noMatchingUser();
        }

        if (! $user->isActive()) {
            throw SsoAccessDeniedException::inactiveUser();
        }

        $user->oauthIdentities()->create([
            'provider' => $provider,
            'provider_id' => (string) $socialiteUser->getId(),
            'email' => $email,
        ]);

        return $user;
    }

    /**
     * Enforce the configured email-domain allow-list against the IdP email.
     *
     * When `sso.allowed_email_domains` is empty (the default) any domain is
     * permitted and behaviour is unchanged. When it is non-empty, the IdP email
     * must carry a domain (the part after the last `@`, lowercased) that is on the
     * list — otherwise sign-in is denied. A missing/domain-less email with a
     * non-empty allow-list is denied (the IdP identity cannot be trusted).
     *
     * @throws SsoAccessDeniedException When the email's domain is not permitted.
     */
    private function assertEmailDomainAllowed(SocialiteUser $socialiteUser): void
    {
        $allowed = $this->allowedEmailDomains();

        if ($allowed === []) {
            return;
        }

        $email = $socialiteUser->getEmail();
        $domain = is_string($email) ? $this->domainOf($email) : null;

        if ($domain === null || ! in_array($domain, $allowed, true)) {
            throw SsoAccessDeniedException::domainNotAllowed();
        }
    }

    /**
     * Read and normalise the configured allow-list of owned email domains.
     *
     * @return list<string>
     */
    private function allowedEmailDomains(): array
    {
        $configured = settings('sso.allowed_email_domains');

        if (! is_array($configured)) {
            return [];
        }

        $domains = [];

        foreach ($configured as $domain) {
            if (! is_string($domain)) {
                continue;
            }

            $normalised = strtolower(trim($domain));

            if ($normalised !== '') {
                $domains[] = $normalised;
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Extract the lowercased domain (after the last `@`) from an email address.
     */
    private function domainOf(string $email): ?string
    {
        $email = trim($email);
        $position = strrpos($email, '@');

        if ($position === false) {
            return null;
        }

        $domain = strtolower(substr($email, $position + 1));

        return $domain === '' ? null : $domain;
    }

    /**
     * Determine whether the IdP asserts a verified email for the given identity.
     *
     * Provider-specific gates (spec §10):
     *   - **Google** returns an `email_verified` flag in the raw user payload; we
     *     require it to be strictly true.
     *   - **Microsoft** (Azure AD) does not return an `email_verified` field — the
     *     email derives from the authenticated work/school/personal MS account, so
     *     a present, non-empty email is treated as verified.
     */
    private function emailIsVerified(string $provider, SocialiteUser $socialiteUser): bool
    {
        $email = $socialiteUser->getEmail();

        if (! is_string($email) || trim($email) === '') {
            return false;
        }

        return match ($provider) {
            'google' => $this->rawFlagIsTrue($socialiteUser, 'email_verified'),
            // Microsoft has no email_verified signal; the authenticated MS account
            // email is treated as verified once we know it is present and non-empty.
            'microsoft' => true,
            default => false,
        };
    }

    /**
     * Read a boolean flag from the Socialite raw user payload.
     *
     * Reads the payload via the `getRaw()` accessor (provided by every concrete
     * Socialite user — `Laravel\Socialite\AbstractUser`) rather than the public
     * `$user` property, which the {@see SocialiteUser} interface does not expose.
     */
    private function rawFlagIsTrue(SocialiteUser $socialiteUser, string $key): bool
    {
        $raw = method_exists($socialiteUser, 'getRaw') ? $socialiteUser->getRaw() : [];

        return is_array($raw) && ($raw[$key] ?? false) === true;
    }
}
