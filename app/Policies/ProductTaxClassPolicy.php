<?php

namespace App\Policies;

use App\Models\ProductTaxClass;
use App\Models\User;

class ProductTaxClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tax-classes.view');
    }

    public function view(User $user, ProductTaxClass $productTaxClass): bool
    {
        return $user->can('tax-classes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tax-classes.manage');
    }

    public function update(User $user, ProductTaxClass $productTaxClass): bool
    {
        return $user->can('tax-classes.manage');
    }

    public function delete(User $user, ProductTaxClass $productTaxClass): bool
    {
        return $user->can('tax-classes.manage');
    }
}
