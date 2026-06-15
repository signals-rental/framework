<?php

declare(strict_types=1);

use App\Actions\Auth\ConsumeMagicLink;
use App\Actions\Auth\RequestMagicLink;
use App\Data\Auth\RequestMagicLinkData;
use App\Models\MagicLinkToken;
use App\Models\User;
use App\Notifications\MagicLinkLoginNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

if (! function_exists('mintMagicLinkToken')) {
    /**
     * Mint a magic-link token directly via the factory.
     *
     * Returns the plaintext secret and the persisted token row so unit tests can
     * exercise {@see ConsumeMagicLink} without running the request
     * action first. The secret is a fresh 64-char string and is hashed before
     * storage, matching the production mint shape exactly.
     *
     * @return array{0: string, 1: MagicLinkToken}
     */
    function mintMagicLinkToken(User $user, ?CarbonInterface $expiresAt = null): array
    {
        $secret = Str::random(64);

        $token = MagicLinkToken::factory()->for($user)->create([
            'token_hash' => hash('sha256', $secret),
            'expires_at' => $expiresAt ?? now()->addMinutes(15),
        ]);

        return [$secret, $token];
    }
}

if (! function_exists('mintMagicLinkSecret')) {
    /**
     * Mint a magic-link secret by running the real request action.
     *
     * Used by feature tests that need to hit the consume route with a real,
     * unconsumed token. Fakes the notification channel and reflects the secret
     * out of the queued {@see MagicLinkLoginNotification} so the consume URL uses
     * the exact plaintext whose sha256 hash is stored — the same way production
     * mints. Resets the per-email throttle bucket on entry so a test can mint
     * for the same user repeatedly within one process.
     */
    function mintMagicLinkSecret(User $user): string
    {
        Notification::fake();

        RateLimiter::clear('magic-link.request|'.strtolower($user->email).'|127.0.0.1');

        app(RequestMagicLink::class)(new RequestMagicLinkData(email: $user->email));

        $secret = null;

        Notification::assertSentTo($user, MagicLinkLoginNotification::class, function ($notification) use (&$secret): bool {
            $secret = (new ReflectionClass($notification))->getProperty('secret')->getValue($notification);

            return true;
        });

        return (string) $secret;
    }
}
