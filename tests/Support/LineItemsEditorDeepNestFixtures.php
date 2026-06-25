<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityLineItemsTreeBuilder;
use App\Services\SequenceAllocator;
use App\Verbs\Events\Opportunities\ItemAdded;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Thunk\Verbs\Facades\Verbs;

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function runDeepNestDropTargetJs(array $payload): array
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

function deepNestSyncOpportunity(): Opportunity
{
    Auth::login(test()->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Deep nest sync repro',
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

/**
 * @return array<int, array{id: int, depth: int}>
 */
function deepNestLedParBeforeOnSiteNodes(Opportunity $opportunity): array
{
    $rows = collect(app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity))
        ->map(fn (array $row): array => [
            'id' => $row['id'],
            'item_type' => $row['item_type'],
            'depth' => $row['depth'],
            'name' => $row['name'],
        ])
        ->all();

    $ledPar = collect($rows)->firstWhere('name', 'LED PAR');
    $onSite = collect($rows)->firstWhere('name', 'On-Site Tech');
    $rest = array_values(array_filter($rows, fn (array $row): bool => $row['id'] !== $ledPar['id']));
    $insertIndex = array_search($onSite['id'], array_column($rest, 'id'), true);

    $target = runDeepNestDropTargetJs([
        'rest' => $rest,
        'draggedNode' => $ledPar,
        'insertIndex' => $insertIndex,
        'hoverRowIndex' => $insertIndex,
        'clientX' => 100,
        'startX' => 100,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    $move = runDeepNestDropTargetJs([
        'action' => 'applyDragBlockMove',
        'rows' => $rows,
        'drag' => [
            'block' => [$ledPar],
            'blockIds' => [$ledPar['id']],
            'targetIndex' => $target['insertIndex'],
            'targetDepth' => $target['targetDepth'],
            'startDepth' => 1,
            'valid' => $target['valid'],
            'originalParentId' => null,
        ],
    ]);

    expect($move['applied'])->toBeTrue();

    /** @var array<int, array{id: int, depth: int}> $nodes */
    $nodes = $move['nodes'];

    return $nodes;
}
