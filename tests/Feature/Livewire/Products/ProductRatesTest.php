<?php

use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
    $this->product = Product::factory()->create();
    $this->definition = RateDefinition::factory()->create(['name' => 'Daily Rate']);
});

it('renders the product rates tab', function () {
    $this->get(route('products.rates', $this->product))
        ->assertOk()
        ->assertSee('Rates');
});

it('lists the rates assigned to the product', function () {
    ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price' => 5000,
    ]);

    $this->get(route('products.rates', $this->product))
        ->assertSee('Daily Rate');
});

it('deletes a rate assignment', function () {
    $rate = ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
    ]);

    Volt::test('products.rates', ['product' => $this->product])
        ->call('deleteRate', $rate->id)
        ->assertDispatched('rate-deleted');

    $this->assertDatabaseMissing('product_rates', ['id' => $rate->id]);
});

it('forbids users without the rates.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('products.rates', $this->product))
        ->assertForbidden();
});
