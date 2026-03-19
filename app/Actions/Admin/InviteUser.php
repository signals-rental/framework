<?php

namespace App\Actions\Admin;

use App\Data\Admin\InviteUserData;
use App\Enums\MembershipType;
use App\Models\Member;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InviteUser
{
    public function __invoke(InviteUserData $data): User
    {
        Gate::authorize('users.invite');

        $user = DB::transaction(function () use ($data): User {
            $member = Member::create([
                'name' => $data->name,
                'membership_type' => MembershipType::User,
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => null,
                'email_verified_at' => now(),
                'invited_at' => now(),
                'is_active' => true,
                'member_id' => $member->id,
            ]);

            if (! empty($data->roles)) {
                $user->syncRoles($data->roles);
            }

            return $user;
        });

        $user->notify(new UserInvitedNotification);

        app(\App\Services\Api\WebhookService::class)->dispatch('user.created', [
            'user' => \App\Data\Api\UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }
}
