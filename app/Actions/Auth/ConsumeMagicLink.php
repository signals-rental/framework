<?php

namespace App\Actions\Auth;

use App\Data\Auth\ConsumeMagicLinkData;
use App\Exceptions\Auth\InvalidMagicLinkException;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Services\Auth\MagicLinkService;

/**
 * Validates and consumes a magic-link login token (spec §8, §10).
 *
 * Every failure path throws the same {@see InvalidMagicLinkException} so the
 * controller surfaces one generic message and never leaks which check failed.
 * Eligibility (feature on, active user, not SSO-enforced) is re-checked here so
 * a minted link cannot outlive a policy change. Consumption is atomic: the token
 * is claimed with a single conditional UPDATE (`WHERE consumed_at IS NULL AND
 * expires_at > now`), so two concurrent clicks can never both succeed — exactly
 * one wins the claim and the other is treated as invalid. On success the resolved
 * user is returned for the controller to log in (mirroring how {@see SsoController}
 * uses {@see ResolveSsoUser}).
 */
class ConsumeMagicLink
{
    public function __construct(
        private readonly MagicLinkService $magicLink,
    ) {}

    /**
     * Validate the plaintext secret and return the user it authenticates.
     *
     * @throws InvalidMagicLinkException When the token is unknown, expired,
     *                                   already consumed, no longer eligible, or
     *                                   lost the atomic claim to a concurrent click.
     */
    public function __invoke(ConsumeMagicLinkData $data): User
    {
        $secret = $data->secret;

        $token = MagicLinkToken::query()
            ->where('token_hash', hash('sha256', $secret))
            ->with('user')
            ->first();

        if ($token === null || ! $token->isUsable()) {
            throw new InvalidMagicLinkException;
        }

        $user = $token->user;

        if ($user === null || ! $this->magicLink->isEligible($user)) {
            throw new InvalidMagicLinkException;
        }

        // Atomically claim the token: only one caller can flip an unconsumed,
        // unexpired row to consumed. A zero-row result means a concurrent click
        // (or expiry between the read and the claim) already took it — invalid.
        $claimed = MagicLinkToken::query()
            ->whereKey($token->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($claimed === 0) {
            throw new InvalidMagicLinkException;
        }

        return $user;
    }
}
