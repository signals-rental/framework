<?php

use App\Actions\Admin\SendTestEmail;
use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

it('sends a test email to the given address', function () {
    Mail::fake();

    (new SendTestEmail)('recipient@example.com');

    Mail::assertSent(TestEmail::class, function ($mail) {
        return $mail->hasTo('recipient@example.com');
    });
});

it('throws an exception if mail delivery fails', function () {
    Mail::shouldReceive('to->send')
        ->andThrow(new \Exception('Connection refused'));

    (new SendTestEmail)('test@example.com');
})->throws(\Exception::class, 'Connection refused');
