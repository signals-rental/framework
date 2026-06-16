<?php

use App\Actions\Products\DeleteProductGroup;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('deletes a product group', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    (new DeleteProductGroup)($group);

    expect(ProductGroup::find($group->id))->toBeNull();

    // The AuditableEvent → LogAction listener is not faked here, so the deletion
    // is recorded end-to-end in action_logs against the acting user.
    assertActionLogged('product_group.deleted', ProductGroup::class, $group->id, $user->id);
});

it('throws authorization exception without permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $group = ProductGroup::factory()->create();

    (new DeleteProductGroup)($group);
})->throws(AuthorizationException::class);
