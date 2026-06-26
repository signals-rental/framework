<?php

use App\Mail\TemplatedEmail;

it('builds the envelope subject from the constructor argument', function () {
    $mailable = new TemplatedEmail(
        subjectLine: 'Your quote is ready',
        bodyHtml: '<p>Hello there</p>',
    );

    expect($mailable->envelope()->subject)->toBe('Your quote is ready');
});

it('uses the signals layout view and passes optional content fields', function () {
    $mailable = new TemplatedEmail(
        subjectLine: 'Invoice issued',
        bodyHtml: '<p>Please find your invoice attached.</p>',
        eyebrow: 'Billing',
        footerContext: 'Signals Rental Ltd',
        preheader: 'Invoice ready to view',
    );

    $content = $mailable->content();

    expect($content->view)->toBe('emails.layouts.signals')
        ->and($content->with)->toMatchArray([
            'bodyHtml' => '<p>Please find your invoice attached.</p>',
            'eyebrow' => 'Billing',
            'footerContext' => 'Signals Rental Ltd',
            'preheader' => 'Invoice ready to view',
        ]);
});

it('renders the body html through the signals email layout', function () {
    $mailable = new TemplatedEmail(
        subjectLine: 'Test',
        bodyHtml: '<strong>Rendered body</strong>',
        eyebrow: 'System',
    );

    $html = $mailable->render();

    expect($html)->toContain('<strong>Rendered body</strong>')
        ->and($html)->toContain('System');
});
