<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RestructureOpportunityItems;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RestructureOpportunityItemsData;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Thunk\Verbs\Facades\Verbs;

uses()->group('opportunities');

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function runDropTargetJs(array $payload): array
{
    $process = new Process(
        ['node', base_path('tests/js/line-item-drop-target-runner.mjs')],
        base_path(),
        null,
        json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * @return array<int, array{id: int, item_type: string, depth: int, name: string}>
 */
function oppNineShapeRows(): array
{
    return [
        ['id' => 1, 'item_type' => 'group', 'depth' => 1, 'name' => 'Main Stage Audio'],
        ['id' => 2, 'item_type' => 'product', 'depth' => 2, 'name' => 'Sennheiser'],
        ['id' => 3, 'item_type' => 'accessory', 'depth' => 3, 'name' => 'XLR Cable'],
        ['id' => 4, 'item_type' => 'group', 'depth' => 1, 'name' => 'Lighting Package'],
        ['id' => 5, 'item_type' => 'group', 'depth' => 2, 'name' => 'Staging & Decks'],
        ['id' => 6, 'item_type' => 'product', 'depth' => 3, 'name' => 'Moving Head'],
        ['id' => 7, 'item_type' => 'accessory', 'depth' => 4, 'name' => '16A Power Distro'],
        ['id' => 8, 'item_type' => 'product', 'depth' => 3, 'name' => 'Stage Deck'],
        ['id' => 9, 'item_type' => 'product', 'depth' => 2, 'name' => '55" LED Display'],
        ['id' => 10, 'item_type' => 'product', 'depth' => 1, 'name' => 'On-Site Tech'],
        ['id' => 11, 'item_type' => 'product', 'depth' => 1, 'name' => 'LED PAR'],
    ];
}

function deepNestOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Deep nest drag repro',
        'store_id' => test()->store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    $spec = [
        ['item_type' => 'group', 'name' => 'Main Stage Audio', 'path' => '0001'],
        ['item_type' => 'product', 'name' => 'Sennheiser', 'path' => '00010001'],
        ['item_type' => 'accessory', 'name' => 'XLR Cable', 'path' => '000100010001'],
        ['item_type' => 'group', 'name' => 'Lighting Package', 'path' => '0002'],
        ['item_type' => 'group', 'name' => 'Staging & Decks', 'path' => '00020001'],
        ['item_type' => 'product', 'name' => 'Moving Head', 'path' => '000200010001'],
        ['item_type' => 'accessory', 'name' => '16A Power Distro', 'path' => '0002000100010001'],
        ['item_type' => 'product', 'name' => 'Stage Deck', 'path' => '000200010002'],
        ['item_type' => 'product', 'name' => '55" LED Display', 'path' => '00020002'],
        ['item_type' => 'product', 'name' => 'On-Site Tech', 'path' => '0003'],
        ['item_type' => 'product', 'name' => 'LED PAR', 'path' => '0004'],
    ];

    DB::transaction(function () use ($opportunity, $spec): void {
        foreach ($spec as $row) {
            $itemId = app(SequenceAllocator::class)->next('opportunity_items');

            ItemAdded::fire(array_merge([
                'opportunity_item_id' => $itemId,
                'opportunity_id' => $opportunity->id,
                'starts_at' => '2026-07-01T09:00:00Z',
                'ends_at' => '2026-07-05T17:00:00Z',
                'quantity' => '1',
                'transaction_type' => LineItemTransactionType::Rental->value,
            ], $row));

            Verbs::commit();
        }
    });

    return $opportunity->fresh(['items']);
}

it('keeps root depth when inserting before a root sibling after a deep group subtree (opp 9 boundary)', function () {
    $rows = oppNineShapeRows();
    $dragged = ['id' => 11, 'item_type' => 'product', 'depth' => 1, 'name' => 'LED PAR'];
    $rest = array_values(array_filter($rows, fn (array $row): bool => $row['id'] !== 11));
    $insertIndex = array_search(10, array_column($rest, 'id'), true);

    $result = runDropTargetJs([
        'rest' => $rest,
        'draggedNode' => $dragged,
        'insertIndex' => $insertIndex,
        'hoverRowIndex' => $insertIndex,
        'clientX' => 100,
        'startX' => 100,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    expect($result['targetDepth'])->toBe(1)
        ->and($result['parentId'])->toBeNull()
        ->and($result['valid'])->toBeTrue()
        ->and($result['highlightGroupId'])->toBeNull();
});

it('builds a valid persist payload when moving LED PAR before On-Site Tech in the opp 9 shape', function () {
    $rows = oppNineShapeRows();
    $dragged = ['id' => 11, 'item_type' => 'product', 'depth' => 1, 'name' => 'LED PAR'];
    $rest = array_values(array_filter($rows, fn (array $row): bool => $row['id'] !== 11));
    $insertIndex = array_search(10, array_column($rest, 'id'), true);

    $target = runDropTargetJs([
        'rest' => $rest,
        'draggedNode' => $dragged,
        'insertIndex' => $insertIndex,
        'hoverRowIndex' => $insertIndex,
        'clientX' => 100,
        'startX' => 100,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    $move = runDropTargetJs([
        'action' => 'applyDragBlockMove',
        'rows' => $rows,
        'drag' => [
            'block' => [$dragged],
            'blockIds' => [11],
            'targetIndex' => $target['insertIndex'],
            'targetDepth' => $target['targetDepth'],
            'startDepth' => 1,
            'valid' => $target['valid'],
            'originalParentId' => null,
        ],
    ]);

    expect($move['applied'])->toBeTrue();

    $nodes = $move['nodes'];
    $ledPar = collect($nodes)->firstWhere('id', 11);
    $onSite = collect($nodes)->firstWhere('id', 10);

    expect($ledPar['depth'])->toBe(1)
        ->and(array_search(11, array_column($nodes, 'id'), true))
        ->toBeLessThan(array_search(10, array_column($nodes, 'id'), true));
});

it('accepts the client persist payload on the server for a deep opp 9 root reorder', function () {
    $opportunity = deepNestOpportunity();

    $itemsByName = $opportunity->items->keyBy('name');
    $ledPar = $itemsByName['LED PAR'];
    $onSite = $itemsByName['On-Site Tech'];

    $rows = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh()))
        ->map(fn (array $row): array => [
            'id' => $row['id'],
            'item_type' => $row['item_type'],
            'depth' => $row['depth'],
            'name' => $row['name'],
        ])
        ->all();

    $dragged = collect($rows)->firstWhere('id', $ledPar->id);
    $rest = array_values(array_filter($rows, fn (array $row): bool => $row['id'] !== $ledPar->id));
    $insertIndex = array_search($onSite->id, array_column($rest, 'id'), true);

    $target = runDropTargetJs([
        'rest' => $rest,
        'draggedNode' => $dragged,
        'insertIndex' => $insertIndex,
        'hoverRowIndex' => $insertIndex,
        'clientX' => 100,
        'startX' => 100,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    $move = runDropTargetJs([
        'action' => 'applyDragBlockMove',
        'rows' => $rows,
        'drag' => [
            'block' => [$dragged],
            'blockIds' => [$ledPar->id],
            'targetIndex' => $target['insertIndex'],
            'targetDepth' => $target['targetDepth'],
            'startDepth' => 1,
            'valid' => $target['valid'],
            'originalParentId' => null,
        ],
    ]);

    expect($move['applied'])->toBeTrue();

    (new RestructureOpportunityItems)(
        $opportunity->fresh(['items']),
        RestructureOpportunityItemsData::from(['nodes' => $move['nodes']]),
    );

    $freshTree = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity->fresh()));
    $ledIndex = $freshTree->search(fn (array $row): bool => $row['id'] === $ledPar->id);
    $onSiteIndex = $freshTree->search(fn (array $row): bool => $row['id'] === $onSite->id);

    expect($ledIndex)->toBeLessThan($onSiteIndex)
        ->and($freshTree->firstWhere('id', $ledPar->id)['depth'])->toBe(1)
        ->and($opportunity->fresh(['items'])->items->firstWhere('id', $ledPar->id)?->parentPath())->toBeNull()
        ->and($freshTree->firstWhere('id', $onSite->id)['depth'])->toBe(1);
});

it('still nests into a group when the insert index is strictly inside its child region', function () {
    $rows = oppNineShapeRows();
    $dragged = ['id' => 11, 'item_type' => 'product', 'depth' => 1, 'name' => 'LED PAR'];
    $rest = array_values(array_filter($rows, fn (array $row): bool => $row['id'] !== 11));
    $insertIndex = array_search(9, array_column($rest, 'id'), true);

    $result = runDropTargetJs([
        'rest' => $rest,
        'draggedNode' => $dragged,
        'insertIndex' => $insertIndex,
        'hoverRowIndex' => $insertIndex,
        'clientX' => 100,
        'startX' => 100,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    expect($result['targetDepth'])->toBe(2)
        ->and($result['parentId'])->toBe(4)
        ->and($result['valid'])->toBeTrue();
});
