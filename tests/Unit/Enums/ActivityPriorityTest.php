<?php

use App\Enums\ActivityPriority;

it('has the correct priority values', function () {
    expect(ActivityPriority::Low->value)->toBe(0)
        ->and(ActivityPriority::Normal->value)->toBe(1)
        ->and(ActivityPriority::High->value)->toBe(2);
});

it('returns human-readable labels', function () {
    expect(ActivityPriority::Low->label())->toBe('Low')
        ->and(ActivityPriority::Normal->label())->toBe('Normal')
        ->and(ActivityPriority::High->label())->toBe('High');
});
