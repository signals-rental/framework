<?php

use Illuminate\Support\Facades\Gate;

it('defines the viewHorizon gate', function () {
    expect(Gate::has('viewHorizon'))->toBeTrue();
});

it('denies viewHorizon for unauthenticated users', function () {
    expect(Gate::allows('viewHorizon'))->toBeFalse();
});

it('denies viewHorizon for users not in the email list', function () {
    $user = \App\Models\User::factory()->create(['email' => 'test@example.com']);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});
