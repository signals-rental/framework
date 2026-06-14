<?php

use App\Services\Calendar\EventLaneAllocator;

beforeEach(function () {
    $this->allocator = new EventLaneAllocator;
});

it('returns an empty array for no events', function () {
    expect($this->allocator->allocate([]))->toBe([]);
});

it('places a single event in lane 0 of a single lane', function () {
    $result = $this->allocator->allocate([
        ['id' => 1, 'start_min' => 0, 'end_min' => 60],
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe(1)
        ->and($result[0]['lane'])->toBe(0)
        ->and($result[0]['lanes'])->toBe(1);
});

it('gives two overlapping events separate lanes with lanes = 2', function () {
    $result = $this->allocator->allocate([
        ['id' => 'a', 'start_min' => 0, 'end_min' => 60],
        ['id' => 'b', 'start_min' => 30, 'end_min' => 90],
    ]);

    expect($result[0]['lane'])->toBe(0)
        ->and($result[1]['lane'])->toBe(1)
        ->and($result[0]['lanes'])->toBe(2)
        ->and($result[1]['lanes'])->toBe(2);
});

it('does not overlap events that merely touch (end == next start)', function () {
    $result = $this->allocator->allocate([
        ['id' => 'a', 'start_min' => 0, 'end_min' => 60],
        ['id' => 'b', 'start_min' => 60, 'end_min' => 120],
    ]);

    expect($result[0]['lane'])->toBe(0)
        ->and($result[1]['lane'])->toBe(0)
        ->and($result[0]['lanes'])->toBe(1)
        ->and($result[1]['lanes'])->toBe(1);
});

it('treats a transitively-overlapping chain as one cluster', function () {
    // A(0-60), B(30-90), C(80-120): A overlaps B, B overlaps C, but A does not
    // overlap C. C reuses lane 0 (free once A ends at 60), giving cluster lanes 2.
    $result = $this->allocator->allocate([
        ['id' => 'A', 'start_min' => 0, 'end_min' => 60],
        ['id' => 'B', 'start_min' => 30, 'end_min' => 90],
        ['id' => 'C', 'start_min' => 80, 'end_min' => 120],
    ]);

    $byId = [];
    foreach ($result as $event) {
        $byId[$event['id']] = $event;
    }

    expect($byId['A']['lane'])->toBe(0)
        ->and($byId['B']['lane'])->toBe(1)
        ->and($byId['C']['lane'])->toBe(0)
        ->and($byId['A']['lanes'])->toBe(2)
        ->and($byId['B']['lanes'])->toBe(2)
        ->and($byId['C']['lanes'])->toBe(2);
});

it('preserves the original input order and extra keys', function () {
    $result = $this->allocator->allocate([
        ['id' => 'late', 'start_min' => 30, 'end_min' => 90, 'subject' => 'second'],
        ['id' => 'early', 'start_min' => 0, 'end_min' => 60, 'subject' => 'first'],
    ]);

    expect($result[0]['id'])->toBe('late')
        ->and($result[0]['subject'])->toBe('second')
        ->and($result[1]['id'])->toBe('early')
        ->and($result[1]['subject'])->toBe('first');
});

it('splits non-overlapping events into separate single-lane clusters', function () {
    $result = $this->allocator->allocate([
        ['id' => 'a', 'start_min' => 0, 'end_min' => 60],
        ['id' => 'b', 'start_min' => 120, 'end_min' => 180],
    ]);

    expect($result[0]['lanes'])->toBe(1)
        ->and($result[1]['lanes'])->toBe(1)
        ->and($result[0]['lane'])->toBe(0)
        ->and($result[1]['lane'])->toBe(0);
});
