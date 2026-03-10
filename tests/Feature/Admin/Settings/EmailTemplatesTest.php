<?php

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the email templates list page', function () {
    $this->get(route('admin.settings.email-templates'))
        ->assertOk()
        ->assertSee('Email Templates');
});

it('displays templates in the list', function () {
    EmailTemplate::factory()->create(['name' => 'Test Template']);

    $this->get(route('admin.settings.email-templates'))
        ->assertOk()
        ->assertSee('Test Template');
});

it('renders the edit form for a template', function () {
    $template = EmailTemplate::factory()->create();

    $this->get(route('admin.settings.email-templates.edit', $template))
        ->assertOk()
        ->assertSee('Edit Template');
});

it('loads template data into the edit form', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => 'Test Subject',
        'body_markdown' => 'Test body content',
    ]);

    Volt::test('admin.settings.email-template-form', ['template' => $template])
        ->assertSet('subject', 'Test Subject')
        ->assertSet('bodyMarkdown', 'Test body content');
});

it('saves template changes and creates a version', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => 'Original Subject',
        'body_markdown' => 'Original body',
    ]);

    $result = Volt::test('admin.settings.email-template-form', ['template' => $template])
        ->set('subject', 'Updated Subject')
        ->set('bodyMarkdown', 'Updated body content')
        ->call('save');

    $result->assertHasNoErrors()
        ->assertDispatched('email-template-saved');

    $template->refresh();
    expect($template->subject)->toBe('Updated Subject');
    expect($template->body_markdown)->toBe('Updated body content');

    expect(EmailTemplateVersion::where('email_template_id', $template->id)->count())->toBe(1);
    $version = EmailTemplateVersion::where('email_template_id', $template->id)->first();
    expect($version->subject)->toBe('Original Subject');
    expect($version->body_markdown)->toBe('Original body');
});

it('validates required fields', function () {
    $template = EmailTemplate::factory()->create();

    Volt::test('admin.settings.email-template-form', ['template' => $template])
        ->set('subject', '')
        ->call('save')
        ->assertHasErrors(['subject']);
});

it('can preview a template', function () {
    $template = EmailTemplate::factory()->create([
        'body_markdown' => 'Hello {{ user.name }}',
    ]);

    Volt::test('admin.settings.email-template-form', ['template' => $template])
        ->call('preview')
        ->assertSet('previewHtml', fn ($html) => str_contains($html, 'Jane Smith'));
});

it('can reset a system template to defaults', function () {
    (new EmailTemplateSeeder)->run();

    $template = EmailTemplate::where('key', 'test_email')->first();
    $template->update(['subject' => 'Modified Subject']);

    Volt::test('admin.settings.email-template-form', ['template' => $template])
        ->call('resetToDefault')
        ->assertDispatched('email-template-reset');

    $template->refresh();
    expect($template->subject)->toBe('Test email from {{ company.name }}');
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.email-templates'))
        ->assertForbidden();
});
