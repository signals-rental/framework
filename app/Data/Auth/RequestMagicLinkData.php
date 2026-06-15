<?php

namespace App\Data\Auth;

use App\Actions\Auth\RequestMagicLink;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Input for {@see RequestMagicLink}.
 *
 * Carries the address entered into the "email me a sign-in link" affordance. The
 * action itself is anti-enumerative: it never reveals whether the email matches
 * a user, so the only validation here is that the value is a well-formed email.
 */
class RequestMagicLinkData extends Data
{
    public function __construct(
        #[Required, Email, Max(255)]
        public string $email,
    ) {}
}
