<?php

namespace App\Services\Auth;

use App\Actions\Auth\ConsumeMagicLink;
use App\Actions\Auth\RequestMagicLink;
use App\Models\User;

/**
 * The single source of truth for who may use email magic-link login, and when.
 *
 * This is the shared magic-link eligibility policy (spec §4, §7, §8): both
 * {@see RequestMagicLink} (request time) and {@see ConsumeMagicLink} (consume
 * time) gate on the exact same rule, so it lives here in one place and is
 * re-checked at consume time so a minted link can never outlive a policy change.
 * Resolved from the container so it is mockable in tests.
 */
class MagicLinkService
{
    public function __construct(
        private readonly SsoEnforcement $ssoEnforcement,
    ) {}

    /**
     * Determine whether the given user may use magic-link login right now.
     *
     * Eligible when ALL hold:
     *   - the user account is active;
     *   - the `security.magic_link_enabled` feature toggle is on;
     *   - the user is NOT SSO-enforced (the Owner is already exempt inside
     *     {@see SsoEnforcement}, so magic-link stays available as a break-glass
     *     path for the Owner).
     *
     * Re-checked at consume time so a link can never outlive a policy change
     * (feature disabled or SSO enforced after the link was minted).
     */
    public function isEligible(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if (! settings('security.magic_link_enabled')) {
            return false;
        }

        return ! $this->ssoEnforcement->isEnforcedFor($user);
    }
}
