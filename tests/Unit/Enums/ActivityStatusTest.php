<?php

use App\Enums\ActivityStatus;

it('has the correct CRMS status_id values', function () {
    expect(ActivityStatus::Scheduled->value)->toBe(2001)
        ->and(ActivityStatus::Completed->value)->toBe(2002)
        ->and(ActivityStatus::Cancelled->value)->toBe(2003)
        ->and(ActivityStatus::Held->value)->toBe(2004);
});

it('returns human-readable labels', function () {
    expect(ActivityStatus::Scheduled->label())->toBe('Scheduled')
        ->and(ActivityStatus::Completed->label())->toBe('Completed');
});
