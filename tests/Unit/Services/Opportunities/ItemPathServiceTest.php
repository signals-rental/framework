<?php

use App\Services\Opportunities\ItemPathService;

beforeEach(function () {
    $this->service = new ItemPathService;
});

// ─── rebuild: flat siblings ──────────────────────────────────────────

it('rebuilds three flat siblings to sequential top-level paths', function () {
    $paths = $this->service->rebuild([
        ['id' => 10, 'depth' => 1],
        ['id' => 20, 'depth' => 1],
        ['id' => 30, 'depth' => 1],
    ]);

    expect($paths)->toBe([
        10 => '0001',
        20 => '0002',
        30 => '0003',
    ]);
});

it('returns an empty map for no nodes', function () {
    expect($this->service->rebuild([]))->toBe([]);
});

// ─── rebuild: nesting ────────────────────────────────────────────────

it('rebuilds a nested group with two children then a sibling group', function () {
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 1],
        ['id' => 2, 'depth' => 2],
        ['id' => 3, 'depth' => 2],
        ['id' => 4, 'depth' => 1],
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '00010001',
        3 => '00010002',
        4 => '0002',
    ]);
});

it('rebuilds deep multi-level nesting', function () {
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 1],
        ['id' => 2, 'depth' => 2],
        ['id' => 3, 'depth' => 3],
        ['id' => 4, 'depth' => 4],
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '00010001',
        3 => '000100010001',
        4 => '0001000100010001',
    ]);
});

// ─── rebuild: counter reset ──────────────────────────────────────────

it('resets deeper counters when depth decreases then increases again', function () {
    // group A with two children, then group B with one child.
    // The child of B must restart at 0001, not continue from the A children.
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 1], // A
        ['id' => 2, 'depth' => 2], // A.1
        ['id' => 3, 'depth' => 2], // A.2
        ['id' => 4, 'depth' => 1], // B
        ['id' => 5, 'depth' => 2], // B.1 -> must be 0002 0001, not 0002 0003
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '00010001',
        3 => '00010002',
        4 => '0002',
        5 => '00020001',
    ]);
});

it('resets a depth-3 counter when returning to that depth under a new parent', function () {
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 1],
        ['id' => 2, 'depth' => 2],
        ['id' => 3, 'depth' => 3],
        ['id' => 4, 'depth' => 3],
        ['id' => 5, 'depth' => 2], // new depth-2 parent
        ['id' => 6, 'depth' => 3], // restarts at 0001 under new parent
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '00010001',
        3 => '000100010001',
        4 => '000100010002',
        5 => '00010002',
        6 => '000100020001',
    ]);
});

// ─── rebuild: depth clamp ────────────────────────────────────────────

it('clamps a depth jump greater than one level after a top-level node', function () {
    // Node 2 claims depth 3 right after a depth-1 node -> clamped to depth 2.
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 1],
        ['id' => 2, 'depth' => 3],
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '00010001',
    ]);
});

it('clamps the first node to depth 1 regardless of claimed depth', function () {
    $paths = $this->service->rebuild([
        ['id' => 9, 'depth' => 5],
    ]);

    expect($paths)->toBe([9 => '0001']);
});

it('clamps depth to a minimum of one', function () {
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 0],
        ['id' => 2, 'depth' => -3],
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '0002',
    ]);
});

it('clamps a jump from depth 2 up to depth 4 down to depth 3', function () {
    $paths = $this->service->rebuild([
        ['id' => 1, 'depth' => 1],
        ['id' => 2, 'depth' => 2],
        ['id' => 3, 'depth' => 4], // clamped to 3
    ]);

    expect($paths)->toBe([
        1 => '0001',
        2 => '00010001',
        3 => '000100010001',
    ]);
});

// ─── validatePlacement: accessory ────────────────────────────────────

it('accepts an accessory placed under a product', function () {
    $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 3, 'item_type' => 'accessory'],
    ]);
})->throwsNoExceptions();

it('rejects an accessory placed under a group', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'accessory'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects an accessory placed at root', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'accessory'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects an accessory placed under a service', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'service'],
        ['id' => 3, 'depth' => 3, 'item_type' => 'accessory'],
    ]))->toThrow(InvalidArgumentException::class);
});

// ─── validatePlacement: group/product/service ────────────────────────

it('accepts product, group and service nested under a group', function () {
    $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 2, 'item_type' => 'group'],
        ['id' => 4, 'depth' => 2, 'item_type' => 'service'],
    ]);
})->throwsNoExceptions();

it('accepts group, product and service at root', function () {
    $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 1, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 1, 'item_type' => 'service'],
    ]);
})->throwsNoExceptions();

it('rejects a product placed under another product', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 3, 'item_type' => 'product'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a group placed under a product', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 3, 'item_type' => 'group'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a service placed under a product', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 3, 'item_type' => 'service'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('accepts an empty placement list', function () {
    $this->service->validatePlacement([]);
})->throwsNoExceptions();

// ─── validatePlacement: clamp affects parent resolution ──────────────

it('applies the depth clamp before resolving parents so an over-deep accessory still validates against the clamped parent', function () {
    // Accessory claims depth 4 after group(1) -> product(2); clamped to depth 3,
    // making its parent the product -> legal.
    $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 2, 'item_type' => 'product'],
        ['id' => 3, 'depth' => 4, 'item_type' => 'accessory'],
    ]);
})->throwsNoExceptions();

it('rejects placement once the clamp re-parents an accessory under a group', function () {
    // Accessory claims depth 3 right after a depth-1 group; clamped to depth 2,
    // parent becomes the group -> illegal.
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'group'],
        ['id' => 2, 'depth' => 3, 'item_type' => 'accessory'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown item_type role', function () {
    expect(fn () => $this->service->validatePlacement([
        ['id' => 1, 'depth' => 1, 'item_type' => 'widget'],
    ]))->toThrow(ValueError::class);
});
