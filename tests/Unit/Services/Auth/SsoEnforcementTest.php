<?php

use App\Models\User;
use App\Services\Auth\SsoEnforcement;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// DatabaseMigrations: roles/users and the settings() store are persisted to the DB.
uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    $this->enforcement = app(SsoEnforcement::class);
    Role::findOrCreate('Sales', 'web');
    Role::findOrCreate('Read Only', 'web');
});

it('enforces sso for a user holding an enforced role', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create();
    $user->assignRole('Sales');

    expect($this->enforcement->isEnforcedFor($user))->toBeTrue();
});

it('does not enforce sso for a user without an enforced role', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $user = User::factory()->create();
    $user->assignRole('Read Only');

    expect($this->enforcement->isEnforcedFor($user))->toBeFalse();
});

it('does not enforce sso when the enforced-role list is empty', function () {
    settings()->set('security.sso_enforced_roles', [], 'json');

    $user = User::factory()->create();
    $user->assignRole('Sales');

    expect($this->enforcement->isEnforcedFor($user))->toBeFalse();
});

it('does not enforce sso when the enforced-role setting is unset', function () {
    $user = User::factory()->create();
    $user->assignRole('Sales');

    expect($this->enforcement->isEnforcedFor($user))->toBeFalse();
});

it('always exempts the owner even with an enforced role (break-glass)', function () {
    settings()->set('security.sso_enforced_roles', ['Sales'], 'json');

    $owner = User::factory()->owner()->create();
    $owner->assignRole('Sales');

    expect($this->enforcement->isEnforcedFor($owner))->toBeFalse();
});
