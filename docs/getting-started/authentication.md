---
title: Authentication & Settings
description: Log in, reset your password, and manage your profile, password, and appearance settings.
---

## Overview

Signals Rental Framework uses Laravel's built-in authentication with Sanctum for API tokens. All authentication pages are accessible without logging in, while settings pages require an active session.

## Login

Visit `/login` to sign in with your email and password. Check **Remember me** to stay signed in across browser sessions.

![The Signals login page](/docs/images/login.png)

Login is rate-limited to 5 attempts per minute per email/IP combination. After exceeding this limit, you'll be asked to wait before trying again.

## Forgot Password

If you've forgotten your password, click **Forgot your password?** on the login page or visit `/forgot-password` directly.

![The forgot password page](/docs/images/forgot.png)

Enter your email address and Signals will send a password reset link. For security, the same confirmation message is shown regardless of whether the email exists in the system.

The reset link expires after 60 minutes. Click the link in the email to set a new password with a minimum of 8 characters.

## Email Verification

When email verification is enabled, new users and users who change their email address will be prompted to verify before continuing. A verification email is sent automatically, and can be resent from the verification page.

## Settings

Once logged in, access your account settings from the user dropdown in the top-right corner. Settings are organised into three tabs.

![The settings page showing profile, password, and appearance tabs](/docs/images/settings.png)

### Profile

Update your display name and email address. If you change your email, you'll need to re-verify it before the change takes full effect.

This page also includes the option to **delete your account** — this permanently removes your user and all associated data. You must confirm with your current password.

### Password

Change your password by entering your current password and choosing a new one (minimum 8 characters). The new password must be confirmed.

### Appearance

Switch between **Light**, **Dark**, and **System** themes. The System option follows your operating system's preference and updates automatically.

## Password Confirmation

Certain sensitive actions require you to re-enter your password, even while logged in. This confirmation is cached in your session and won't be asked again for a short period.

## Logout

Log out from the user dropdown menu in the header. This ends your session and redirects you to the login page.
