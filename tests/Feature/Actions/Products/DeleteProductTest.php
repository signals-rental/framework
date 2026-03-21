<?php

use App\Actions\Products\DeleteProduct;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('deletes a product via soft delete', function () {
    Event::fake([AuditableEvent::class]);

    $product = Product::factory()->create();

    (new DeleteProduct)($product);

    expect(Product::find($product->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->not->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('requires products.delete permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::factory()->create();

    (new DeleteProduct)($product);
})->throws(AuthorizationException::class);
