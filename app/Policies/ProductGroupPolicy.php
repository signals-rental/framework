<?php

namespace App\Policies;

use App\Models\ProductGroup;
use App\Models\User;

class ProductGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, ProductGroup $productGroup): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.edit');
    }

    public function update(User $user, ProductGroup $productGroup): bool
    {
        return $user->can('products.edit');
    }

    public function delete(User $user, ProductGroup $productGroup): bool
    {
        return $user->can('products.edit');
    }
}
