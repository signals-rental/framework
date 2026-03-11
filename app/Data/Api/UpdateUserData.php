<?php

namespace App\Data\Api;

use Spatie\LaravelData\Data;

class UpdateUserData extends Data
{
    /**
     * @param  list<string>|null  $roles
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?array $roles = null,
    ) {}

    /**
     * Filter out null values for the UpdateUser action.
     *
     * @return array{name?: string, email?: string, roles?: list<string>}
     */
    public function toActionData(): array
    {
        return array_filter(
            get_object_vars($this),
            fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(int $userId): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$userId],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
