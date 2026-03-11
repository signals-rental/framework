<?php

namespace App\Data\Admin;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class InviteUserData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required]
        public string $email,
        public array $roles = [],
    ) {}
}
