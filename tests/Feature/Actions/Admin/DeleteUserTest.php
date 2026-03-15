<?php

use App\Actions\Admin\DeleteUser;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('deletes a user', function () {
    $user = User::factory()->create();

    (new DeleteUser)($user);

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('revokes API tokens on deletion', function () {
    $user = User::factory()->create();
    $user->createToken('test-token', ['users:read']);

    (new DeleteUser)($user);

    expect($user->tokens()->count())->toBe(0);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('prevents deleting the owner', function () {
    $owner = User::factory()->owner()->create();

    (new DeleteUser)($owner);
})->throws(ValidationException::class);

it('rejects unauthorized users', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $target = User::factory()->create();

    (new DeleteUser)($target);
})->throws(AuthorizationException::class);
