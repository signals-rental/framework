<?php

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\User;

it('has the correct fillable attributes', function () {
    $version = new EmailTemplateVersion;

    expect($version->getFillable())->toBe([
        'email_template_id',
        'subject',
        'body_markdown',
        'version_number',
        'created_by',
    ]);
});

it('does not use updated_at timestamp', function () {
    expect(EmailTemplateVersion::UPDATED_AT)->toBeNull();
});

it('belongs to a template', function () {
    $version = EmailTemplateVersion::factory()->create();

    expect($version->template)->toBeInstanceOf(EmailTemplate::class);
    expect($version->template->id)->toBe($version->email_template_id);
});

it('belongs to a creator', function () {
    $user = User::factory()->create();
    $version = EmailTemplateVersion::factory()->create(['created_by' => $user->id]);

    expect($version->creator)->toBeInstanceOf(User::class);
    expect($version->creator->id)->toBe($user->id);
});

it('stores version data correctly', function () {
    $template = EmailTemplate::factory()->create();
    $user = User::factory()->create();

    $version = EmailTemplateVersion::factory()->create([
        'email_template_id' => $template->id,
        'subject' => 'Test Subject',
        'body_markdown' => '# Hello World',
        'version_number' => 3,
        'created_by' => $user->id,
    ]);

    $version->refresh();
    expect($version->subject)->toBe('Test Subject');
    expect($version->body_markdown)->toBe('# Hello World');
    expect($version->version_number)->toBe(3);
});
