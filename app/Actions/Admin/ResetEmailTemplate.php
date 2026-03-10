<?php

namespace App\Actions\Admin;

use App\Events\AuditableEvent;
use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResetEmailTemplate
{
    public function __invoke(EmailTemplate $template): EmailTemplate
    {
        Gate::authorize('email-templates.manage');

        if (! $template->is_system) {
            throw ValidationException::withMessages([
                'template' => 'Only system templates can be reset.',
            ]);
        }

        $defaults = EmailTemplateSeeder::defaults();
        $default = $defaults[$template->key] ?? null;

        if (! $default) {
            throw ValidationException::withMessages([
                'template' => 'No default content found for this template.',
            ]);
        }

        $oldValues = $template->only(['subject', 'body_markdown']);

        $template->update([
            'subject' => $default['subject'],
            'body_markdown' => $default['body_markdown'],
        ]);

        /** @var EmailTemplate $template */
        $template = $template->fresh();

        event(new AuditableEvent(
            $template, 'reset',
            $oldValues,
            $template->only(['subject', 'body_markdown']),
        ));

        return $template;
    }
}
