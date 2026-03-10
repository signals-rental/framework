<?php

namespace App\Data\Api;

class CreateRoleData
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly array $permissions = [],
    ) {}

    /**
     * @param  array{name: string, description?: string, permissions?: list<string>}  $data
     */
    public static function from(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            permissions: $data['permissions'] ?? [],
        );
    }

    /**
     * @return array{name: string, description: ?string, permissions: list<string>}
     */
    public function toActionData(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }
}
