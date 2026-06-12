<?php

use App\Actions\Admin\SendTestTemplateEmail;
use App\Mail\TemplatedEmail;
use App\Models\EmailTemplate;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('sends the chosen template with a test subject prefix', function () {
    Mail::fake();

    $template = EmailTemplate::factory()->create([
        'subject' => 'Hello {{ user.name }}',
        'body_markdown' => 'Welcome **{{ user.name }}**',
    ]);

    (new SendTestTemplateEmail)($template, 'recipient@example.com');

    Mail::assertSent(TemplatedEmail::class, function ($mail) {
        return $mail->hasTo('recipient@example.com')
            && str_starts_with($mail->subjectLine, '[Test] ')
            && str_contains($mail->subjectLine, 'Jane Smith')
            && str_contains($mail->bodyHtml, 'Jane Smith');
    });
});

it('renders inactive templates too (admin send is not gated on is_active)', function () {
    Mail::fake();

    $template = EmailTemplate::factory()->inactive()->create([
        'subject' => 'Inactive {{ company.name }}',
        'body_markdown' => 'Body',
    ]);

    (new SendTestTemplateEmail)($template, 'recipient@example.com');

    Mail::assertSent(TemplatedEmail::class);
});

it('rejects unauthorized users', function () {
    $this->actingAs(User::factory()->create());

    Mail::fake();

    $template = EmailTemplate::factory()->create();

    (new SendTestTemplateEmail)($template, 'recipient@example.com');
})->throws(AuthorizationException::class);

it('validates email and template in the page component', function () {
    Mail::fake();

    Volt::test('admin.settings.email-templates')
        ->set('testTemplateId', null)
        ->set('testRecipient', 'not-an-email')
        ->call('sendTest')
        ->assertHasErrors(['testTemplateId', 'testRecipient']);

    Mail::assertNothingSent();
});

it('rejects a non-existent template in the page component', function () {
    Mail::fake();

    Volt::test('admin.settings.email-templates')
        ->set('testTemplateId', 99999)
        ->set('testRecipient', 'valid@example.com')
        ->call('sendTest')
        ->assertHasErrors(['testTemplateId']);

    Mail::assertNothingSent();
});

it('sends from the page component on valid input', function () {
    Mail::fake();

    $template = EmailTemplate::factory()->create([
        'subject' => 'Hello {{ user.name }}',
        'body_markdown' => 'Body content',
    ]);

    Volt::test('admin.settings.email-templates')
        ->set('testTemplateId', $template->id)
        ->set('testRecipient', 'valid@example.com')
        ->call('sendTest')
        ->assertHasNoErrors()
        ->assertDispatched('test-template-sent')
        ->assertSet('showTestModal', false);

    Mail::assertSent(TemplatedEmail::class, fn ($mail) => $mail->hasTo('valid@example.com'));
});
