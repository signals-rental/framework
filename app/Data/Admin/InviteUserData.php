<?php

namespace App\Data\Admin;

class InviteUserData
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
}
