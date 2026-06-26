<?php

use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../../Support/DemoSeederTestHelpers.php';

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);

    // The async availability recalculation jobs are queued by the demand
    // observers; fake the queue so the seeder runs without a worker.
    Queue::fake();

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ProductGroupSeeder::class);

    // An owner is required so the event-sourced action Gate checks pass.
    User::factory()->owner()->create();

    // The seeder needs a default store, demo organisations, and the full demo
    // product catalogue (including included accessories for tree materialisation).
    Store::factory()->create(['is_default' => true, 'timezone' => 'UTC']);

    Member::factory()
        ->organisation()
        ->count(3)
        ->create(['tag_list' => ['demo-data']]);

    seedDemoProducts();
});

it('seeds demo opportunities tagged demo-data via the event-sourced path', function () {
    seedDemoOpportunities();

    $demoOpportunities = Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get();

    expect($demoOpportunities)->not->toBeEmpty();
    $demoOpportunities->each(function (Opportunity $opportunity): void {
        expect($opportunity->tag_list)->toContain('demo-data')
            ->and($opportunity->number)->not->toBeNull();
    });
});

it('seeds a spread across draft, quotation and order states', function () {
    seedDemoOpportunities();

    $states = Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get()
        ->pluck('state')
        ->map(fn (OpportunityState $state): int => $state->value)
        ->unique();

    expect($states)->toContain(OpportunityState::Order->value)
        ->and($states)->toContain(OpportunityState::Quotation->value)
        ->and($states)->toContain(OpportunityState::Draft->value);
});

it('gives every demo opportunity at least one line item', function () {
    seedDemoOpportunities();

    Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->get()
        ->each(function (Opportunity $opportunity): void {
            expect($opportunity->items()->count())->toBeGreaterThan(0);
        });
});

it('seeds a three-level line-item tree with valid paths and rollups on the headline demo opportunity', function () {
    seedDemoOpportunities();

    $opportunity = Opportunity::query()
        ->whereJsonContains('tag_list', 'demo-data')
        ->where('reference', 'DEMO-OPP-0001')
        ->firstOrFail();

    $items = $opportunity->items()->orderBy('path')->get();

    $group = $items->first(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group);
    expect($group)->not->toBeNull()
        ->and($group->path)->toMatch('/^\d{4}$/');

    $product = $items->first(function (OpportunityItem $item) use ($group): bool {
        return $item->item_type === OpportunityItemType::Product
            && str_starts_with($item->path, $group->path)
            && strlen($item->path) === strlen($group->path) + 4;
    });
    expect($product)->not->toBeNull();

    $accessory = $items->first(function (OpportunityItem $item) use ($product): bool {
        return $item->item_type === OpportunityItemType::Accessory
            && str_starts_with($item->path, $product->path)
            && strlen($item->path) === strlen($product->path) + 4;
    });
    expect($accessory)->not->toBeNull()
        ->and($items->where('item_type', OpportunityItemType::Group)->count())->toBeGreaterThan(1)
        ->and($items->where('item_type', OpportunityItemType::Service)->count())->toBeGreaterThan(0)
        ->and($opportunity->charge_total)->toBeGreaterThan(0)
        ->and($opportunity->charge_excluding_tax_total)->toBeGreaterThan(0)
        ->and($opportunity->tax_total)->not->toBeNull();
});

it('is idempotent on re-run', function () {
    seedDemoOpportunities();
    $firstCount = Opportunity::query()->whereJsonContains('tag_list', 'demo-data')->count();

    seedDemoOpportunities();
    $secondCount = Opportunity::query()->whereJsonContains('tag_list', 'demo-data')->count();

    expect($secondCount)->toBe($firstCount);
});

it('returns early without erroring when there are no demo members or products', function () {
    Member::query()->whereJsonContains('tag_list', 'demo-data')->forceDelete();
    Product::query()->whereJsonContains('tag_list', 'demo-data')->forceDelete();

    seedDemoOpportunities();

    expect(Opportunity::query()->whereJsonContains('tag_list', 'demo-data')->count())->toBe(0);
});
