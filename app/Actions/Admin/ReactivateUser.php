<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ReactivateUser
{
    public function __invoke(User $user): User
    {
        Gate::authorize('users.activate');

        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        return $user->fresh();
    }
}
