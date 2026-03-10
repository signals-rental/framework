<?php

namespace App\Actions\Admin;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use Illuminate\Support\Facades\Gate;

class UpdateEmailTemplate
{
    /**
     * @param  array{subject: string, body_markdown: string}  $data
     */
    public function __invoke(EmailTemplate $template, array $data): EmailTemplate
    {
        Gate::authorize('email-templates.manage');

        // Create version snapshot before updating
        EmailTemplateVersion::create([
            'email_template_id' => $template->id,
            'subject' => $template->subject,
            'body_markdown' => $template->body_markdown,
            'version_number' => $template->latestVersionNumber() + 1,
            'created_by' => auth()->id(),
        ]);

        $template->update([
            'subject' => $data['subject'],
            'body_markdown' => $data['body_markdown'],
        ]);

        return $template->fresh();
    }
}
