<?php

use App\Actions\Activities\CreateActivity;
use App\Data\Activities\CreateActivityData;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\User;
use Database\Seeders\ListOfValuesSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ListOfValuesSeeder::class);
});

/**
 * Rename the seeded "Task" Activity Type list value (identified by its stable
 * metadata.icon key) to a new label, returning its id.
 */
function renameTaskListValue(string $newName): int
{
    $listId = ListName::query()->where('name', 'Activity Type')->value('id');

    $task = ListValue::query()
        ->where('list_name_id', $listId)
        ->get()
        ->first(fn (ListValue $v): bool => ($v->metadata['icon'] ?? null) === 'task');

    $task->update(['name' => $newName]);

    return (int) $task->id;
}

it('resolves the default activity type from the stable icon key after the Task label is renamed (action)', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    // Admin renames "Task" — the label is no longer the anchor.
    $renamedId = renameTaskListValue('To-Do');

    // No type supplied — the action must still default to the renamed Task value.
    $result = (new CreateActivity)(CreateActivityData::from(['subject' => 'Default type after rename']));

    expect($result->type_id)->toBe($renamedId)
        ->and($result->activity_type_name)->toBe('To-Do');
    expect(Activity::count())->toBe(1);
});

it('still defaults to Task by icon when the label is unchanged (action)', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $listId = ListName::query()->where('name', 'Activity Type')->value('id');
    $taskId = (int) ListValue::query()
        ->where('list_name_id', $listId)
        ->where('name', ActivityType::Task->label())
        ->value('id');

    $result = (new CreateActivity)(CreateActivityData::from(['subject' => 'No type given']));

    expect($result->type_id)->toBe($taskId);
});

it('resolves the default activity type from the stable icon key after the Task label is renamed (form)', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $renamedId = renameTaskListValue('Action Item');

    // The Volt create form pre-selects typeId in mount() via defaultTypeId().
    Volt::actingAs($user)
        ->test('activities.form')
        ->assertSet('typeId', $renamedId);
});

it('falls back to the first active value when no Task icon is present (action)', function () {
    $user = User::factory()->owner()->create();
    $this->actingAs($user);

    $listId = ListName::query()->where('name', 'Activity Type')->value('id');

    // Strip every metadata.icon so no value matches 'task'; default must fall
    // back to the first active value by sort order.
    ListValue::query()->where('list_name_id', $listId)->get()
        ->each(fn (ListValue $v) => $v->update(['metadata' => []]));

    $firstActiveId = (int) ListValue::query()
        ->where('list_name_id', $listId)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->value('id');

    $result = (new CreateActivity)(CreateActivityData::from(['subject' => 'Fallback']));

    expect($result->type_id)->toBe($firstActiveId);
});
