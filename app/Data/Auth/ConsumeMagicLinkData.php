<?php

namespace App\Data\Auth;

use App\Actions\Auth\ConsumeMagicLink;
use App\Actions\Auth\RequestMagicLink;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * Input for {@see ConsumeMagicLink}.
 *
 * Carries the plaintext sign-in secret from the consume URL. The action mints
 * 64-char secrets (see {@see RequestMagicLink::SECRET_LENGTH});
 * the length bounds here are deliberately lax so malformed inputs still flow
 * through the same generic "invalid or expired" error path rather than 422'ing
 * — that single message is what keeps the consume step from leaking signal.
 */
class ConsumeMagicLinkData extends Data
{
    public function __construct(
        #[Required, Min(1), Max(255)]
        public string $secret,
    ) {}
}
