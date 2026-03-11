<?php

namespace App\Data\Api;

use App\Data\Admin\InviteUserData;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Unique;
use Spatie\LaravelData\Data;

class CreateUserData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required, Email, Unique('users', 'email')]
        public string $email,
        /** @var list<string> */
        public array $roles = [],
    ) {}

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
}
