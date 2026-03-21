<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Api\WebhookService;
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

        // Revoke all API tokens
        $user->tokens()->delete();

        /** @var User $user */
        $user = $user->fresh();

        app(WebhookService::class)->dispatch('user.deactivated', [
            'user' => \App\Data\Api\UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }
}
