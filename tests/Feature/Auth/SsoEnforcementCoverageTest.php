<?php

use App\Models\User;
use App\Services\Auth\SsoEnforcement;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->enforcement = app(SsoEnforcement::class);
    Role::findOrCreate('Sales', 'web');
});

it('treats a non-array enforced-roles setting as no enforcement', function () {
    // A malformed (string) enforced-roles setting must degrade to "no enforcement"
    // rather than throw — enforcedRoles() returns an empty list, so even a user who
    // holds a role is not forced onto SSO.
    settings()->set('security.sso_enforced_roles', 'Sales', 'string');

    $user = User::factory()->create();
    $user->assignRole('Sales');

    expect($this->enforcement->isEnforcedFor($user))->toBeFalse();
});
