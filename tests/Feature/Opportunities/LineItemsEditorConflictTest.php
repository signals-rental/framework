<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RenameOpportunityItem;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\RenameOpportunityItemData;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

/**
 * Drive the client-side reconcileLocalTree() (resources/js/line-item-tree-reconcile.js)
 * through a Node runner so the browser merge logic is tested directly, not only via
 * its PHP mirror.
 *
 * @param  array<string, mixed>  $payload
 * @return array{ids: array<int, int>, names: array<int, string|null>, conflicts: array<string, string>}
 */
function runReconcileJs(array $payload): array
{
    $process = new Process(
        ['node', base_path('tests/js/line-item-tree-reconcile-runner.mjs')],
        base_path(),
        null,
        json_encode($payload, JSON_THROW_ON_ERROR),
    );

    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
    }

    /** @var array{ids: array<int, int>, names: array<int, string|null>, conflicts: array<string, string>} $decoded */
    $decoded = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

function conflictTestOpportunity(): Opportunity
{
    Auth::login(test()->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Conflict test']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('applies persistTree despite revision drift when the node set is complete', function () {
    $opportunity = conflictTestOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Line A',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line B',
        'quantity' => '1',
        'unit_price' => 500,
    ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
    $revision = lineItemsEditorInstance($component)->treeRevision();

    $first = $opportunity->fresh(['items'])->items->sortBy('path')->first();

    (new RenameOpportunityItem)(
        $first,
        RenameOpportunityItemData::from(['name' => 'Line A renamed']),
    );

    $items = $opportunity->fresh(['items'])->items->sortBy('path')->values();
    $nodes = $items->map(fn ($item): array => ['id' => $item->id, 'depth' => $item->depth()])->reverse()->values()->all();

    $result = lineItemsEditorInstance($component)->persistTree($nodes, $revision);

    expect($result['stale'])->toBeFalse()
        ->and($result['revision_drift'])->toBeTrue()
        ->and($result['revision'])->toBeGreaterThan($revision);

    $serverIds = collect(lineItemsEditorInstance($component)->serverTree()['tree'])->pluck('id')->all();

    expect($serverIds)->toBe(array_column($nodes, 'id'));
});

describe('reconcileLocalTree (client-side JS)', function () {
    it('server-wins for a non-pending row', function () {
        $result = runReconcileJs([
            'localRows' => [['id' => 1, 'path' => '0001', 'name' => 'Local name', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null]],
            'serverRows' => [['id' => 1, 'path' => '0001', 'name' => 'Server name', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null]],
            'pendingLocalIds' => [],
        ]);

        expect($result['ids'])->toBe([1])
            ->and($result['names'])->toBe(['Server name'])
            ->and($result['conflicts'])->toBe([]);
    });

    it('local-wins when the id is in pendingLocalIds, flagging a hard-field conflict', function () {
        $result = runReconcileJs([
            'localRows' => [['id' => 1, 'path' => '0001', 'name' => 'Pending local', 'quantity' => '2', 'unit_price' => 1000, 'discount_percent' => null]],
            'serverRows' => [['id' => 1, 'path' => '0001', 'name' => 'Server name', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null]],
            'pendingLocalIds' => [1],
        ]);

        expect($result['ids'])->toBe([1])
            ->and($result['names'])->toBe(['Pending local'])
            ->and($result['conflicts'])->toHaveKey('1');
    });

    it('keeps a pending local row without conflict when hard fields match the server', function () {
        $result = runReconcileJs([
            'localRows' => [['id' => 1, 'path' => '0001', 'name' => 'Same', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null]],
            'serverRows' => [['id' => 1, 'path' => '0001', 'name' => 'Same', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null]],
            'pendingLocalIds' => [1],
        ]);

        expect($result['ids'])->toBe([1])
            ->and($result['conflicts'])->toBe([]);
    });

    it('appends negative temp-ID local rows after the server rows', function () {
        $result = runReconcileJs([
            'localRows' => [['id' => -42, 'path' => '0002', 'name' => 'Brand new local', 'quantity' => '1', 'unit_price' => 0, 'discount_percent' => null]],
            'serverRows' => [['id' => 1, 'path' => '0001', 'name' => 'Server', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null]],
            'pendingLocalIds' => [],
        ]);

        expect($result['ids'])->toBe([1, -42])
            ->and($result['conflicts'])->toBe([]);
    });
});

it('pullTree reconciles local pending rows against fresh server truth', function () {
    $opportunity = conflictTestOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Server line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
    $item = $opportunity->fresh(['items'])->items->first();

    $localRows = [[
        'id' => $item->id,
        'path' => $item->path,
        'name' => 'Pending local rename',
        'quantity' => '1',
        'unit_price' => 1000,
        'discount_percent' => null,
    ]];

    $pull = lineItemsEditorInstance($component)->pullTree(0, $localRows, [$item->id]);

    expect($pull['tree'])->not->toBeEmpty()
        ->and($pull['conflicts'])->toHaveKey($item->id);
});
