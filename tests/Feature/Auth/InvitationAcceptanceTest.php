<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('renders the invitation acceptance page with valid signed URL', function () {
    $user = User::factory()->invited()->create();

    $url = URL::signedRoute('invitation.accept', ['user' => $user->id]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Welcome');
});

it('rejects invalid signatures', function () {
    $user = User::factory()->invited()->create();

    $this->get(route('invitation.accept', ['user' => $user->id]))
        ->assertForbidden();
});

it('redirects already-accepted invitations', function () {
    $user = User::factory()->create([
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $url = URL::signedRoute('invitation.accept', ['user' => $user->id]);

    $this->get($url)
        ->assertRedirect(route('login'));
});

it('allows setting password and completing invitation', function () {
    $user = User::factory()->invited()->create();

    $url = URL::signedRoute('invitation.accept', ['user' => $user->id]);

    \Livewire\Volt\Volt::test('auth.accept-invitation', ['user' => $user])
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->invitation_accepted_at)->not->toBeNull();
    expect($user->password)->not->toBeNull();
});
