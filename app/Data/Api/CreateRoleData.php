<?php

namespace App\Data\Api;

use Spatie\LaravelData\Data;

class CreateRoleData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $permissions = [],
    ) {}

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
