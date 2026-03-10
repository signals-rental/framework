<?php

namespace App\Actions\Admin;

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

        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($value) => $value !== null));

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->fresh();
    }
}
