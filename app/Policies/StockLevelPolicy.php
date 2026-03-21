<?php

namespace App\Policies;

use App\Models\StockLevel;
use App\Models\User;

class StockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, StockLevel $stockLevel): bool
    {
        return $user->can('stock.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stock.adjust');
    }

    public function update(User $user, StockLevel $stockLevel): bool
    {
        return $user->can('stock.adjust');
    }

    public function delete(User $user, StockLevel $stockLevel): bool
    {
        return $user->can('stock.adjust');
    }
}
