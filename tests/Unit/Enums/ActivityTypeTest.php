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

it('returns the correct label for every case', function (ActivityType $type, string $label) {
    expect($type->label())->toBe($label);
})->with([
    'task' => [ActivityType::Task, 'Task'],
    'call' => [ActivityType::Call, 'Call'],
    'fax' => [ActivityType::Fax, 'Fax'],
    'email' => [ActivityType::Email, 'Email'],
    'meeting' => [ActivityType::Meeting, 'Meeting'],
    'note' => [ActivityType::Note, 'Note'],
    'letter' => [ActivityType::Letter, 'Letter'],
]);

it('resolves from CRMS name', function () {
    expect(ActivityType::fromCrmsName('Task'))->toBe(ActivityType::Task)
        ->and(ActivityType::fromCrmsName('meeting'))->toBe(ActivityType::Meeting);
});

it('resolves every CRMS name case-insensitively', function (string $name, ActivityType $expected) {
    expect(ActivityType::fromCrmsName($name))->toBe($expected);
})->with([
    'task' => ['task', ActivityType::Task],
    'call' => ['call', ActivityType::Call],
    'fax' => ['fax', ActivityType::Fax],
    'email' => ['email', ActivityType::Email],
    'meeting' => ['meeting', ActivityType::Meeting],
    'note' => ['note', ActivityType::Note],
    'letter' => ['letter', ActivityType::Letter],
    'mixed-case' => ['LeTTeR', ActivityType::Letter],
]);

it('throws on unknown CRMS name', function () {
    ActivityType::fromCrmsName('unknown');
})->throws(ValueError::class);
