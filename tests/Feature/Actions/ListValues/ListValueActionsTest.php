<?php

use App\Actions\ListValues\CreateListValue;
use App\Actions\ListValues\DeleteListValue;
use App\Actions\ListValues\UpdateListValue;
use App\Data\ListValues\CreateListValueData;
use App\Data\ListValues\UpdateListValueData;
use App\Events\AuditableEvent;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

it('creates a list value', function () {
    Event::fake([AuditableEvent::class]);

    $listName = ListName::factory()->create();

    $data = CreateListValueData::from([
        'list_name_id' => $listName->id,
        'name' => 'Mobile',
        'sort_order' => 1,
    ]);

    $result = (new CreateListValue)($data);

    expect($result->name)->toBe('Mobile');
    expect($result->list_name_id)->toBe($listName->id);
    expect($result->sort_order)->toBe(1);

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a list value', function () {
    Event::fake([AuditableEvent::class]);

    $value = ListValue::factory()->create(['name' => 'Old Value']);

    $data = UpdateListValueData::from(['name' => 'New Value']);

    $result = (new UpdateListValue)($value, $data);

    expect($result->name)->toBe('New Value');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a list value', function () {
    Event::fake([AuditableEvent::class]);

    $value = ListValue::factory()->create();

    (new DeleteListValue)($value);

    expect(ListValue::find($value->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized list value creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $listName = ListName::factory()->create();

    $data = CreateListValueData::from([
        'list_name_id' => $listName->id,
        'name' => 'Unauthorized',
    ]);

    (new CreateListValue)($data);
})->throws(AuthorizationException::class);
