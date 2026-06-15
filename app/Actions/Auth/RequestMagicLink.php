<?php

namespace App\Actions\Auth;

use App\Data\Auth\RequestMagicLinkData;
use App\Models\User;
use App\Notifications\MagicLinkLoginNotification;
use App\Services\Auth\MagicLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Mints and emails a single-use magic-link login token (spec §7).
 *
 * Anti-enumeration is the central constraint: this action ALWAYS returns void
 * and never signals whether the email matched a user. For an eligible, active
 * user it invalidates any outstanding links, mints a fresh 15-minute token
 * (only the hash is stored) and queues the login email. For unknown, inactive,
 * feature-off or SSO-enforced cases it silently does nothing. The mail is queued
 * so response timing cannot leak existence either.
 *
 * Two throttles apply, both returning silently when tripped (so neither leaks
 * account existence): a per-email+IP cap, and a broader per-IP cap so a single
 * IP cannot fan requests out across many different addresses. The invalidate +
 * mint pair runs in a transaction so a mid-operation crash can never leave the
 * user with all links revoked and no valid replacement.
 */
class RequestMagicLink
{
    /**
     * Number of magic-link requests permitted per email+IP within the window.
     *
     * Deliberately NOT bound to `security.max_login_attempts` /
     * `security.lockout_duration`: those govern password lockout (visible
     * "too many attempts" error); magic-link throttling has anti-enumeration
     * semantics — it silently no-ops when tripped so the response never
     * differs from the eligible-user path.
     */
    private const MAX_ATTEMPTS = 3;

    /**
     * Number of magic-link requests permitted per IP (across all addresses)
     * within the window. Caps an IP from fanning sends out over many emails.
     *
     * Same rationale as {@see self::MAX_ATTEMPTS}: silent-fail semantics, not
     * tied to the visible login-lockout settings.
     */
    private const MAX_IP_ATTEMPTS = 10;

    /**
     * Throttle window in seconds (15 minutes).
     *
     * Same rationale as {@see self::MAX_ATTEMPTS}: silent-fail semantics, not
     * tied to the visible login-lockout settings.
     */
    private const DECAY_SECONDS = 900;

    /**
     * Token lifetime in minutes.
     */
    private const EXPIRY_MINUTES = 15;

    /**
     * Length of the random plaintext secret embedded in the link.
     */
    private const SECRET_LENGTH = 64;

    public function __construct(
        private readonly MagicLinkService $magicLink,
    ) {}

    /**
     * Request a magic-link login email for the given address.
     *
     * Never reveals whether the address belongs to an account: returns void in
     * all cases, including when throttled.
     */
    public function __invoke(RequestMagicLinkData $data): void
    {
        $email = $data->email;
        $emailKey = $this->throttleKey($email);
        $ipKey = $this->ipThrottleKey();

        if (
            RateLimiter::tooManyAttempts($emailKey, self::MAX_ATTEMPTS)
            || RateLimiter::tooManyAttempts($ipKey, self::MAX_IP_ATTEMPTS)
        ) {
            return;
        }

        RateLimiter::hit($emailKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);

        // Case-insensitive lookup: a user stored as `Foo@example.com` must still
        // resolve when the request comes in as `foo@example.com`. Without this,
        // such users hit the silent neutral path and receive no link at all.
        $user = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->first();

        if ($user === null || ! $this->magicLink->isEligible($user)) {
            return;
        }

        $secret = Str::random(self::SECRET_LENGTH);

        DB::transaction(function () use ($user, $secret): void {
            $this->invalidateOutstandingTokens($user);

            $user->magicLinkTokens()->create([
                'token_hash' => hash('sha256', $secret),
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            ]);
        });

        $user->notify(new MagicLinkLoginNotification($secret));
    }

    /**
     * Invalidate the user's prior unconsumed magic-link tokens.
     *
     * A new request supersedes outstanding links so only the latest one works.
     */
    private function invalidateOutstandingTokens(User $user): void
    {
        $user->magicLinkTokens()
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /**
     * Build the per-email, per-IP throttle key.
     */
    private function throttleKey(string $email): string
    {
        return 'magic-link.request|'.Str::lower($email).'|'.request()->ip();
    }

    /**
     * Build the per-IP throttle key (across all addresses).
     */
    private function ipThrottleKey(): string
    {
        return 'magic-link.request.ip|'.request()->ip();
    }
}
