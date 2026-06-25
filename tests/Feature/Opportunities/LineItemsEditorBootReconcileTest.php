<?php

use App\Models\Opportunity;
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

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function runBootReconcileJs(array $payload): array
{
    $process = new Process(
        ['node', base_path('tests/js/line-item-boot-reconcile-runner.mjs')],
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
 * @param  array<int, array<string, mixed>>  $tree
 * @return array{tree: array<int, array<string, mixed>>, revision: string, cacheToken: string}
 */
function bootSeedPayload(Opportunity $opportunity, array $tree, int $revision): array
{
    return [
        'tree' => $tree,
        'revision' => (string) $revision,
        'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
    ];
}

it('boot reconcile compares snowflake revision tokens without js number precision loss', function () {
    $serverRevision = '460798819026006016';
    $cacheRevision = '460798819026006015';
    $tree = [['id' => 1, 'depth' => 1, 'parent_group_id' => null]];

    $decision = runBootReconcileJs([
        'cached' => $tree,
        'meta' => [
            'revision' => $cacheRevision,
            'cacheToken' => 'quotation:draft',
        ],
        'seedPayload' => [
            'tree' => $tree,
            'revision' => $serverRevision,
            'cacheToken' => 'quotation:draft',
        ],
    ]);

    expect($decision['source'])->toBe('server')
        ->and($decision['reason'])->toBe('cache-stale');
});

it('boot reconcile uses server seed when cache revision is older than the server seed revision', function () {
    $opportunity = deepNestSyncOpportunity();
    $serverTree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $serverRevision = lineItemsEditorInstance($component)->treeRevision();

    $decision = runBootReconcileJs([
        'cached' => $serverTree,
        'meta' => [
            'key' => 'opp-'.$opportunity->id.'-cache',
            'revision' => (string) ($serverRevision - 1),
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ],
        'seedPayload' => bootSeedPayload($opportunity, $serverTree, $serverRevision),
    ]);

    expect($decision['source'])->toBe('server')
        ->and($decision['reason'])->toBe('cache-stale');
});

it('boot reconcile uses cache when revision and structure match the server seed', function () {
    $opportunity = deepNestSyncOpportunity();
    $serverTree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $serverRevision = lineItemsEditorInstance($component)->treeRevision();

    $decision = runBootReconcileJs([
        'cached' => $serverTree,
        'meta' => [
            'key' => 'opp-'.$opportunity->id.'-cache',
            'revision' => (string) $serverRevision,
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ],
        'seedPayload' => bootSeedPayload($opportunity, $serverTree, $serverRevision),
    ]);

    expect($decision['source'])->toBe('cache')
        ->and($decision['reason'])->toBe('in-sync');
});

it('boot reconcile rejects corrupt cache with matching revision but scrambled structure', function () {
    $opportunity = deepNestSyncOpportunity();
    $serverTree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $serverRevision = lineItemsEditorInstance($component)->treeRevision();

    $corruptCache = $serverTree;
    $corruptCache[2] = array_merge($corruptCache[2], [
        'depth' => 1,
        'parent_group_id' => null,
    ]);
    $corruptCache[3] = array_merge($corruptCache[3], [
        'depth' => 3,
        'parent_group_id' => $corruptCache[1]['id'],
    ]);

    $decision = runBootReconcileJs([
        'cached' => $corruptCache,
        'meta' => [
            'key' => 'opp-'.$opportunity->id.'-cache',
            'revision' => (string) $serverRevision,
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ],
        'seedPayload' => bootSeedPayload($opportunity, $serverTree, $serverRevision),
    ]);

    expect($decision['source'])->toBe('server')
        ->and($decision['reason'])->toBe('corrupt-cache-structure')
        ->and($decision['seedSignature'])->not->toBe($decision['cacheSignature']);
});

it('boot reconcile rejects partial cache rows missing server ids', function () {
    $opportunity = deepNestSyncOpportunity();
    $serverTree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $serverRevision = lineItemsEditorInstance($component)->treeRevision();

    $decision = runBootReconcileJs([
        'cached' => array_slice($serverTree, 0, 5),
        'meta' => [
            'key' => 'opp-'.$opportunity->id.'-cache',
            'revision' => (string) $serverRevision,
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ],
        'seedPayload' => bootSeedPayload($opportunity, $serverTree, $serverRevision),
    ]);

    expect($decision['source'])->toBe('server')
        ->and($decision['reason'])->toBe('partial-cache');
});

it('boot reconcile uses server seed after a persisted reorder when cache still holds the pre-edit tree', function () {
    $opportunity = deepNestSyncOpportunity();
    $prePersistTree = app(OpportunityLineItemsTreeBuilder::class)->tree($opportunity);
    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh(['items'])]);
    $editor = lineItemsEditorInstance($component);
    $preRevision = $editor->treeRevision();
    $nodes = deepNestLedParBeforeOnSiteNodes($opportunity);

    $persist = $editor->persistTree($nodes, $preRevision);

    expect($persist['stale'])->toBeFalse();

    $serverTree = $editor->serverTree()['tree'];
    $serverRevision = $editor->treeRevision();
    $expectedIds = array_column($nodes, 'id');

    expect(collect($serverTree)->pluck('id')->all())->toBe($expectedIds);

    $decision = runBootReconcileJs([
        'cached' => $prePersistTree,
        'meta' => [
            'key' => 'opp-'.$opportunity->id.'-cache',
            'revision' => (string) $preRevision,
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ],
        'seedPayload' => bootSeedPayload($opportunity->fresh(), $serverTree, $serverRevision),
    ]);

    expect($decision['source'])->toBe('server')
        ->and($decision['reason'])->toBe('cache-stale');

    $inSyncDecision = runBootReconcileJs([
        'cached' => $serverTree,
        'meta' => [
            'key' => 'opp-'.$opportunity->id.'-cache',
            'revision' => (string) $serverRevision,
            'cacheToken' => $opportunity->state->value.':'.$opportunity->status,
        ],
        'seedPayload' => bootSeedPayload($opportunity->fresh(), $serverTree, $serverRevision),
    ]);

    expect($inSyncDecision['source'])->toBe('cache');
});
