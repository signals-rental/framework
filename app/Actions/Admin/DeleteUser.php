<?php

namespace App\Actions\Admin;

use App\Events\AuditableEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __invoke(User $user): void
    {
        Gate::authorize('users.delete');

        if ($user->isOwner()) {
            throw ValidationException::withMessages([
                'user' => 'The account owner cannot be deleted.',
            ]);
        }

        // Revoke all API tokens
        $user->tokens()->delete();

        event(new AuditableEvent($user, 'user.deleted'));

        app(\App\Services\Api\WebhookService::class)->dispatch('user.deleted', [
            'user' => \App\Data\Api\UserData::fromModel($user)->toArray(),
        ]);

        $user->delete();
    }
}
