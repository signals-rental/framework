<?php

use App\Services\Opportunities\LineItemTreeReconciler;

it('keeps a pending local row without flagging a conflict when no hard field diverges', function () {
    $reconciler = new LineItemTreeReconciler;

    // Same hard fields (quantity/unit_price/discount_percent/name) on both sides —
    // hasHardFieldConflict must fall through to its `return false` (line 117).
    $local = [
        ['id' => 5, 'path' => '0001', 'name' => 'Same', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null, 'notes' => 'local note'],
    ];
    $server = [
        ['id' => 5, 'path' => '0001', 'name' => 'Same', 'quantity' => '1', 'unit_price' => 1000, 'discount_percent' => null, 'notes' => 'server note'],
    ];

    $result = $reconciler->reconcile($local, $server, pendingLocalIds: [5]);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]['notes'])->toBe('local note')
        ->and($result['conflicts'])->toBe([]);
});
