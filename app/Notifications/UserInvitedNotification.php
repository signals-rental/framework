<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
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

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::signedRoute('invitation.accept', [
            'user' => $notifiable->id,
        ], now()->addDays(7));

        return (new MailMessage)
            ->subject('You\'ve been invited to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been invited to join '.config('app.name').'.')
            ->action('Accept Invitation', $url)
            ->line('This invitation link will expire in 7 days.');
    }
}
