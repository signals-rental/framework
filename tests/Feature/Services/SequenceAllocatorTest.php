<?php

use App\Models\Sequence;
use App\Services\SequenceAllocator;

describe('SequenceAllocator', function () {
    it('starts a new sequence at 1 and increments', function () {
        $allocator = app(SequenceAllocator::class);

        expect($allocator->next('test'))->toBe(1)
            ->and($allocator->next('test'))->toBe(2)
            ->and($allocator->next('test'))->toBe(3);
    });

    it('keeps independent sequences separate by name', function () {
        $allocator = app(SequenceAllocator::class);

        expect($allocator->next('a'))->toBe(1)
            ->and($allocator->next('b'))->toBe(1)
            ->and($allocator->next('a'))->toBe(2);
    });

    it('persists the next value to hand out in the sequences table', function () {
        $allocator = app(SequenceAllocator::class);

        $allocator->next('persisted');
        $allocator->next('persisted');

        expect(Sequence::query()->where('name', 'persisted')->value('next_value'))->toBe(3);
    });
});
