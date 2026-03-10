<?php

use App\Actions\Admin\TransferOwnership;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

it('transfers ownership to another user', function () {
    $newOwner = User::factory()->create();

    (new TransferOwnership)($newOwner);

    $this->owner->refresh();
    $newOwner->refresh();

    expect($this->owner->is_owner)->toBeFalse();
    expect($newOwner->is_owner)->toBeTrue();
    expect($newOwner->is_admin)->toBeTrue();
});

it('rejects transfer when acting user is not the owner', function () {
    $nonOwner = User::factory()->create();
    $this->actingAs($nonOwner);

    $target = User::factory()->create();

    (new TransferOwnership)($target);
})->throws(ValidationException::class, 'Only the current owner can transfer ownership.');

it('rejects transfer to self', function () {
    (new TransferOwnership)($this->owner);
})->throws(ValidationException::class, 'You are already the owner.');

it('rejects transfer to a deactivated user', function () {
    $deactivatedUser = User::factory()->deactivated()->create();

    (new TransferOwnership)($deactivatedUser);
})->throws(ValidationException::class, 'Cannot transfer ownership to a deactivated user.');
