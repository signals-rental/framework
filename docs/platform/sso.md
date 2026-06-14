---
title: Single Sign-On (SSO)
description: Let staff log in with Google or Microsoft 365. Admins configure OAuth credentials, enforce SSO per role, and 2FA still applies after sign-in.
---

## Overview

**Single Sign-On (SSO)** lets your staff log in to Signals using their existing **Google** or **Microsoft 365** account instead of — or in place of — a password. SSO is a login-only feature: it does not create new user accounts. The member (user) record must already exist in Signals, and only users whose email address is matched to an active account will be allowed in. Unknown or unverified emails are denied.

SSO is powered by **Laravel Socialite** with the `google` and `microsoft` (Azure, `common` tenant) providers. Configuration and enforcement are controlled in two places:

| Setting location | Purpose |
|-----------------|---------|
| **Settings → Integrations → Single Sign-On** | Enable providers and supply OAuth credentials (self-hosted) |
| **Settings → Security → SSO Enforcement** | Require specific roles to use SSO instead of a password |

## How SSO Login Works

When a user clicks **Continue with Google** or **Continue with Microsoft** on the login page:

1. They are redirected to the provider's OAuth consent screen.
2. After they approve, the provider sends them back to Signals at `/auth/{provider}/callback`.
3. Signals checks that the returned email address is **verified** by the provider.
4. The verified email is matched against an existing, active Signals user:
   - **Matched** — the account is linked (or an existing link is reused) and the user is signed in.
   - **No match** — login is denied. The user must be invited and have an active account before using SSO.
5. If the user has **Two-Factor Authentication (2FA)** enabled, they are prompted for their one-time code before the login completes, exactly as with password login.

> **Note:** SSO replaces only the password step. 2FA is still required if enabled on the account.

## Enabling SSO — Self-Hosted

On a self-hosted Signals install, you register OAuth credentials with each provider and enter them in **Settings → Integrations → Single Sign-On**.

### Configuring Google

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and create (or select) a project.
2. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
3. Choose **Web application** as the application type.
4. Under **Authorised redirect URIs**, add:
   ```
   https://<your-domain>/auth/google/callback
   ```
5. Copy the **Client ID** and **Client Secret**.
6. In Signals, go to **Settings → Integrations → Single Sign-On → Google**.
7. Toggle **Enable Google login** on, paste in the Client ID and Client Secret, and save.

### Configuring Microsoft 365

1. Open the [Azure portal](https://portal.azure.com/) and go to **Azure Active Directory → App registrations → New registration**.
2. Under **Supported account types**, choose **Accounts in any organizational directory and personal Microsoft accounts** (the `common` tenant — allows any work, school, or personal Microsoft account; access is still gated by email-matching in Signals).
3. Under **Redirect URI**, select **Web** and enter:
   ```
   https://<your-domain>/auth/microsoft/callback
   ```
4. After registering, go to **Certificates & secrets → New client secret**. Copy the value immediately — it is not shown again.
5. Copy the **Application (client) ID** from the app's Overview page.
6. In Signals, go to **Settings → Integrations → Single Sign-On → Microsoft**.
7. Toggle **Enable Microsoft login** on, paste in the Client ID and Client Secret, and save.

> **Note:** Client secrets are **write-only**. They are stored encrypted at rest, are never returned in API responses, and are **never loaded back into the settings form** after saving — the field renders blank with a "Configured — leave blank to keep" indicator. Saving with the secret field left blank keeps the existing secret unchanged. To rotate a secret, paste the new value and save — the previous secret is overwritten.

## Allowed Email Domains

The **Allowed email domains** field (under **Settings → Integrations → Single Sign-On**) restricts which email domains may sign in via SSO. Enter your owned domains one per line (commas and spaces also work, e.g. `example.com`); the list is normalised to lowercase and de-duplicated on save.

When the list is **non-empty**, only users whose IdP email domain is on the list may sign in or be auto-linked — and this gate applies on **every** SSO login, including users who already have a linked identity (so a domain you later remove can no longer sign in). When the list is **empty (the default), any email domain is permitted.**

> **Strongly recommended for Microsoft.** Signals registers Microsoft with the multitenant `common` setting, so any work, school, or personal Microsoft account can complete the OAuth flow. The email claim returned by Microsoft is **not a trustworthy identity across arbitrary Microsoft tenants** (the "nOAuth" class of cross-tenant account-takeover issues). Configuring an allow-list of **your owned domains** closes this gap by ensuring only emails on domains you control can match a Signals account. Leaving the allow-list empty allows any domain and is not recommended when Microsoft sign-in is enabled.

The allow-list is **policy, not a credential**, so it is configurable on both self-hosted and Signals Cloud installs.

## Enabling SSO — Signals Cloud

On **Signals Cloud**, OAuth credentials are managed centrally by Signals. The Integrations settings page shows **only enable/disable toggles** for each provider — there are no credential fields. Flip the toggle for the provider you want to make available and save.

## Email Matching and Auto-Link

SSO login matches the verified email returned by the provider to an existing, active Signals user. If a match is found, an identity link is created automatically on first sign-in — no further action is required. Subsequent logins use the stored identity link directly, so login still works even if the user's email address later changes in Signals (the link persists by provider identity).

Reasons login will be denied:

| Reason | Resolution |
|--------|-----------|
| No Signals user with that email | [Invite the user](/docs/getting-started/authentication) to create their account first |
| User account is inactive | Reactivate the account in **Admin → Users** |
| Provider did not return a verified email | Use a provider account with a verified email address |
| Email domain not on the allow-list | Add the domain in **Settings → Integrations → Single Sign-On → Allowed email domains**, or sign in with an account on a permitted domain |
| The provider is disabled or not configured | Enable it in **Settings → Integrations → Single Sign-On** |

## Enforcing SSO per Role

Admins can require that members of specific roles use SSO and cannot sign in with a password. This is configured in **Settings → Security → SSO Enforcement**.

Select one or more roles from the **Require SSO for these roles** multiselect. Members with any of the selected roles will be blocked from password login and shown the SSO buttons with a message explaining why their password was not accepted.

**The Owner role is always exempt** from enforcement — this is a break-glass guarantee that prevents a complete lockout if SSO becomes unavailable. The Owner role cannot be selected in the enforcement picker.

> **Tip:** If you want to test enforcement before rolling it out broadly, add a low-privilege role first and verify the flow with a test user.

## Two-Factor Authentication and SSO

SSO does not bypass 2FA. If a user has 2FA enabled on their Signals account, they are redirected to the standard one-time code challenge after a successful SSO callback and must enter their code before the login completes.

Users can manage 2FA from **Settings → Profile → Two-Factor Authentication** regardless of whether they use SSO or password login.

## Permissions

SSO configuration requires administrator access. No new permissions are introduced — the Integrations and Security settings pages are gated on the existing admin/owner access model.

## Settings reference

| Setting key | Type | UI location |
|-------------|------|-------------|
| `sso.google_enabled` | bool | Settings → Integrations → Single Sign-On |
| `sso.microsoft_enabled` | bool | Settings → Integrations → Single Sign-On |
| `sso.google_client_id` | encrypted string (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.google_client_secret` | encrypted, write-only (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.microsoft_client_id` | encrypted string (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.microsoft_client_secret` | encrypted, write-only (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.allowed_email_domains` | array (policy — self-hosted and Cloud) | Settings → Integrations → Single Sign-On |
| `security.sso_enforced_roles` | array | Settings → Security → SSO Enforcement |

On Signals Cloud, the credential keys (`*_client_id` / `*_client_secret`) are managed centrally via environment configuration and are not editable in the UI — only the enable toggles and the allow-list are shown.

## Routes

| Route | Description |
|-------|-------------|
| `GET /auth/google/redirect` | Redirects the user to Google's OAuth consent screen |
| `GET /auth/google/callback` | Handles Google's OAuth response and completes login |
| `GET /auth/microsoft/redirect` | Redirects the user to Microsoft's OAuth consent screen |
| `GET /auth/microsoft/callback` | Handles Microsoft's OAuth response and completes login |

Routes for unknown providers return a `404`.
