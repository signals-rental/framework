<?php

use App\Enums\StockMethod;
use App\Enums\TransactionType;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

// Distinctive path from the placeholder image graphic.
$placeholderMarker = 'M21 15l-5-5L5 21';

it('shows the product image when the product has one', function () use ($placeholderMarker) {
    Storage::fake('public');

    $product = Product::factory()->create([
        'name' => 'LED Panel',
        'icon_thumb_url' => 'icons/product-thumb.jpg',
    ]);
    $stockLevel = StockLevel::factory()->create(['product_id' => $product->id]);

    Volt::test('stock-levels.show', ['stockLevel' => $stockLevel])
        ->assertSee('icons/product-thumb.jpg', false)
        ->assertDontSee($placeholderMarker, false);
});

it('shows a placeholder graphic when the product has no image', function () use ($placeholderMarker) {
    $product = Product::factory()->create([
        'name' => 'Plain Product',
        'icon_thumb_url' => null,
    ]);
    $stockLevel = StockLevel::factory()->create(['product_id' => $product->id]);

    Volt::test('stock-levels.show', ['stockLevel' => $stockLevel])
        ->assertSee($placeholderMarker, false);
});

it('rejects a decimal transaction quantity', function () {
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 0]);

    Volt::test('stock-levels.show', ['stockLevel' => $stockLevel])
        ->set('transactionType', TransactionType::Buy->value)
        ->set('transactionQuantity', '2.5')
        ->call('addTransaction')
        ->assertHasErrors('transactionQuantity');
});

it('limits serialised stock transactions to a single unit', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Serialised]);
    $stockLevel = StockLevel::factory()->create(['product_id' => $product->id]);

    Volt::test('stock-levels.show', ['stockLevel' => $stockLevel])
        ->set('transactionType', TransactionType::Buy->value)
        ->set('transactionQuantity', '3')
        ->call('addTransaction')
        ->assertHasErrors('transactionQuantity');
});

it('allows multiple units for bulk stock transactions', function () {
    $product = Product::factory()->create(['stock_method' => StockMethod::Bulk]);
    $stockLevel = StockLevel::factory()->create(['product_id' => $product->id, 'quantity_held' => 0]);

    Volt::test('stock-levels.show', ['stockLevel' => $stockLevel])
        ->set('transactionType', TransactionType::Buy->value)
        ->set('transactionQuantity', '5')
        ->call('addTransaction')
        ->assertHasNoErrors();

    expect((float) $stockLevel->fresh()->quantity_held)->toBe(5.0);
});

it('deletes a transaction and reverses its effect through the confirm flow', function () {
    $stockLevel = StockLevel::factory()->create(['quantity_held' => 0]);

    $component = Volt::test('stock-levels.show', ['stockLevel' => $stockLevel])
        ->set('transactionType', TransactionType::Buy->value)
        ->set('transactionQuantity', '4')
        ->call('addTransaction')
        ->assertHasNoErrors();

    expect((float) $stockLevel->fresh()->quantity_held)->toBe(4.0);

    $txn = $stockLevel->stockTransactions()->latest('id')->firstOrFail();

    $component->call('confirmDeleteTransaction', $txn->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteTransaction')
        ->assertSet('showDeleteModal', false);

    expect(StockTransaction::find($txn->id))->toBeNull();
    expect((float) $stockLevel->fresh()->quantity_held)->toBe(0.0);
});
