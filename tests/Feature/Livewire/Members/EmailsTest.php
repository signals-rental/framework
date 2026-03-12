<?php

use App\Models\Email;
use App\Models\Member;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    $this->member = Member::factory()->create(['name' => 'Test Member']);
});

it('renders the emails page', function () {
    $this->get("/members/{$this->member->id}/emails")
        ->assertOk()
        ->assertSee('Emails');
});

it('lists emails for a member', function () {
    Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $this->member->id,
        'address' => 'john@example.com',
    ]);
    Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $this->member->id,
        'address' => 'jane@example.com',
    ]);

    Volt::test('members.emails', ['member' => $this->member])
        ->assertSee('john@example.com')
        ->assertSee('jane@example.com');
});

it('shows empty state when no emails exist', function () {
    Volt::test('members.emails', ['member' => $this->member])
        ->assertSee('No emails found.');
});

it('shows primary badge on primary email', function () {
    Email::factory()->primary()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $this->member->id,
        'address' => 'primary@example.com',
    ]);

    Volt::test('members.emails', ['member' => $this->member])
        ->assertSee('Primary');
});

it('can delete an email', function () {
    $email = Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $this->member->id,
    ]);

    Volt::test('members.emails', ['member' => $this->member])
        ->call('deleteEmail', $email->id);

    expect(Email::find($email->id))->toBeNull();
});

it('renders the create email form', function () {
    $this->get("/members/{$this->member->id}/emails/create")
        ->assertOk()
        ->assertSee('Add Email');
});

it('can create an email', function () {
    Volt::test('members.email-form', ['member' => $this->member])
        ->set('address', 'new@example.com')
        ->set('isPrimary', false)
        ->call('save')
        ->assertRedirect();

    expect($this->member->emails()->where('address', 'new@example.com')->exists())->toBeTrue();
});

it('can edit an email', function () {
    $email = Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $this->member->id,
        'address' => 'old@example.com',
    ]);

    Volt::test('members.email-form', ['member' => $this->member, 'email' => $email])
        ->assertSet('address', 'old@example.com')
        ->set('address', 'updated@example.com')
        ->call('save')
        ->assertRedirect();

    expect($email->fresh()->address)->toBe('updated@example.com');
});

it('validates required email address', function () {
    Volt::test('members.email-form', ['member' => $this->member])
        ->set('address', '')
        ->call('save')
        ->assertHasErrors(['address']);
});

it('validates email format', function () {
    Volt::test('members.email-form', ['member' => $this->member])
        ->set('address', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['address']);
});

it('can set primary email', function () {
    $existing = Email::factory()->primary()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $this->member->id,
    ]);

    Volt::test('members.email-form', ['member' => $this->member])
        ->set('address', 'newprimary@example.com')
        ->set('isPrimary', true)
        ->call('save')
        ->assertRedirect();

    expect($existing->fresh()->is_primary)->toBeFalse();
    expect($this->member->emails()->where('address', 'newprimary@example.com')->first()->is_primary)->toBeTrue();
});

it('cannot delete another member\'s email', function () {
    $otherMember = Member::factory()->create();
    $otherEmail = Email::factory()->create([
        'emailable_type' => Member::class,
        'emailable_id' => $otherMember->id,
    ]);

    expect(fn () => Volt::test('members.emails', ['member' => $this->member])
        ->call('deleteEmail', $otherEmail->id)
    )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect(Email::find($otherEmail->id))->not->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get("/members/{$this->member->id}/emails")
        ->assertRedirect();
});
