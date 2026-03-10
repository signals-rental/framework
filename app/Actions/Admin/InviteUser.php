<?php

namespace App\Actions\Admin;

use App\Data\Admin\InviteUserData;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\Gate;

class InviteUser
{
    public function __invoke(InviteUserData $data): User
    {
        Gate::authorize('users.invite');

        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => null,
            'email_verified_at' => now(),
            'invited_at' => now(),
            'is_active' => true,
        ]);

        if (! empty($data->roles)) {
            $user->syncRoles($data->roles);
        }

        $user->notify(new UserInvitedNotification);

        return $user;
    }
}
