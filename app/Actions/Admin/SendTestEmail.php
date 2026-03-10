<?php

namespace App\Actions\Admin;

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

class SendTestEmail
{
    /**
     * Send a test email to the given address.
     *
     * @throws \Exception
     */
    public function __invoke(string $recipient): void
    {
        Mail::to($recipient)->send(new TestEmail);
    }
}
