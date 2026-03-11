<?php

namespace App\Data\Api;

use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class UserData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $is_admin,
        public bool $is_owner,
        public bool $is_active,
        public ?string $email_verified_at,
        public ?string $invited_at,
        public ?string $invitation_accepted_at,
        public ?string $last_login_at,
        public ?string $deactivated_at,
        public string $created_at,
        public string $updated_at,
        public array $roles,
    ) {}

    public static function fromModel(User $user): self
    {
        /** @var Carbon|null $emailVerifiedAt */
        $emailVerifiedAt = $user->email_verified_at;

        /** @var Carbon|null $invitedAt */
        $invitedAt = $user->invited_at;

        /** @var Carbon|null $invitationAcceptedAt */
        $invitationAcceptedAt = $user->invitation_accepted_at;

        /** @var Carbon|null $lastLoginAt */
        $lastLoginAt = $user->last_login_at;

        /** @var Carbon|null $deactivatedAt */
        $deactivatedAt = $user->deactivated_at;

        /** @var Carbon $createdAt */
        $createdAt = $user->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $user->updated_at;

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            is_admin: (bool) $user->is_admin,
            is_owner: (bool) $user->is_owner,
            is_active: (bool) $user->is_active,
            email_verified_at: $emailVerifiedAt?->toIso8601String(),
            invited_at: $invitedAt?->toIso8601String(),
            invitation_accepted_at: $invitationAcceptedAt?->toIso8601String(),
            last_login_at: $lastLoginAt?->toIso8601String(),
            deactivated_at: $deactivatedAt?->toIso8601String(),
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
            roles: $user->getRoleNames()->toArray(),
        );
    }
}
