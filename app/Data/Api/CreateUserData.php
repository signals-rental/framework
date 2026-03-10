<?php

namespace App\Data\Api;

use App\Data\Admin\InviteUserData;

class CreateUserData
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly array $roles = [],
    ) {}

    /**
     * @param  array{name: string, email: string, roles?: list<string>}  $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            roles: $data['roles'] ?? [],
        );
    }

    /**
     * Convert to the shared InviteUserData DTO used by the InviteUser action.
     */
    public function toInviteUserData(): InviteUserData
    {
        return new InviteUserData(
            name: $this->name,
            email: $this->email,
            roles: $this->roles,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
