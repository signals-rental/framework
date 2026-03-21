<?php

namespace App\Actions\Admin;

use App\Data\Admin\InviteUserData;
use App\Enums\MembershipType;
use App\Enums\RoleLevel;
use App\Models\Member;
use App\Models\User;
use App\Notifications\UserInvitedNotification;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InviteUser
{
    public function __invoke(InviteUserData $data): User
    {
        Gate::authorize('users.invite');

        $this->validateRoleHierarchy($data);

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

        app(WebhookService::class)->dispatch('user.created', [
            'user' => \App\Data\Api\UserData::fromModel($user)->toArray(),
        ]);

        return $user;
    }

    /**
     * Validate that the acting user's role level is high enough to assign the target roles.
     *
     * @throws ValidationException
     */
    private function validateRoleHierarchy(InviteUserData $data): void
    {
        if (empty($data->roles)) {
            return;
        }

        /** @var \App\Models\User $actingUser */
        $actingUser = auth()->user();
        $actingLevel = RoleLevel::forUser($actingUser);

        $tooHigh = [];
        foreach ($data->roles as $roleName) {
            if (RoleLevel::levelFor($roleName) >= $actingLevel) {
                $tooHigh[] = $roleName;
            }
        }

        if ($tooHigh !== []) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot assign roles at or above your own level: '.implode(', ', $tooHigh),
            ]);
        }
    }
}
