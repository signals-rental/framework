<?php

namespace App\Services\Auth;

use App\Models\User;

/**
 * Determines whether a user must authenticate via SSO (spec §4.3, decision D2).
 *
 * Enforcement is per-role: `security.sso_enforced_roles` lists the Spatie role
 * names whose members must use Single Sign-On rather than a password. The Owner
 * is always exempt — a deliberate break-glass guarantee so enforcement can never
 * lock the account out (spec §10).
 */
class SsoEnforcement
{
    /**
     * Determine whether SSO is enforced for the given user.
     *
     * Returns true only when the user holds at least one enforced role and is not
     * the Owner. An empty or unset enforced-role list disables enforcement entirely.
     */
    public function isEnforcedFor(User $user): bool
    {
        if ($user->isOwner()) {
            return false;
        }

        $enforcedRoles = $this->enforcedRoles();

        if ($enforcedRoles === []) {
            return false;
        }

        return $user->hasAnyRole($enforcedRoles);
    }

    /**
     * The configured list of role names that must use SSO.
     *
     * @return list<string>
     */
    private function enforcedRoles(): array
    {
        $roles = settings('security.sso_enforced_roles');

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter(
            $roles,
            fn (mixed $role): bool => is_string($role) && $role !== '',
        ));
    }
}
