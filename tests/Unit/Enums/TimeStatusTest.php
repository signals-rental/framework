<?php

use App\Enums\TimeStatus;

it('has the correct values', function () {
    expect(TimeStatus::Free->value)->toBe(0)
        ->and(TimeStatus::Busy->value)->toBe(1);
});

it('returns human-readable labels', function () {
    expect(TimeStatus::Free->label())->toBe('Free')
        ->and(TimeStatus::Busy->label())->toBe('Busy');
});
