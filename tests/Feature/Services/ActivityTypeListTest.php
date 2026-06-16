<?php

use App\Enums\ActivityType;
use App\Models\ListName;
use App\Models\ListValue;
use App\Services\Activities\ActivityTypeList;
use Database\Seeders\ListOfValuesSeeder;

it('resolves the list id and active values for the Activity Type list', function () {
    $this->seed(ListOfValuesSeeder::class);

    $service = app(ActivityTypeList::class);

    $listId = ListName::query()->where('name', 'Activity Type')->value('id');

    expect($service->listId())->toBe($listId)
        ->and($service->activeValues())->not->toBeEmpty()
        ->and($service->activeValues()->every(fn (ListValue $v): bool => $v->is_active))->toBeTrue();
});

it('returns null list id and empty values when the list is unseeded', function () {
    // The activities migration creates the "Activity Type" list, so drop it to
    // exercise the unseeded state.
    ListValue::query()->whereNotNull('id')->delete();
    ListName::query()->where('name', 'Activity Type')->delete();

    $service = app(ActivityTypeList::class);
    $service->clearCache();

    expect($service->listId())->toBeNull()
        ->and($service->activeValues())->toBeEmpty()
        ->and($service->defaultId())->toBeNull();
});

it('defaults to the Task value anchored on the stable icon key', function () {
    $this->seed(ListOfValuesSeeder::class);

    $listId = ListName::query()->where('name', 'Activity Type')->value('id');
    $taskId = (int) ListValue::query()
        ->where('list_name_id', $listId)
        ->where('name', ActivityType::Task->label())
        ->value('id');

    $service = app(ActivityTypeList::class);

    expect($service->defaultId())->toBe($taskId)
        ->and($service->taskId())->toBe($taskId);
});

it('falls back to the first active value when no Task icon is present', function () {
    $this->seed(ListOfValuesSeeder::class);

    $listId = ListName::query()->where('name', 'Activity Type')->value('id');
    ListValue::query()->where('list_name_id', $listId)->get()
        ->each(fn (ListValue $v) => $v->update(['metadata' => []]));

    $firstActiveId = (int) ListValue::query()
        ->where('list_name_id', $listId)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->value('id');

    expect(app(ActivityTypeList::class)->defaultId())->toBe($firstActiveId);
});

it('memoises resolution until the cache is cleared', function () {
    $this->seed(ListOfValuesSeeder::class);

    $service = app(ActivityTypeList::class);
    $before = $service->activeValues()->count();

    // Add an active value after the cache is warm — it is not seen until cleared.
    $listId = ListName::query()->where('name', 'Activity Type')->value('id');
    ListValue::query()->create([
        'list_name_id' => $listId,
        'name' => 'Site Visit',
        'sort_order' => 99,
        'is_active' => true,
        'metadata' => ['icon' => 'task'],
    ]);

    expect($service->activeValues()->count())->toBe($before);

    $service->clearCache();

    expect($service->activeValues()->count())->toBe($before + 1);
});
