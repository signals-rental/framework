<?php

use App\Actions\Opportunities\RenameOpportunityItem;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class)->group('opportunities');

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->actingAs($this->owner);
});

it('persistTree on a deep opp 9 shape succeeds with a valid reorder payload', function () {
    $opportunity = deepNestSyncOpportunity();
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $baseRevision = $editor->treeRevision();
    $nodes = deepNestLedParBeforeOnSiteNodes($opportunity);

    $result = $editor->persistTree($nodes, $baseRevision);

    expect($result['stale'])->toBeFalse()
        ->and($result['revision'])->toBeGreaterThan($baseRevision);
});

it('persistTree applies deep reorder despite revision drift from intervening item events', function () {
    $opportunity = deepNestSyncOpportunity();
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $staleRevision = $editor->treeRevision();
    $nodes = deepNestLedParBeforeOnSiteNodes($opportunity);
    $expectedIds = array_column($nodes, 'id');

    $ledPar = $opportunity->items->firstWhere('name', 'LED PAR');

    (new RenameOpportunityItem)(
        $ledPar,
        RenameOpportunityItemData::from(['name' => 'LED PAR renamed']),
    );

    expect($editor->treeRevision())->toBeGreaterThan($staleRevision);

    $result = $editor->persistTree($nodes, $staleRevision);

    expect($result['stale'])->toBeFalse()
        ->and($result['revision_drift'])->toBeTrue()
        ->and($result['revision'])->toBeGreaterThan($staleRevision);

    $pull = $editor->pullTree($staleRevision, [], []);

    expect(collect($pull['tree'])->pluck('id')->all())->toBe($expectedIds);

    $freshTree = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh()));
    $ledParFresh = $freshTree->firstWhere('id', $ledPar->id);
    $onSite = $freshTree->firstWhere('name', 'On-Site Tech');

    expect($freshTree->search(fn (array $row): bool => $row['id'] === $ledParFresh['id']))
        ->toBeLessThan($freshTree->search(fn (array $row): bool => $row['id'] === $onSite['id']));
});

it('serializes deep tree rows for IndexedDB without clone errors', function () {
    $opportunity = deepNestSyncOpportunity();
    $tree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);

    $process = new Process(
        ['node', base_path('tests/js/line-item-cache-runner.mjs')],
        base_path(),
        null,
        json_encode(['rows' => $tree, 'oppId' => $opportunity->id], JSON_THROW_ON_ERROR),
    );

    $process->run();

    expect($process->isSuccessful())->toBeTrue();

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);

    expect($decoded['count'])->toBe(count($tree))
        ->and($decoded['cloneable'])->toBeTrue();
});

it('pullTree after deep persistTree returns the persisted order when local rows are omitted (post-structural sync)', function () {
    $opportunity = deepNestSyncOpportunity();
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $baseRevision = $editor->treeRevision();
    $nodes = deepNestLedParBeforeOnSiteNodes($opportunity);

    $persist = $editor->persistTree($nodes, $baseRevision);

    expect($persist['stale'])->toBeFalse();

    $expectedIds = array_column($nodes, 'id');

    $pull = $editor->pullTree($baseRevision, [], []);

    expect($pull['stale'])->toBeTrue()
        ->and(collect($pull['tree'])->pluck('id')->all())->toBe($expectedIds);
});

it('pullTree after deep persistTree keeps persisted order even with stale revision and optimistic local rows', function () {
    $opportunity = deepNestSyncOpportunity();
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $baseRevision = $editor->treeRevision();
    $nodes = deepNestLedParBeforeOnSiteNodes($opportunity);

    $persist = $editor->persistTree($nodes, $baseRevision);

    expect($persist['stale'])->toBeFalse();

    $expectedIds = array_column($nodes, 'id');
    $optimisticLocalRows = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity))
        ->map(fn (array $row): array => array_merge($row, [
            'id' => $row['id'],
            'depth' => collect($nodes)->firstWhere('id', $row['id'])['depth'] ?? $row['depth'],
        ]))
        ->sortBy(fn (array $row): int => array_search($row['id'], $expectedIds, true))
        ->values()
        ->all();

    $pull = $editor->pullTree($baseRevision, $optimisticLocalRows, []);

    expect($pull['stale'])->toBeTrue()
        ->and(collect($pull['tree'])->pluck('id')->all())->toBe($expectedIds);
});

it('simulates flush without persist: stale pull with optimistic local rows reverts to the old server order', function () {
    $opportunity = deepNestSyncOpportunity();
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $baseRevision = $editor->treeRevision();
    $originalIds = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity))->pluck('id')->all();
    $nodes = deepNestLedParBeforeOnSiteNodes($opportunity);

    expect(array_column($nodes, 'id'))->not->toBe($originalIds);

    $optimisticLocalRows = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity))
        ->keyBy('id')
        ->all();

    foreach ($nodes as $node) {
        $optimisticLocalRows[$node['id']]['depth'] = $node['depth'];
    }

    $optimisticLocalRows = collect(array_column($nodes, 'id'))
        ->map(fn (int $id): array => $optimisticLocalRows[$id])
        ->all();

    $pull = $editor->pullTree($baseRevision, $optimisticLocalRows, []);

    expect($pull['stale'])->toBeFalse()
        ->and(collect($pull['tree'])->pluck('id')->all())->toBe($originalIds);
});
