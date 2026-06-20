<?php

use App\Enums\ShortageDispatchPolicy;

it('defaults to warn_partial (the non-blocking, visible posture)', function () {
    expect(ShortageDispatchPolicy::default())->toBe(ShortageDispatchPolicy::WarnPartial);
});

it('only blocks dispatch under the Block policy', function () {
    expect(ShortageDispatchPolicy::Block->blocksDispatch())->toBeTrue()
        ->and(ShortageDispatchPolicy::WarnPartial->blocksDispatch())->toBeFalse()
        ->and(ShortageDispatchPolicy::AllowPartial->blocksDispatch())->toBeFalse();
});

it('only warns on partial under WarnPartial', function () {
    expect(ShortageDispatchPolicy::WarnPartial->warnsOnPartial())->toBeTrue()
        ->and(ShortageDispatchPolicy::Block->warnsOnPartial())->toBeFalse()
        ->and(ShortageDispatchPolicy::AllowPartial->warnsOnPartial())->toBeFalse();
});

it('exposes human labels', function () {
    expect(ShortageDispatchPolicy::Block->label())->toBe('Block')
        ->and(ShortageDispatchPolicy::WarnPartial->label())->toBe('Warn with partial')
        ->and(ShortageDispatchPolicy::AllowPartial->label())->toBe('Allow partial');
});
