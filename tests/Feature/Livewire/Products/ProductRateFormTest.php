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

it('renders the create rate form', function () {
    $this->get(route('products.rates.create', $this->product))
        ->assertOk()
        ->assertSee('Rate Definition')
        ->assertSee('Assign Rate');
});

it('defaults valid from to today on create', function () {
    Volt::test('products.rate-form', ['product' => $this->product])
        ->assertSet('validFrom', now()->toDateString());
});

it('creates a rate, converting the price to minor units, and redirects to the tab', function () {
    Volt::test('products.rate-form', ['product' => $this->product])
        ->set('rateDefinitionId', $this->definition->id)
        ->set('transactionType', 'rental')
        ->set('price', '50.00')
        ->set('currency', 'GBP')
        ->set('priority', 0)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.rates', $this->product));

    $this->assertDatabaseHas('product_rates', [
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price' => 5000,
    ]);
});

it('flashes a non-blocking overlap warning when the new rate overlaps', function () {
    ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'store_id' => null,
        'transaction_type' => 'rental',
        'priority' => 0,
        'valid_from' => null,
        'valid_to' => null,
    ]);

    Volt::test('products.rate-form', ['product' => $this->product])
        ->set('rateDefinitionId', $this->definition->id)
        ->set('transactionType', 'rental')
        ->set('price', '40.00')
        ->set('currency', 'GBP')
        ->set('priority', 0)
        ->set('validFrom', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(session('rate-overlap-warning'))->toBeGreaterThanOrEqual(1);
});

it('populates the form when editing', function () {
    $rate = ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price' => 1000,
        'currency' => 'GBP',
    ]);

    Volt::test('products.rate-form', ['product' => $this->product, 'rate' => $rate])
        ->assertSet('rateId', $rate->id)
        ->assertSet('price', '10.00');
});

it('updates a rate assignment', function () {
    $rate = ProductRate::factory()->create([
        'product_id' => $this->product->id,
        'rate_definition_id' => $this->definition->id,
        'price' => 1000,
        'currency' => 'GBP',
    ]);

    Volt::test('products.rate-form', ['product' => $this->product, 'rate' => $rate])
        ->set('price', '99.99')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.rates', $this->product));

    expect($rate->fresh()->price)->toBe(9999);
});

it('validates that a rate definition is selected', function () {
    Volt::test('products.rate-form', ['product' => $this->product])
        ->set('price', '10.00')
        ->call('save')
        ->assertHasErrors(['rateDefinitionId']);
});

it('returns 404 when editing a rate that belongs to a different product', function () {
    $otherProduct = Product::factory()->create();
    $rate = ProductRate::factory()->create([
        'product_id' => $otherProduct->id,
        'rate_definition_id' => $this->definition->id,
    ]);

    $this->get(route('products.rates.edit', [$this->product, $rate]))
        ->assertNotFound();
});

it('forbids users without the rates.view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('products.rates.create', $this->product))
        ->assertForbidden();
});
