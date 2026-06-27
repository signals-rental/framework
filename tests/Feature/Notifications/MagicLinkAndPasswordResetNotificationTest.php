<?php

use App\Notifications\MagicLinkLoginNotification;
use App\Notifications\PasswordResetNotification;
use App\Services\EmailTemplateRenderer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;

describe('MagicLinkLoginNotification template-render failure fallback', function () {
    it('logs and falls back to a plain mail message when the template render throws', function () {
        $user = (object) ['name' => 'Casey Magic', 'email' => 'casey@example.test'];

        // Force a non-ModelNotFound Throwable from the renderer so the generic
        // catch (lines 69, 74-78) logs and degrades to the plain MailMessage.
        $this->mock(EmailTemplateRenderer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('render')
                ->once()
                ->andThrow(new RuntimeException('Blade compile error'));
        });

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Magic-link template render failed')
                && array_key_exists('exception', $context));

        $notification = new MagicLinkLoginNotification('plain-secret-token');
        $mail = $notification->toMail($user);

        expect($mail)->toBeInstanceOf(MailMessage::class);

        // The fallback embeds the single-use sign-in link.
        $hasLink = collect($mail->actionUrl ? [$mail->actionUrl] : [])
            ->merge($mail->introLines)
            ->contains(fn ($line) => str_contains((string) $line, 'plain-secret-token'))
            || str_contains((string) $mail->actionUrl, 'plain-secret-token');

        expect($mail->actionText)->toBe('Sign in')
            ->and($hasLink)->toBeTrue();
    });
});

describe('PasswordResetNotification custom URL callback', function () {
    afterEach(function () {
        // Reset the static callback so it does not leak into other tests.
        ResetPassword::$createUrlCallback = null;
    });

    it('uses the registered createUrlUsing callback to build the reset url', function () {
        ResetPassword::createUrlUsing(fn ($notifiable, string $token): string => "https://custom.example/reset/{$token}");

        $notifiable = new class
        {
            public string $name = 'Reset User';

            public function getEmailForPasswordReset(): string
            {
                return 'reset@example.test';
            }
        };

        // No DB template seeded -> renderer throws ModelNotFound -> default
        // MailMessage built from the custom-callback URL (exercises line 64).
        $notification = new PasswordResetNotification('reset-token-xyz');
        $mail = $notification->toMail($notifiable);

        expect($mail)->toBeInstanceOf(MailMessage::class)
            ->and($mail->actionUrl)->toBe('https://custom.example/reset/reset-token-xyz');
    });
});
