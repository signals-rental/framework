<?php

namespace App\Notifications;

use App\Mail\TemplatedEmail;
use App\Services\EmailTemplateRenderer;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class PasswordResetNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * Build the mail representation of the notification.
     *
     * Renders the `password_reset` DB template through the Signals layout. If the
     * template is missing or inactive, falls back to Laravel's default content so
     * the reset flow never breaks.
     *
     * @param  mixed  $notifiable
     * @return MailMessage|Mailable
     */
    public function toMail($notifiable)
    {
        $url = $this->buildResetUrl($notifiable);

        try {
            $rendered = app(EmailTemplateRenderer::class)->render('password_reset', [
                'user' => [
                    'name' => $notifiable->name ?? '',
                    'email' => $notifiable->getEmailForPasswordReset(),
                ],
                'company' => ['name' => settings('company.name', config('app.name'))],
                'reset' => ['url' => $url],
            ]);
        } catch (ModelNotFoundException) {
            // Template missing or inactive: fall back to Laravel default content.
            return $this->buildMailMessage($url);
        }

        return new TemplatedEmail(
            subjectLine: $rendered['subject'],
            bodyHtml: $rendered['html'],
            eyebrow: 'Account Security',
            footerContext: 'You\'re receiving this because a password reset was requested for your account at '.settings('company.name', config('app.name')).'.',
            preheader: 'Reset your password.',
        );
    }

    /**
     * Build the reset URL exactly as Laravel's default ResetPassword notification does.
     *
     * @param  mixed  $notifiable
     */
    protected function buildResetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return URL::route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
    }
}
