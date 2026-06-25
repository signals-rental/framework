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
