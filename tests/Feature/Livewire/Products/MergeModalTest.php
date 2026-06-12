<?php

use App\Events\AuditableEvent;
use App\Livewire\Products\MergeModal;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    actingAs(User::factory()->owner()->create());
});

it('opens the merge modal and sets product IDs', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->assertSet('productAId', $productA->id)
        ->assertSet('productBId', $productB->id)
        ->assertSet('primaryId', $productA->id);
});

it('defaults primary to product A', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->assertSet('primaryId', $productA->id);
});

it('allows swapping the primary product', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->assertSet('primaryId', $productA->id)
        ->set('primaryId', $productB->id)
        ->assertSet('primaryId', $productB->id);
});

it('performs a successful merge and dispatches product-merged event', function () {
    Event::fake([AuditableEvent::class]);

    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->call('merge')
        ->assertDispatched('product-merged')
        ->assertRedirect(route('products.show', $productA->id));
});

it('merges with swapped primary correctly', function () {
    Event::fake([AuditableEvent::class]);

    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->set('primaryId', $productB->id)
        ->call('merge')
        ->assertDispatched('product-merged')
        ->assertRedirect(route('products.show', $productB->id));

    // Product A should be soft-deleted (it was the secondary)
    expect(Product::find($productA->id))->toBeNull();
    expect(Product::withTrashed()->find($productA->id))->not->toBeNull();
});

it('does not merge products of different types', function () {
    $rental = Product::factory()->rental()->create();
    $sale = Product::factory()->sale()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $rental->id, productB: $sale->id)
        ->call('merge')
        ->assertNotDispatched('product-merged')
        ->assertNoRedirect();

    // Both products should still exist
    expect(Product::find($rental->id))->not->toBeNull();
    expect(Product::find($sale->id))->not->toBeNull();
});

it('surfaces a validation error when the secondary product is archived', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    $component = Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id);

    // Soft-delete the secondary: the DTO's withoutTrashed() exists rule now rejects it
    // on the Livewire path (validateAndCreate), surfacing a ValidationException that the
    // modal flashes as an error — not a ModelNotFound "no longer exists" message.
    $productB->delete();

    $component->call('merge')
        ->assertNotDispatched('product-merged')
        ->assertNoRedirect();

    expect(session('error'))->not->toBe('One of the selected products no longer exists.');

    // The primary product is untouched.
    expect(Product::find($productA->id))->not->toBeNull();
});

it('does not merge when a product does not exist', function () {
    $productA = Product::factory()->rental()->create();
    $fakeId = 999999;

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $fakeId)
        ->call('merge')
        ->assertNotDispatched('product-merged')
        ->assertNoRedirect();

    // Original product should still exist
    expect(Product::find($productA->id))->not->toBeNull();
});

it('does not merge when products are not set', function () {
    Livewire::test(MergeModal::class)
        ->call('merge')
        ->assertNotDispatched('product-merged')
        ->assertNoRedirect();
});

it('clears search results when query is too short', function () {
    $productA = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id)
        ->set('mergeSearch', 'X')
        ->assertSet('mergeSearchResults', []);
});

it('clears search results when no product is selected', function () {
    Livewire::test(MergeModal::class)
        ->set('mergeSearch', 'Something')
        ->assertSet('mergeSearchResults', []);
});

it('searches and filters products by name case-insensitively', function () {
    $productA = Product::factory()->rental()->create(['name' => 'Alpha Speaker', 'is_active' => true]);
    $match = Product::factory()->rental()->create(['name' => 'Alpha Mixer', 'is_active' => true]);
    $other = Product::factory()->rental()->create(['name' => 'Beta Light', 'is_active' => true]);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id)
        ->set('mergeSearch', 'alpha')
        ->assertSet('mergeSearchResults', function (array $results) use ($match, $productA, $other) {
            $ids = collect($results)->pluck('id');

            return $ids->contains($match->id)
                && ! $ids->contains($productA->id)
                && ! $ids->contains($other->id);
        });
});

it('excludes inactive products from search results', function () {
    $productA = Product::factory()->rental()->create(['name' => 'Gamma Console', 'is_active' => true]);
    $inactive = Product::factory()->rental()->create(['name' => 'Gamma Spare', 'is_active' => false]);

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id)
        ->set('mergeSearch', 'Gamma')
        ->assertSet('mergeSearchResults', function (array $results) use ($inactive) {
            return ! collect($results)->pluck('id')->contains($inactive->id);
        });
});

it('selects a merge target', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id)
        ->call('selectMergeTarget', $productB->id)
        ->assertSet('productBId', $productB->id)
        ->assertSet('mergeSearch', '')
        ->assertSet('mergeSearchResults', []);
});

it('clears merge target', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->call('clearMergeTarget')
        ->assertSet('productBId', null)
        ->assertSet('mergeSearch', '')
        ->assertSet('mergeSearchResults', []);
});

it('rejects merge when primary is not one of the selected products', function () {
    $productA = Product::factory()->rental()->create();
    $productB = Product::factory()->rental()->create();
    $other = Product::factory()->rental()->create();

    Livewire::test(MergeModal::class)
        ->dispatch('open-merge-modal', productA: $productA->id, productB: $productB->id)
        ->set('primaryId', $other->id)
        ->call('merge')
        ->assertNotDispatched('product-merged')
        ->assertNoRedirect();
});
