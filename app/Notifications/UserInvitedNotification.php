<?php

namespace App\Notifications;

use App\Mail\TemplatedEmail;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class UserInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the invitation.
     *
     * Renders the `user_invited` DB template through the Signals layout. If the
     * template is missing or inactive, falls back to a built MailMessage so the
     * invitation flow never breaks.
     */
    public function toMail(object $notifiable): MailMessage|Mailable
    {
        $url = $this->buildInvitationUrl($notifiable);
        $companyName = settings('company.name', config('app.name'));

        try {
            $rendered = app(EmailTemplateRenderer::class)->render('user_invited', [
                'user' => [
                    'name' => $notifiable->name ?? '',
                    'email' => $notifiable->email ?? '',
                ],
                'company' => ['name' => $companyName],
                'invitation' => ['url' => $url],
            ]);
        } catch (ModelNotFoundException) {
            return $this->buildFallbackMessage($notifiable, $url, $companyName);
        }

        return new TemplatedEmail(
            subjectLine: $rendered['subject'],
            bodyHtml: $rendered['html'],
            eyebrow: 'Invitation',
            footerContext: 'You\'re receiving this because you have been invited to '.$companyName.'.',
            preheader: 'You have been invited to '.$companyName.'.',
        );
    }

    /**
     * Build the signed invitation acceptance URL.
     */
    protected function buildInvitationUrl(object $notifiable): string
    {
        return URL::signedRoute('invitation.accept', [
            'user' => $notifiable->id,
        ], now()->addDays(7));
    }

    /**
     * Fallback message used when the template is unavailable.
     */
    protected function buildFallbackMessage(object $notifiable, string $url, string $companyName): MailMessage
    {
        return (new MailMessage)
            ->subject('You\'ve been invited to '.$companyName)
            ->greeting('Hello '.($notifiable->name ?? '').'!')
            ->line('You have been invited to join '.$companyName.'.')
            ->action('Accept Invitation', $url)
            ->line('This invitation link will expire in 7 days.');
    }
}
