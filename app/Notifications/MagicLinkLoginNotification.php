<?php

namespace App\Notifications;

use App\Mail\TemplatedEmail;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Emails a single-use magic-link login link (spec §7).
 *
 * Queued so the request response timing cannot leak account existence. Mirrors
 * the transactional-email convention used by {@see UserInvitedNotification} and
 * {@see PasswordResetNotification}: render the `magic_link` DB template through
 * the Signals layout, falling back to a built MailMessage if the template is
 * missing or inactive so the login flow never breaks.
 *
 * Implements {@see ShouldBeEncrypted} so the plaintext sign-in secret is never
 * persisted in clear text on the queue payload.
 */
class MagicLinkLoginNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $secret  The plaintext token embedded in the sign-in link.
     */
    public function __construct(
        private readonly string $secret,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the magic-link email.
     */
    public function toMail(object $notifiable): MailMessage|Mailable
    {
        $url = $this->buildLoginUrl();
        $companyName = settings('company.name', config('app.name'));

        try {
            $rendered = app(EmailTemplateRenderer::class)->render('magic_link', [
                'user' => [
                    'name' => $notifiable->name ?? '',
                    'email' => $notifiable->email ?? '',
                ],
                'company' => ['name' => $companyName],
                'magic_link' => ['url' => $url],
            ]);
        } catch (ModelNotFoundException) {
            // Template missing or inactive — expected, fall through silently.
            return $this->buildFallbackMessage($notifiable, $url, $companyName);
        } catch (Throwable $e) {
            // The class contract promises the login flow "never breaks" via the
            // fallback. A broken template (Blade syntax error, missing variable,
            // misconfigured renderer) would surface here — log and degrade so the
            // user still receives a working sign-in link.
            Log::error('Magic-link template render failed; falling back to plain mail message.', [
                'exception' => $e,
            ]);

            return $this->buildFallbackMessage($notifiable, $url, $companyName);
        }

        return new TemplatedEmail(
            subjectLine: $rendered['subject'],
            bodyHtml: $rendered['html'],
            eyebrow: 'Account Security',
            footerContext: 'You\'re receiving this because a sign-in link was requested for your account at '.$companyName.'.',
            preheader: 'Your sign-in link (expires in 15 minutes).',
        );
    }

    /**
     * Build the magic-link sign-in URL.
     */
    protected function buildLoginUrl(): string
    {
        return URL::route('magic-link.login', ['token' => $this->secret]);
    }

    /**
     * Fallback message used when the DB template is unavailable.
     */
    protected function buildFallbackMessage(object $notifiable, string $url, string $companyName): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in link for '.$companyName)
            ->greeting('Hello '.($notifiable->name ?? '').'!')
            ->line('Click the button below to sign in. This link expires in 15 minutes and can be used once.')
            ->action('Sign in', $url)
            ->line('If you didn\'t request this, you can safely ignore this email.');
    }
}
