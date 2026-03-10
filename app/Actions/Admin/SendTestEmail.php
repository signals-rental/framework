<?php

namespace App\Actions\Admin;

use App\Mail\TestEmail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class SendTestEmail
{
    /**
     * Send a test email to the given address.
     *
     * @throws AuthorizationException
     * @throws \Exception
     */
    public function __invoke(string $recipient): void
    {
        Gate::authorize('settings.manage');

        Mail::to($recipient)->send(new TestEmail);
    }
}
