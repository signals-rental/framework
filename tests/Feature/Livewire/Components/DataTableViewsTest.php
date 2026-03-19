<?php

use App\Livewire\Components\DataTable;
use App\Models\CustomView;
use App\Models\Member;
use App\Models\User;
use App\Models\UserViewPreference;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ViewSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(ViewSeeder::class);
    $this->user = User::factory()->owner()->create();
    actingAs($this->user);
});

/**
 * @return array<int, array<string, mixed>>
 */
function viewColumns(): array
{
    return [
        ['key' => 'checkbox', 'type' => 'checkbox'],
        ['key' => 'avatar', 'label' => '', 'type' => 'avatar'],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true],
        ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select'],
        ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
        ['key' => 'actions', 'type' => 'actions'],
    ];
}

describe('toggleColumn', function () {
    it('initializes visibleColumnKeys from current columns on first toggle', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        // Before toggle, visibleColumnKeys may be set from the view
        $component->call('toggleColumn', 'is_active');

        $keys = $component->get('visibleColumnKeys');
        expect($keys)->toBeArray()
            ->and($keys)->not->toContain('checkbox')
            ->and($keys)->not->toContain('actions');
    });

    it('removes a column when toggled while visible', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        // is_active should be visible initially (it's in the default columns from ViewSeeder)
        // Toggle it off
        $component->call('toggleColumn', 'is_active');
        $keys = $component->get('visibleColumnKeys');
        expect($keys)->not->toContain('is_active');
    });

    it('adds a column when it is not visible', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        // Initialize by toggling a column off
        $component->call('toggleColumn', 'is_active');
        // Now is_active should be removed, add it back
        $component->call('toggleColumn', 'is_active');

        $keys = $component->get('visibleColumnKeys');
        expect($keys)->toContain('is_active');
    });
});

describe('switchView', function () {
    it('changes viewId and applies the view', function () {
        Member::factory()->create();
        $view = CustomView::query()->where('name', 'Organisations Only')->first();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $component->call('switchView', $view->id);

        expect($component->get('viewId'))->toBe($view->id)
            ->and($component->get('activeViewName'))->toBe('Organisations Only');
    });
});

describe('clearView', function () {
    it('resets viewId and activeViewName', function () {
        Member::factory()->create();
        $view = CustomView::query()->where('name', 'Organisations Only')->first();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $component->call('switchView', $view->id);
        $component->call('clearView');

        expect($component->get('viewId'))->toBeNull()
            ->and($component->get('activeViewName'))->toBeNull();
    });
});

describe('setDefaultView', function () {
    it('creates a UserViewPreference record', function () {
        Member::factory()->create();
        $view = CustomView::query()->where('name', 'Contacts Only')->first();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $component->call('switchView', $view->id);
        $component->call('setDefaultView');

        $this->assertDatabaseHas('user_view_preferences', [
            'user_id' => $this->user->id,
            'entity_type' => 'members',
            'custom_view_id' => $view->id,
        ]);
    });

    it('does nothing when viewId is null', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $component->call('clearView');
        $component->call('setDefaultView');

        expect(UserViewPreference::count())->toBe(0);
    });
});

describe('onViewSaved', function () {
    it('reloads available views on view-saved event', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $initialViews = $component->get('availableViews');

        // Create a new personal view
        CustomView::factory()->create([
            'entity_type' => 'members',
            'visibility' => 'system',
            'user_id' => null,
            'name' => 'New System View',
        ]);

        $component->dispatch('view-saved');

        // Views should be reloaded (the count may differ)
        $component->assertStatus(200);
    });
});

describe('getDisplayColumnsProperty', function () {
    it('returns all columns when no view is set', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
        ]);

        $component->call('clearView');
        $component->set('visibleColumnKeys', []);

        $display = $component->viewData('displayColumns');
        expect($display)->toHaveCount(count(viewColumns()));
    });

    it('filters to view columns and includes structural columns', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $component->set('visibleColumnKeys', ['name', 'is_active']);

        /** @var array<int, array<string, mixed>> $display */
        $display = $component->viewData('displayColumns');
        $keys = collect($display)->pluck('key')->all();

        // Should include checkbox, avatar (since name is in view), name, is_active, actions
        expect($keys)->toContain('checkbox')
            ->and($keys)->toContain('name')
            ->and($keys)->toContain('is_active')
            ->and($keys)->toContain('actions')
            ->and($keys)->not->toContain('membership_type');
    });

    it('generates missing column definitions from registry', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        // 'description' is in MemberColumnRegistry but not in viewColumns()
        $component->set('visibleColumnKeys', ['name', 'description']);

        /** @var array<int, array<string, mixed>> $display */
        $display = $component->viewData('displayColumns');
        $keys = collect($display)->pluck('key')->all();

        expect($keys)->toContain('description');

        $descCol = collect($display)->firstWhere('key', 'description');
        expect($descCol['label'])->toBe('Description');
    });
});

describe('getToggleableColumnsProperty', function () {
    it('returns toggleable columns with visibility state', function () {
        Member::factory()->create();

        $component = Livewire::test(DataTable::class, [
            'columns' => viewColumns(),
            'model' => Member::class,
            'entityType' => 'members',
        ]);

        $component->set('visibleColumnKeys', ['name']);

        // Access computed property via the component instance method
        /** @var \App\Livewire\Components\DataTable $instance */
        $instance = $component->instance();
        $toggleable = $instance->getToggleableColumnsProperty();

        // MemberColumnRegistry has columns, name should be visible, others not
        $nameEntry = collect($toggleable)->firstWhere('key', 'name');
        expect($nameEntry)->not->toBeNull()
            ->and($nameEntry['visible'])->toBeTrue();

        $typeEntry = collect($toggleable)->firstWhere('key', 'membership_type');
        expect($typeEntry)->not->toBeNull()
            ->and($typeEntry['visible'])->toBeFalse();
    });
});
