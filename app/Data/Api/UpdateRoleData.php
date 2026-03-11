<?php

namespace App\Data\Api;

use Spatie\LaravelData\Data;

class UpdateRoleData extends Data
{
    /**
     * @param  list<string>|null  $permissions
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?array $permissions = null,
    ) {}

    /**
     * Filter out null values for the UpdateRole action.
     *
     * @return array{name?: string, description?: string, permissions?: list<string>}
     */
    public function toActionData(): array
    {
        return array_filter(
            get_object_vars($this),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(int $roleId): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$roleId],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
