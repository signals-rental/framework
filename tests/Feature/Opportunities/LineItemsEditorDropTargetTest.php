<?php

use Symfony\Component\Process\Process;

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function runLineItemDropTargetResolver(array $payload): array
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

it('nests into a group when hovering its header and inserting after it', function () {
    $group = ['id' => 10, 'item_type' => 'group', 'depth' => 1, 'name' => 'Audio'];
    $productA = ['id' => 20, 'item_type' => 'product', 'depth' => 1, 'name' => 'Mic A'];
    $productB = ['id' => 30, 'item_type' => 'product', 'depth' => 1, 'name' => 'Mic B'];
    $rest = [$group, $productA, $productB];

    $result = runLineItemDropTargetResolver([
        'rest' => $rest,
        'draggedNode' => $productB,
        'insertIndex' => 1,
        'hoverRowIndex' => 0,
        'clientX' => 100,
        'startX' => 100,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    expect($result['targetDepth'])->toBe(2)
        ->and($result['insertIndex'])->toBe(1)
        ->and($result['parentId'])->toBe(10)
        ->and($result['parentType'])->toBe('group')
        ->and($result['valid'])->toBeTrue()
        ->and($result['highlightGroupId'])->toBe(10);
});

it('keeps root depth when inserting before the first group row', function () {
    $group = ['id' => 10, 'item_type' => 'group', 'depth' => 1, 'name' => 'Audio'];
    $productA = ['id' => 20, 'item_type' => 'product', 'depth' => 1, 'name' => 'Mic A'];
    $productB = ['id' => 30, 'item_type' => 'product', 'depth' => 1, 'name' => 'Mic B'];
    $rest = [$group, $productA, $productB];

    $result = runLineItemDropTargetResolver([
        'rest' => array_filter($rest, fn (array $row): bool => $row['id'] !== 30),
        'draggedNode' => $productB,
        'insertIndex' => 0,
        'hoverRowIndex' => 0,
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

it('keeps root depth when hover is on a nested row but insert is after the group subtree', function () {
    $group = ['id' => 10, 'item_type' => 'group', 'depth' => 1, 'name' => 'Audio'];
    $child = ['id' => 20, 'item_type' => 'product', 'depth' => 2, 'name' => 'Nested mic'];
    $product = ['id' => 30, 'item_type' => 'product', 'depth' => 1, 'name' => 'Root mic'];
    $rest = [$group, $child, $product];

    $result = runLineItemDropTargetResolver([
        'rest' => $rest,
        'draggedNode' => $product,
        'insertIndex' => 2,
        'hoverRowIndex' => 1,
        'clientX' => 120,
        'startX' => 120,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    expect($result['targetDepth'])->toBe(1)
        ->and($result['insertIndex'])->toBe(2)
        ->and($result['parentId'])->toBeNull()
        ->and($result['valid'])->toBeTrue()
        ->and($result['highlightGroupId'])->toBeNull();
});

it('resolves nest depth when the insert index lands in a group child region', function () {
    $group = ['id' => 10, 'item_type' => 'group', 'depth' => 1, 'name' => 'Audio'];
    $child = ['id' => 20, 'item_type' => 'product', 'depth' => 2, 'name' => 'Nested mic'];
    $product = ['id' => 30, 'item_type' => 'product', 'depth' => 1, 'name' => 'Root mic'];
    $rest = [$group, $child, $product];

    $result = runLineItemDropTargetResolver([
        'rest' => $rest,
        'draggedNode' => $product,
        'insertIndex' => 1,
        'hoverRowIndex' => 1,
        'clientX' => 40,
        'startX' => 200,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    expect($result['targetDepth'])->toBe(2)
        ->and($result['insertIndex'])->toBe(1)
        ->and($result['parentId'])->toBe(10)
        ->and($result['parentType'])->toBe('group')
        ->and($result['valid'])->toBeTrue();
});

it('keeps root depth when dropping outside any group region', function () {
    $group = ['id' => 10, 'item_type' => 'group', 'depth' => 1, 'name' => 'Audio'];
    $productA = ['id' => 20, 'item_type' => 'product', 'depth' => 2, 'name' => 'Nested mic'];
    $productB = ['id' => 30, 'item_type' => 'product', 'depth' => 1, 'name' => 'Root mic'];
    $rest = [$group, $productA, $productB];

    $result = runLineItemDropTargetResolver([
        'rest' => $rest,
        'draggedNode' => $productB,
        'insertIndex' => 2,
        'hoverRowIndex' => 2,
        'clientX' => 120,
        'startX' => 120,
        'startDepth' => 1,
        'originalParentId' => null,
    ]);

    expect($result['targetDepth'])->toBe(1)
        ->and($result['parentId'])->toBeNull()
        ->and($result['valid'])->toBeTrue()
        ->and($result['highlightGroupId'])->toBeNull();
});

it('parentAt returns the group parent for a nested insert index', function () {
    $group = ['id' => 10, 'item_type' => 'group', 'depth' => 1, 'name' => 'Audio'];
    $child = ['id' => 20, 'item_type' => 'product', 'depth' => 2, 'name' => 'Nested mic'];
    $rest = [$group, $child];

    $result = runLineItemDropTargetResolver([
        'action' => 'parentAt',
        'rest' => $rest,
        'insertIndex' => 1,
        'depth' => 2,
    ]);

    expect($result['parentId'])->toBe(10)
        ->and($result['parentType'])->toBe('group');
});
