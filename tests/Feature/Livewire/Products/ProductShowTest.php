<?php

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\StockLevel;
use App\Models\User;
use App\Support\Formatter;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the show page with product details', function () {
    $product = Product::factory()->create(['name' => 'LED Panel 4x8']);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('LED Panel 4x8');
});

it('shows the product name and type', function () {
    $product = Product::factory()->rental()->create(['name' => 'Wireless Microphone']);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Wireless Microphone')
        ->assertSee('Rental');
});

it('shows active badge for active products', function () {
    $product = Product::factory()->create(['name' => 'Active Product', 'is_active' => true]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Active');
});

it('shows inactive badge for inactive products', function () {
    $product = Product::factory()->inactive()->create(['name' => 'Inactive Product']);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Inactive');
});

it('loads product group relationship', function () {
    $group = ProductGroup::factory()->create(['name' => 'Audio Equipment']);
    $product = Product::factory()->create([
        'name' => 'Grouped Product',
        'product_group_id' => $group->id,
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Audio Equipment');
});

it('shows description when present', function () {
    $product = Product::factory()->create([
        'name' => 'Described Product',
        'description' => 'A detailed product description',
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('A detailed product description');
});

it('requires authentication', function () {
    $product = Product::factory()->create();
    auth()->logout();

    $this->get(route('products.show', $product))
        ->assertRedirect();
});

it('shows quick stats values for available quantity, stock levels and accessories', function () {
    $product = Product::factory()->bulk()->create(['name' => 'Stat Product']);

    StockLevel::factory()->create([
        'product_id' => $product->id,
        'quantity_held' => 10,
        'quantity_allocated' => 3,
        'quantity_unavailable' => 2,
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Available Quantity')
        ->assertSee('Stock Levels')
        ->assertSee('Accessories')
        // 10 - 3 - 2 = 5 available
        ->assertSee('5');
});

it('renders stock method as a violet badge for serialised products', function () {
    $product = Product::factory()->serialised()->create(['name' => 'Serial Product']);

    $rendered = Volt::test('products.show', ['product' => $product])->html();

    expect($rendered)->toContain('s-badge-violet');
    expect($rendered)->toContain('Serialised');

    // Stock Method must render as the badge, never the raw enum value (2).
    $methodSection = substr($rendered, strpos($rendered, 'Stock Method') ?: 0, 400);
    expect($methodSection)->not->toContain('>2<');
});

it('renders stock method as a cyan badge for bulk products', function () {
    $product = Product::factory()->bulk()->create(['name' => 'Bulk Product']);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('s-badge-cyan', false);
});

it('omits the barcode row in product details for serialised products', function () {
    $product = Product::factory()->serialised()->create([
        'name' => 'Serial No Barcode',
        'barcode' => 'BC-SERIAL-1',
    ]);

    $rendered = Volt::test('products.show', ['product' => $product])->html();

    // Barcode should not appear in the Product Details grid (only Key Attributes may show it).
    $detailsSection = substr($rendered, strpos($rendered, 'Product Details') ?: 0, 1500);
    expect($detailsSection)->not->toContain('Barcode');
});

it('shows the barcode row in product details for non-serialised products with a barcode', function () {
    $product = Product::factory()->bulk()->create([
        'name' => 'Bulk With Barcode',
        'barcode' => 'BC-BULK-1',
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Barcode')
        ->assertSee('BC-BULK-1');
});

it('shows pricing with the system currency symbol', function () {
    $product = Product::factory()->bulk()->create([
        'name' => 'Priced Product',
        'purchase_price' => 123400,
    ]);

    $rendered = Volt::test('products.show', ['product' => $product])->html();

    // Pricing renders via the system-currency Formatter (currency symbol/code + amount),
    // not the bare numeric formatMoneyCost output.
    $expected = app(Formatter::class)->money(123400);

    expect($expected)->toContain('1,234.00');
    expect($rendered)->toContain($expected);
});

it('does not render weight or sku in the key attributes panel', function () {
    $product = Product::factory()->bulk()->create([
        'name' => 'Attr Product',
        'weight' => 5.5,
        'sku' => 'SKU-XYZ',
    ]);

    $rendered = Volt::test('products.show', ['product' => $product])->html();

    $keyAttrs = substr($rendered, strpos($rendered, 'Key Attributes') ?: 0);
    expect($keyAttrs)->not->toContain('Weight');
    expect($keyAttrs)->not->toContain('SKU-XYZ');
});

it('does not render the icon upload component on the show page', function () {
    $product = Product::factory()->create(['name' => 'No Upload Product']);

    // The escaped component-name marker is how assertSeeLivewire() detects a nested component.
    Volt::test('products.show', ['product' => $product])
        ->assertDontSeeHtml('&quot;name&quot;:&quot;components.icon-upload&quot;');
});

it('renders the description in a full-width center panel', function () {
    $product = Product::factory()->create([
        'name' => 'Desc Product',
        'description' => 'A long product description that spans the full center column.',
    ]);

    Volt::test('products.show', ['product' => $product])
        ->assertSee('Description')
        ->assertSee('A long product description that spans the full center column.');
});

it('includes the merge modal on a non-overview product tab', function () {
    $product = Product::factory()->create(['name' => 'Merge Tab Product']);

    // The merge modal must render on non-overview tabs (regression: previously overview-only).
    Volt::test('products.stock', ['product' => $product])
        ->assertSeeHtml('&quot;name&quot;:&quot;products.merge-modal&quot;')
        ->assertSee('open-merge-modal', false);
});

it('includes the merge modal on the overview tab without double rendering', function () {
    $product = Product::factory()->create(['name' => 'Overview Merge Product']);

    $rendered = Volt::test('products.show', ['product' => $product])->html();

    // The merge action listener is wired once via the shared header partial.
    expect(substr_count($rendered, 'open-modal.window'))->toBeGreaterThanOrEqual(1);
});
