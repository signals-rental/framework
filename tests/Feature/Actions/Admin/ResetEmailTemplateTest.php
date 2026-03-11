<?php

use App\Actions\Admin\ResetEmailTemplate;
use App\Events\AuditableEvent;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('resets a system template to defaults', function () {
    $defaults = EmailTemplateSeeder::defaults();
    $firstKey = array_key_first($defaults);

    $template = EmailTemplate::factory()->system()->create([
        'key' => $firstKey,
        'subject' => 'Customized Subject',
        'body_markdown' => 'Customized body',
    ]);

    $result = (new ResetEmailTemplate)($template);

    expect($result->subject)->toBe($defaults[$firstKey]['subject']);
    expect($result->body_markdown)->toBe($defaults[$firstKey]['body_markdown']);
});

it('throws when template is not a system template', function () {
    $template = EmailTemplate::factory()->create(['is_system' => false]);

    (new ResetEmailTemplate)($template);
})->throws(ValidationException::class);

it('throws when no default content found for key', function () {
    $template = EmailTemplate::factory()->system()->create([
        'key' => 'nonexistent_template_key',
    ]);

    (new ResetEmailTemplate)($template);
})->throws(ValidationException::class);

it('fires an AuditableEvent', function () {
    Event::fake([AuditableEvent::class]);

    $defaults = EmailTemplateSeeder::defaults();
    $firstKey = array_key_first($defaults);

    $template = EmailTemplate::factory()->system()->create([
        'key' => $firstKey,
        'subject' => 'Old Subject',
    ]);

    (new ResetEmailTemplate)($template);

    Event::assertDispatched(AuditableEvent::class, function ($event) {
        return $event->action === 'reset';
    });
});
