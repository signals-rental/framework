<?php

namespace App\Actions\Admin;

use App\Mail\TemplatedEmail;
use App\Services\EmailTemplateRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTestEmail
{
    /**
     * Send a test email to the given address using the branded layout.
     *
     * @throws AuthorizationException
     * @throws \Exception
     */
    public function __invoke(string $recipient): void
    {
        Gate::authorize('settings.manage');

        $companyName = settings('company.name', config('app.name'));

        $data = [
            'company' => ['name' => $companyName],
        ];

        try {
            $rendered = app(EmailTemplateRenderer::class)->render('test_email', $data);
            $subject = $rendered['subject'];
            $bodyHtml = $rendered['html'];
        } catch (ModelNotFoundException) {
            $subject = 'Test email from '.$companyName;
            $bodyHtml = Str::markdown(
                "Hello,\n\nThis is a test email from **{$companyName}** to verify your email configuration is working correctly.\n\nIf you received this email, your email settings are configured properly."
            );
        }

        Mail::to($recipient)->send(new TemplatedEmail(
            subjectLine: $subject,
            bodyHtml: $bodyHtml,
            eyebrow: 'Email Test',
            footerContext: 'This is an automated test message from '.$companyName.'.',
            preheader: 'Your email configuration is working.',
        ));
    }
}
