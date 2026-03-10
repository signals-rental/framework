<?php

namespace App\Actions\Admin;

use App\Events\AuditableEvent;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateUser
{
    /**
     * @param  array{name?: string, email?: string, roles?: list<string>}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        Gate::authorize('users.edit');

        $oldValues = $user->only(['name', 'email']);
        $oldValues['roles'] = $user->getRoleNames()->toArray();

        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($value) => $value !== null));

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        /** @var User $user */
        $user = $user->fresh();

        $newValues = $user->only(['name', 'email']);
        $newValues['roles'] = $user->getRoleNames()->toArray();

        if ($oldValues !== $newValues) {
            event(new AuditableEvent($user, 'updated', $oldValues, $newValues));
        }

        app(\App\Services\Api\WebhookService::class)->dispatch('user.updated', [
            'user' => \App\Data\Api\UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }
}
