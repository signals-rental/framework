<?php

use App\Enums\ActivityType;

it('has the correct CRMS type_id values', function () {
    expect(ActivityType::Task->value)->toBe(1001)
        ->and(ActivityType::Call->value)->toBe(1002)
        ->and(ActivityType::Fax->value)->toBe(1003)
        ->and(ActivityType::Email->value)->toBe(1004)
        ->and(ActivityType::Meeting->value)->toBe(1005)
        ->and(ActivityType::Note->value)->toBe(1006)
        ->and(ActivityType::Letter->value)->toBe(1007);
});

it('returns human-readable labels', function () {
    expect(ActivityType::Task->label())->toBe('Task')
        ->and(ActivityType::Meeting->label())->toBe('Meeting');
});

it('resolves from CRMS name', function () {
    expect(ActivityType::fromCrmsName('Task'))->toBe(ActivityType::Task)
        ->and(ActivityType::fromCrmsName('meeting'))->toBe(ActivityType::Meeting);
});

it('throws on unknown CRMS name', function () {
    ActivityType::fromCrmsName('unknown');
})->throws(\ValueError::class);
