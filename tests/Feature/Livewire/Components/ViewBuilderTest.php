<?php

use App\Livewire\Components\ViewBuilder;
use App\Models\CustomView;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->user = User::factory()->owner()->create();
    actingAs($this->user);
});

it('renders the component', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->assertSet('showModal', false)
        ->assertStatus(200);
});

it('opens the modal for creating a new view', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->assertSet('showModal', true)
        ->assertSet('editingViewId', null)
        ->assertSet('name', '')
        ->assertSet('visibility', 'personal')
        ->assertSee('New Custom View');
});

it('opens the modal for editing an existing view', function () {
    $view = CustomView::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'My Test View',
        'columns' => ['name', 'email'],
        'sort_column' => 'email',
        'sort_direction' => 'desc',
        'per_page' => 48,
    ]);

    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: $view->id)
        ->assertSet('showModal', true)
        ->assertSet('editingViewId', $view->id)
        ->assertSet('name', 'My Test View')
        ->assertSet('selectedColumns', ['name', 'email'])
        ->assertSet('sortColumn', 'email')
        ->assertSet('sortDirection', 'desc')
        ->assertSet('perPage', 48)
        ->assertSee('Edit Custom View');
});

it('saves a new view', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->set('name', 'Brand New View')
        ->set('selectedColumns', ['name', 'email', 'phone'])
        ->set('sortColumn', 'name')
        ->set('sortDirection', 'asc')
        ->set('perPage', 20)
        ->call('save')
        ->assertSet('showModal', false)
        ->assertDispatched('view-saved');

    $this->assertDatabaseHas('custom_views', [
        'name' => 'Brand New View',
        'entity_type' => 'members',
        'visibility' => 'personal',
        'user_id' => $this->user->id,
    ]);
});

it('updates an existing view', function () {
    $view = CustomView::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'Old Name',
        'columns' => ['name'],
    ]);

    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: $view->id)
        ->set('name', 'Updated Name')
        ->set('selectedColumns', ['name', 'email'])
        ->call('save')
        ->assertSet('showModal', false)
        ->assertDispatched('view-saved');

    $this->assertDatabaseHas('custom_views', [
        'id' => $view->id,
        'name' => 'Updated Name',
    ]);
});

it('adds and removes columns', function () {
    $component = Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null);

    $defaults = $component->get('selectedColumns');
    $initialCount = count($defaults);

    // Add a column not in defaults
    $component->call('addColumn', 'description')
        ->assertSet('selectedColumns', [...$defaults, 'description']);

    // Remove the last column (the one we just added)
    $component->call('removeColumn', $initialCount)
        ->assertSet('selectedColumns', $defaults);
});

it('reorders columns', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->set('selectedColumns', ['name', 'email', 'phone'])
        ->call('moveDown', 0)
        ->assertSet('selectedColumns', ['email', 'name', 'phone'])
        ->call('moveUp', 2)
        ->assertSet('selectedColumns', ['email', 'phone', 'name']);
});

it('adds and removes filters', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->assertSet('filters', [])
        ->call('addFilter')
        ->assertCount('filters', 1)
        ->call('addFilter')
        ->assertCount('filters', 2)
        ->call('removeFilter', 0)
        ->assertCount('filters', 1);
});

it('closes the modal', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->assertSet('showModal', true)
        ->call('close')
        ->assertSet('showModal', false);
});

it('does not add duplicate columns', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->set('selectedColumns', ['name', 'email'])
        ->call('addColumn', 'name')
        ->assertSet('selectedColumns', ['name', 'email']);
});

it('strips empty filters on save', function () {
    Livewire::test(ViewBuilder::class, ['entityType' => 'members'])
        ->dispatch('open-view-builder', viewId: null)
        ->set('name', 'Filter Test View')
        ->set('selectedColumns', ['name'])
        ->set('filters', [
            ['field' => 'name', 'predicate' => 'cont', 'value' => 'test'],
            ['field' => '', 'predicate' => 'eq', 'value' => ''],
        ])
        ->call('save')
        ->assertDispatched('view-saved');

    $view = CustomView::where('name', 'Filter Test View')->first();
    expect($view->filters)->toHaveCount(1)
        ->and($view->filters[0]['field'])->toBe('name');
});
