---
title: Magic-Link Login
description: Passwordless login for existing users via a single-use emailed link. Enabled by admins in Security settings; never bypasses 2FA.
---

## Overview

**Magic-link login** lets an existing Signals user sign in without their password by requesting a single-use link sent to their email address. The link is valid for **15 minutes** and can only be used once. If the user has Two-Factor Authentication (2FA) enabled — or 2FA is enforced on their role — the standard one-time-code challenge still runs after the link is consumed.

Magic-link login is **off by default**. An admin or owner enables it in **Settings → Security → Magic-Link Login**.

## How It Works

1. On the login page, the user clicks **Email me a login link** (visible only when the feature is enabled).
2. They enter their email address and submit the form.
3. Signals always responds with a neutral confirmation: *"If an account exists for that address, we've emailed a link."* — this message is shown whether or not an account was found, so the response never reveals whether an email address is registered.
4. If an active account exists, is eligible, and the feature is enabled, a single-use link is emailed to that address.
5. The user clicks the link in their email. Signals validates it and either:
   - **Signs them in** (if no 2FA), redirecting to the intended page, or
   - **Redirects to the 2FA challenge** (if 2FA is enabled on the account), where they enter their one-time code as normal.

> **Note:** Magic-link login replaces only the password step. 2FA is always required if it is enabled or enforced on the account.

## Enabling Magic-Link Login

Go to **Settings → Security → Magic-Link Login** and toggle **Allow magic-link login** on, then save. Only admins and owners can change this setting.

When the toggle is off, the **Email me a login link** affordance is hidden from the login page entirely and any outstanding links are rejected at consume time.

## Anti-Enumeration

Signals never reveals whether an email address corresponds to an account. The same neutral response is returned for every request — known account, unknown email, inactive account, or SSO-blocked role — and the email is dispatched asynchronously (queued) so response timing does not leak information.

## SSO-Enforced Roles

If a role has SSO enforcement enabled (configured in **Settings → Security → SSO Enforcement**), magic-link login is **blocked** for members of that role. The block is enforced at both the request step and again when the link is clicked, so disabling the feature or changing a user's enforcement status after a link is issued causes the link to be rejected.

**The Owner role is always exempt** from SSO enforcement and can always use magic-link login when the feature is enabled.

## Security

| Property | Detail |
|----------|--------|
| **Single-use** | Each link can be consumed exactly once. Requesting a new link invalidates any previously issued, unconsumed links for that account |
| **15-minute expiry** | Links expire 15 minutes after issue. Expired links are rejected with a generic error message |
| **Hashed token storage** | Only a SHA-256 hash of the token is stored in the database. The plaintext token travels only in the email. Lookup is by index on the hash — the plaintext itself is never compared in code |
| **Anti-enumeration** | The same neutral response is returned regardless of whether an account exists or the email is eligible |
| **Policy re-checked on click** | Feature-enabled, user-active, and SSO-enforcement checks are all repeated when the link is consumed — a policy change after issue invalidates the outstanding link |
| **2FA not bypassed** | Users with 2FA enabled are redirected to the standard one-time-code challenge after a successful link consume |
| **Rate-limited** | Two layers. The **request** step is throttled inside the action — up to 3 requests per email+IP and 10 per IP across all addresses within a 15-minute window, both of which silently no-op when tripped so they cannot leak account existence. The **consume** route is additionally rate-limited per IP via standard `throttle:6,1` middleware |

## Deployment Behind a Proxy or Load Balancer

> **Prerequisite for correct rate-limiting.** The request-step throttles are keyed on the client IP (`request()->ip()`). When Signals runs behind a reverse proxy, load balancer, or CDN, Laravel only resolves the **real client IP** if trusted-proxy support is configured — otherwise every request appears to come from the proxy's IP, which collapses the per-IP throttle across all users behind that proxy and weakens the anti-enumeration guarantees.

Configure trusted proxies in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(
        at: '*', // or the specific proxy / CIDR ranges you control
        headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
    );
})
```

Restrict `at:` to the proxy addresses you actually operate rather than `'*'` where possible. The same prerequisite applies to every IP-based throttle in Signals — password-login lockout, the magic-link consume route, and SSO.

## Settings Reference

| Setting key | Type | UI location |
|-------------|------|-------------|
| `security.magic_link_enabled` | bool | Settings → Security → Magic-Link Login |

## Routes

| Route | Description |
|-------|-------------|
| `GET /auth/magic-link/{token}` | Validates and consumes the magic-link token, then signs in the user or redirects to the 2FA challenge |
