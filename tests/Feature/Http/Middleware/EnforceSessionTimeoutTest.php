<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'signals.session-timeout'])->get('/timeout-test', function () {
        return response()->json(['ok' => true]);
    });

    $this->user = User::factory()->create(['is_active' => true]);
});

it('sets last_activity on first request', function () {
    $this->actingAs($this->user)
        ->get('/timeout-test')
        ->assertOk();

    expect(session('last_activity'))->not->toBeNull();
});

it('allows requests within timeout window', function () {
    settings()->set('security.session_timeout', 120);

    $this->actingAs($this->user)
        ->withSession(['last_activity' => time() - 60])
        ->get('/timeout-test')
        ->assertOk();
});

it('logs out user after session timeout', function () {
    settings()->set('security.session_timeout', 5);

    $this->actingAs($this->user)
        ->withSession(['last_activity' => time() - 400])
        ->get('/timeout-test')
        ->assertRedirect(route('login'));
});

it('returns 401 for JSON requests after timeout', function () {
    settings()->set('security.session_timeout', 5);

    $this->actingAs($this->user)
        ->withSession(['last_activity' => time() - 400])
        ->getJson('/timeout-test')
        ->assertUnauthorized()
        ->assertJson(['message' => 'Session expired.']);
});

it('passes through for unauthenticated requests', function () {
    $this->get('/timeout-test')
        ->assertOk();
});
