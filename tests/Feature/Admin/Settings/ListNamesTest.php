<?php

use App\Models\ListName;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

it('renders the list names page', function () {
    $this->get(route('admin.settings.list-names'))
        ->assertOk()
        ->assertSee('List Names');
});

it('lists list names', function () {
    ListName::factory()->create(['name' => 'Opportunity Sources']);
    ListName::factory()->create(['name' => 'Payment Terms']);

    Volt::test('admin.settings.list-names')
        ->assertSee('Opportunity Sources')
        ->assertSee('Payment Terms');
});

it('renders the create form', function () {
    $this->get(route('admin.settings.list-names.create'))
        ->assertOk()
        ->assertSee('Create List');
});

it('can create a list name', function () {
    Volt::test('admin.settings.list-name-form')
        ->set('name', 'New List')
        ->set('description', 'A test list')
        ->call('save');

    expect(ListName::where('name', 'New List')->exists())->toBeTrue();
});

it('renders the edit form', function () {
    $listName = ListName::factory()->create(['name' => 'Editable List']);

    $this->get(route('admin.settings.list-names.edit', $listName))
        ->assertOk()
        ->assertSee('Edit List');
});

it('can edit a list name', function () {
    $listName = ListName::factory()->create(['name' => 'Old List']);

    Volt::test('admin.settings.list-name-form', ['listName' => $listName])
        ->assertSet('name', 'Old List')
        ->set('name', 'Updated List')
        ->call('save');

    expect($listName->fresh()->name)->toBe('Updated List');
});

it('validates required name', function () {
    Volt::test('admin.settings.list-name-form')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('cannot delete a system list', function () {
    $systemList = ListName::factory()->system()->create(['name' => 'System List']);

    Volt::test('admin.settings.list-names')
        ->call('deleteList', $systemList->id)
        ->assertHasErrors('delete');

    expect(ListName::where('name', 'System List')->exists())->toBeTrue();
});

it('can delete a non-system list', function () {
    $list = ListName::factory()->create(['name' => 'Deletable List']);

    Volt::test('admin.settings.list-names')
        ->call('deleteList', $list->id);

    expect(ListName::where('name', 'Deletable List')->exists())->toBeFalse();
});

it('returns 403 for non-admin users', function () {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('admin.settings.list-names'))
        ->assertForbidden();
});
