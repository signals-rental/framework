<?php

use App\Models\EmailTemplate;
use App\Services\EmailTemplateRenderer;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->renderer = new EmailTemplateRenderer;
});

it('renders a template by key', function () {
    EmailTemplate::factory()->create([
        'key' => 'test_render',
        'subject' => 'Hello {{ user.name }}',
        'body_markdown' => 'Welcome **{{ user.name }}** to {{ company.name }}.',
    ]);

    $result = $this->renderer->render('test_render', [
        'user' => ['name' => 'Alice'],
        'company' => ['name' => 'Acme Corp'],
    ]);

    expect($result['subject'])->toBe('Hello Alice');
    expect($result['html'])->toContain('<strong>Alice</strong>');
    expect($result['html'])->toContain('Acme Corp');
});

it('resolves nested merge fields', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => '{{ company.name }}',
        'body_markdown' => '{{ user.email }}',
    ]);

    $result = $this->renderer->renderTemplate($template, [
        'company' => ['name' => 'Test Co'],
        'user' => ['email' => 'test@example.com'],
    ]);

    expect($result['subject'])->toBe('Test Co');
    expect($result['html'])->toContain('test@example.com');
});

it('applies upper filter', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => '{{ user.name | upper }}',
        'body_markdown' => 'test',
    ]);

    $result = $this->renderer->renderTemplate($template, [
        'user' => ['name' => 'alice'],
    ]);

    expect($result['subject'])->toBe('ALICE');
});

it('applies lower filter', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => '{{ user.name | lower }}',
        'body_markdown' => 'test',
    ]);

    $result = $this->renderer->renderTemplate($template, [
        'user' => ['name' => 'ALICE'],
    ]);

    expect($result['subject'])->toBe('alice');
});

it('applies default filter for missing values', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => '{{ missing.field | default:"N/A" }}',
        'body_markdown' => 'test',
    ]);

    $result = $this->renderer->renderTemplate($template, []);

    expect($result['subject'])->toBe('N/A');
});

it('returns empty string for missing fields without default', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => 'Hello {{ missing.field }}!',
        'body_markdown' => 'test',
    ]);

    $result = $this->renderer->renderTemplate($template, []);

    expect($result['subject'])->toBe('Hello !');
});

it('throws when the merge-field regex fails (PCRE limit exceeded)', function () {
    // Build the template first so factory/model preg operations run at the normal limit.
    $template = EmailTemplate::factory()->create([
        'subject' => 'Hi {{ '.str_repeat('a', 5000).' }}',
        'body_markdown' => 'body',
    ]);

    $originalLimit = ini_get('pcre.backtrack_limit');

    // Lower the backtrack limit only around the render call so preg_replace_callback
    // returns null, triggering the RuntimeException guard.
    ini_set('pcre.backtrack_limit', '1');

    try {
        expect(fn () => $this->renderer->renderTemplate($template, []))
            ->toThrow(RuntimeException::class, 'Failed to process email template merge fields');
    } finally {
        ini_set('pcre.backtrack_limit', (string) $originalLimit);
    }
});

it('skips inactive templates', function () {
    EmailTemplate::factory()->inactive()->create(['key' => 'inactive_template']);

    expect(fn () => $this->renderer->render('inactive_template'))
        ->toThrow(ModelNotFoundException::class);
});
