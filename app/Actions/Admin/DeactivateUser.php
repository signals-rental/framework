<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeactivateUser
{
    public function __invoke(User $user): User
    {
        Gate::authorize('users.deactivate');

        if ($user->isOwner()) {
            throw ValidationException::withMessages([
                'user' => 'The account owner cannot be deactivated.',
            ]);
        }

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        // Revoke all API tokens if Sanctum is configured
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        return $user->fresh();
    }
}
