<?php

use App\Actions\Admin\SendPasswordReset;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('sends a password reset link successfully', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn(Password::RESET_LINK_SENT);

    $user = User::factory()->create();

    $status = (new SendPasswordReset)($user);

    expect($status)->toBe(Password::RESET_LINK_SENT);
});

it('throws validation exception when throttled', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andReturn(Password::RESET_THROTTLED);

    $user = User::factory()->create();

    (new SendPasswordReset)($user);
})->throws(ValidationException::class);

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $target = User::factory()->create();

    (new SendPasswordReset)($target);
})->throws(AuthorizationException::class);
