<?php

use App\Services\Opportunities\LineItemTreeReconciler;

it('detects a stale client revision', function () {
    $reconciler = new LineItemTreeReconciler;

    expect($reconciler->isStale(10, 15))->toBeTrue()
        ->and($reconciler->isStale(15, 15))->toBeFalse()
        ->and($reconciler->isStale(0, 5))->toBeFalse();
});

it('preserves pending local rows during reconcile', function () {
    $reconciler = new LineItemTreeReconciler;

    $local = [
        ['id' => 1, 'path' => '0001', 'name' => 'Local edit', 'quantity' => '2', 'unit_price' => 1000, 'discount_percent' => null],
        ['id' => -99, 'path' => '0002', 'name' => 'Temp row', 'quantity' => '1', 'unit_price' => 500, 'discount_percent' => null],
    ];

    $server = [
        ['id' => 1, 'path' => '0001', 'name' => 'Server truth', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null],
    ];

    $result = $reconciler->reconcile($local, $server, pendingLocalIds: [1]);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['name'])->toBe('Local edit')
        ->and($result['rows'][1]['name'])->toBe('Temp row')
        ->and($result['conflicts'])->toHaveKey(1);
});

it('collects pending local ids from the sync queue', function () {
    $reconciler = new LineItemTreeReconciler;

    $ids = $reconciler->pendingLocalIdsFromQueue([
        ['kind' => 'field', 'id' => 3],
        ['kind' => 'persistTree'],
        ['kind' => 'field', 'id' => 3],
        ['kind' => 'field', 'id' => 7],
    ]);

    expect($ids)->toBe([3, 7]);
});

it('merges server-only rows and prefers server truth for synced ids', function () {
    $reconciler = new LineItemTreeReconciler;

    $local = [
        ['id' => 1, 'path' => '0001', 'name' => 'Stale', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null],
    ];
    $server = [
        ['id' => 1, 'path' => '0001', 'name' => 'Server truth', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null],
        ['id' => 2, 'path' => '0002', 'name' => 'Server only', 'quantity' => '1', 'unit_price' => 500, 'discount_percent' => null],
    ];

    $result = $reconciler->reconcile($local, $server);

    expect($result['rows'])->toHaveCount(2)
        ->and($result['rows'][0]['name'])->toBe('Server truth')
        ->and($result['rows'][1]['name'])->toBe('Server only')
        ->and($result['conflicts'])->toBe([]);
});

it('records a conflict when pending local edits diverge from the server row', function () {
    $reconciler = new LineItemTreeReconciler;

    $local = [
        ['id' => 4, 'path' => '0001', 'name' => 'Local edit', 'quantity' => '2', 'unit_price' => 1000, 'discount_percent' => null],
    ];
    $server = [
        ['id' => 4, 'path' => '0001', 'name' => 'Remote edit', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null],
    ];

    $result = $reconciler->reconcile($local, $server, pendingLocalIds: [4]);

    expect($result['rows'][0]['name'])->toBe('Local edit')
        ->and($result['conflicts'][4])->toBe('This line was also changed elsewhere.');
});

it('sorts reconciled rows by materialised path order', function () {
    $reconciler = new LineItemTreeReconciler;

    $result = $reconciler->reconcile(
        [
            ['id' => -1, 'path' => '0002', 'name' => 'Temp', 'quantity' => '1', 'unit_price' => 100, 'discount_percent' => null],
        ],
        [
            ['id' => 2, 'path' => '0002', 'name' => 'B', 'quantity' => '1', 'unit_price' => 100, 'discount_percent' => null],
            ['id' => 1, 'path' => '0001', 'name' => 'A', 'quantity' => '1', 'unit_price' => 100, 'discount_percent' => null],
        ],
    );

    expect(collect($result['rows'])->pluck('name')->all())->toBe(['A', 'Temp', 'B']);
});
