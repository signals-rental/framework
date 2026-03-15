<?php

namespace App\Policies;

use App\Models\OrganisationTaxClass;
use App\Models\User;

class OrganisationTaxClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-classes.view');
    }

    public function view(User $user, OrganisationTaxClass $organisationTaxClass): bool
    {
        return $user->can('tax-classes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tax-classes.manage');
    }

    public function update(User $user, OrganisationTaxClass $organisationTaxClass): bool
    {
        return $user->can('tax-classes.manage');
    }

    public function delete(User $user, OrganisationTaxClass $organisationTaxClass): bool
    {
        return $user->can('tax-classes.manage');
    }
}
