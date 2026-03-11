<?php

use App\Actions\ListValues\CreateListName;
use App\Actions\ListValues\DeleteListName;
use App\Actions\ListValues\UpdateListName;
use App\Data\ListValues\CreateListNameData;
use App\Data\ListValues\UpdateListNameData;
use App\Events\AuditableEvent;
use App\Models\ListName;
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

it('creates a list name', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateListNameData::from([
        'name' => 'Phone Type',
        'description' => 'Types of phone numbers',
    ]);

    $result = (new CreateListName)($data);

    expect($result->name)->toBe('Phone Type');
    expect($result->description)->toBe('Types of phone numbers');
    expect(ListName::where('name', 'Phone Type')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a list name', function () {
    Event::fake([AuditableEvent::class]);

    $listName = ListName::factory()->create(['name' => 'Old List']);

    $data = UpdateListNameData::from(['name' => 'New List']);

    $result = (new UpdateListName)($listName, $data);

    expect($result->name)->toBe('New List');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a list name', function () {
    Event::fake([AuditableEvent::class]);

    $listName = ListName::factory()->create();

    (new DeleteListName)($listName);

    expect(ListName::find($listName->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized list name creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateListNameData::from(['name' => 'Unauthorized List']);

    (new CreateListName)($data);
})->throws(AuthorizationException::class);
