<?php

use App\Mail\TestEmail;
use Illuminate\Mail\Mailable;

it('has the correct subject including app name', function () {
    config(['app.name' => 'Signals']);

    $mailable = new TestEmail;

    $mailable->assertHasSubject('Test Email from Signals');
});

it('uses the emails.test markdown view', function () {
    $mailable = new TestEmail;

    expect($mailable->content()->markdown)->toBe('emails.test');
});

it('is a valid mailable', function () {
    $mailable = new TestEmail;

    expect($mailable)->toBeInstanceOf(Mailable::class);
});
