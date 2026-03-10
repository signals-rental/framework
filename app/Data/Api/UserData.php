<?php

namespace App\Data\Api;

use App\Models\User;
use Illuminate\Support\Carbon;

class UserData
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $is_admin,
        public readonly bool $is_owner,
        public readonly bool $is_active,
        public readonly ?string $email_verified_at,
        public readonly ?string $invited_at,
        public readonly ?string $invitation_accepted_at,
        public readonly ?string $last_login_at,
        public readonly ?string $deactivated_at,
        public readonly string $created_at,
        public readonly string $updated_at,
        public readonly array $roles,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'is_owner' => $this->is_owner,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'invited_at' => $this->invited_at,
            'invitation_accepted_at' => $this->invitation_accepted_at,
            'last_login_at' => $this->last_login_at,
            'deactivated_at' => $this->deactivated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->roles,
        ];
    }
}
