<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
    Store::factory()->create();
});

it('renders the violet serialised badge when a serialised product is selected', function () {
    $product = Product::factory()->serialised()->create(['name' => 'Serialised Camera']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->assertSee('Serialised Camera')
        ->assertSee('s-badge-violet', false)
        ->assertSee('Serialised Stock');
});

it('renders the cyan bulk badge when a bulk product is selected', function () {
    $product = Product::factory()->bulk()->create(['name' => 'Bulk Cable']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->assertSee('Bulk Cable')
        ->assertSee('s-badge-cyan', false)
        ->assertSee('Bulk Stock');
});

it('renders the consistent badge in the product search results', function () {
    // Set results directly to avoid the pg-only ilike search query on the SQLite test DB.
    Volt::test('stock-levels.form')
        ->set('productResults', [
            ['id' => 1, 'name' => 'Result Serialised', 'stock_method' => 2],
            ['id' => 2, 'name' => 'Result Bulk', 'stock_method' => 1],
        ])
        ->assertSee('Result Serialised')
        ->assertSee('Result Bulk')
        ->assertSee('s-badge-violet', false)
        ->assertSee('s-badge-cyan', false);
});

it('shows the single/bulk switcher and seeds a row when switching to bulk for serialised stock', function () {
    $product = Product::factory()->serialised()->create(['name' => 'Serialised Camera']);

    $component = Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->assertSet('entryMode', 'single')
        ->assertSee('Single')
        ->assertSee('Bulk')
        ->call('setEntryMode', 'bulk')
        ->assertSet('entryMode', 'bulk');

    expect($component->get('bulkRows'))->toHaveCount(1);
});

it('does not surface the bulk path for bulk products — single save still works', function () {
    $store = Store::first();
    $product = Product::factory()->bulk()->create(['name' => 'Bulk Cable']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', $store->id)
        ->set('quantityHeld', 10)
        ->assertDontSee('>Bulk<')
        ->call('save')
        ->assertHasNoErrors();

    expect(StockLevel::where('product_id', $product->id)->count())->toBe(1);
});

it('adds and removes bulk rows, always keeping at least one', function () {
    $product = Product::factory()->serialised()->create();

    $component = Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->call('addBulkRow')
        ->call('addBulkRow');

    expect($component->get('bulkRows'))->toHaveCount(3);

    $component->call('removeBulkRow', 1);
    expect($component->get('bulkRows'))->toHaveCount(2);

    // Cannot remove below one.
    $component->call('removeBulkRow', 0)->call('removeBulkRow', 0);
    expect($component->get('bulkRows'))->toHaveCount(1);
});

it('marks a unique asset and serial as valid', function () {
    $product = Product::factory()->serialised()->create();

    $component = Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows');

    $rows = $component->get('bulkRows');
    expect($rows[0]['asset_status'])->toBe('valid');
    expect($rows[0]['serial_status'])->toBe('valid');
});

it('marks an asset number matching an existing stock level as duplicate', function () {
    $product = Product::factory()->serialised()->create();
    StockLevel::factory()->create(['asset_number' => 'DUP-ASSET']);

    $component = Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'DUP-ASSET', 'serial_number' => 'S-9', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows');

    $rows = $component->get('bulkRows');
    expect($rows[0]['asset_status'])->toBe('duplicate');
    expect($rows[0]['serial_status'])->toBe('valid');
});

it('marks a serial number matching an existing stock level as duplicate', function () {
    $product = Product::factory()->serialised()->create();
    StockLevel::factory()->create(['serial_number' => 'DUP-SERIAL']);

    $component = Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-9', 'serial_number' => 'DUP-SERIAL', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows');

    $rows = $component->get('bulkRows');
    expect($rows[0]['serial_status'])->toBe('duplicate');
    expect($rows[0]['asset_status'])->toBe('valid');
});

it('flags both rows that share the same asset number', function () {
    $product = Product::factory()->serialised()->create();

    $component = Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'SAME', 'serial_number' => 'S-1', 'asset_status' => '', 'serial_status' => ''],
            ['asset_number' => 'SAME', 'serial_number' => 'S-2', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows');

    $rows = $component->get('bulkRows');
    expect($rows[0]['asset_status'])->toBe('duplicate');
    expect($rows[1]['asset_status'])->toBe('duplicate');
    expect($rows[0]['serial_status'])->toBe('valid');
    expect($rows[1]['serial_status'])->toBe('valid');
});

it('disables the submit button when a row is incomplete or duplicate, enables it when all valid', function () {
    $product = Product::factory()->serialised()->create();

    // Partial row (one field filled) → submit button disabled.
    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => '', 'asset_status' => 'valid', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows')
        ->assertSee('disabled', false);

    // All valid → button enabled (label reflects the complete count).
    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows')
        ->assertSee('Create 1 Stock Level');

    // Duplicate present → cannot submit (server check rejects).
    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', Store::first()->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => 'valid', 'serial_status' => 'valid'],
            ['asset_number' => 'A-1', 'serial_number' => 'S-2', 'asset_status' => 'valid', 'serial_status' => 'valid'],
        ])
        ->call('save')
        ->assertHasErrors('bulkRows');
});

it('ignores a trailing empty row in the submit gate (the Enter-row regression)', function () {
    $store = Store::first();
    $product = Product::factory()->serialised()->create();

    // One complete row + a trailing empty row (left after pressing Enter).
    // The submit label counts only the complete row, and a save succeeds —
    // the empty trailing row must not block submission.
    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', $store->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => '', 'serial_status' => ''],
            ['asset_number' => '', 'serial_number' => '', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('revalidateBulkRows')
        ->assertSee('Create 1 Stock Level')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.stock', $product->id));

    expect(StockLevel::where('product_id', $product->id)->count())->toBe(1);
});

it('rejects a bulk save when a partial row remains', function () {
    $store = Store::first();
    $product = Product::factory()->serialised()->create();

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', $store->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => 'valid', 'serial_status' => 'valid'],
            ['asset_number' => 'A-2', 'serial_number' => '', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('save')
        ->assertHasErrors('bulkRows');

    expect(StockLevel::where('product_id', $product->id)->count())->toBe(0);
});

it('drops empty rows on save and creates only the complete rows', function () {
    $store = Store::first();
    $product = Product::factory()->serialised()->create(['name' => 'Serialised Camera']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', $store->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => '', 'serial_status' => ''],
            ['asset_number' => '', 'serial_number' => '', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.stock', $product->id));

    $levels = StockLevel::where('product_id', $product->id)->get();
    expect($levels)->toHaveCount(1);

    $level = $levels->first();
    expect((float) $level->quantity_held)->toBe(1.0);
    expect($level->barcode)->toBe('A-1');
    expect($level->asset_number)->toBe('A-1');
    expect($level->serial_number)->toBe('S-1');
});

it('creates one stock level per bulk row and redirects to the product stock tab', function () {
    $store = Store::first();
    $product = Product::factory()->serialised()->create(['name' => 'Serialised Camera']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', $store->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'A-1', 'serial_number' => 'S-1', 'asset_status' => '', 'serial_status' => ''],
            ['asset_number' => 'A-2', 'serial_number' => 'S-2', 'asset_status' => '', 'serial_status' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.stock', $product->id));

    $levels = StockLevel::where('product_id', $product->id)->orderBy('asset_number')->get();
    expect($levels)->toHaveCount(2);

    foreach ($levels as $level) {
        expect((float) $level->quantity_held)->toBe(1.0);
        expect($level->barcode)->toBe($level->asset_number);
        expect($level->serial_number)->not->toBeNull();
        expect($level->item_name)->toBe('Serialised Camera');
    }
});

it('rejects a bulk save defensively when a duplicate is present server-side', function () {
    $store = Store::first();
    $product = Product::factory()->serialised()->create();
    StockLevel::factory()->create(['asset_number' => 'TAKEN']);

    Volt::test('stock-levels.form')
        ->call('selectProduct', $product->id)
        ->set('storeId', $store->id)
        ->call('setEntryMode', 'bulk')
        ->set('bulkRows', [
            ['asset_number' => 'TAKEN', 'serial_number' => 'S-1', 'asset_status' => 'valid', 'serial_status' => 'valid'],
        ])
        ->call('save')
        ->assertHasErrors('bulkRows');

    // Nothing created beyond the pre-existing one.
    expect(StockLevel::where('product_id', $product->id)->count())->toBe(0);
});
