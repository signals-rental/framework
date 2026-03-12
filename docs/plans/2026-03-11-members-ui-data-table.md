# Members UI & Reusable Data Table — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a CRM mega dropdown to the global nav, build a reusable interactive data table Livewire component, wire it into the members index page, and add a sticky footer.

**Architecture:** The DataTable is a full Livewire component (`App\Livewire\Components\DataTable`) that owns sorting, filtering, search, pagination, and checkbox selection state. Parent pages pass column definitions and a query closure. Row rendering, bulk actions, and row actions use named slots. Existing `s-*` CSS classes are reused for table, bulk bar, dropdown, popover, and toolbar styling.

**Tech Stack:** Livewire 4 (Volt for pages, class-based for DataTable), Alpine.js for dropdown/popover interactions, Flux UI for inputs, existing `s-*` component CSS.

---

### Task 1: Add sticky footer to layout

**Files:**
- Modify: `resources/views/components/layouts/app/header.blade.php:400-404`

**Step 1: Add footer include**

In `header.blade.php`, the main content area currently looks like:

```blade
{{-- Main content area --}}
<main class="app-main">
    {{ $slot }}
</main>
```

Change it to:

```blade
{{-- Main content area --}}
<main class="app-main">
    {{ $slot }}
    @include('components.layouts.app.footer')
</main>
```

This puts the footer inside `app-main` (which has `overflow-y: auto`), and the footer CSS already has `position: sticky; bottom: 0`.

**Step 2: Verify visually**

Run: `composer dev` (or `npm run dev`)
Visit any page and confirm the footer appears at the bottom of the content area.

**Step 3: Commit**

```
feat: add sticky footer to app layout
```

---

### Task 2: Add CRM mega dropdown to header

**Files:**
- Modify: `resources/views/components/layouts/app/header.blade.php:95-96` (desktop nav)
- Modify: `resources/views/components/layouts/app/header.blade.php:242-245` (mobile sidebar CRM section)

**Step 1: Replace the desktop CRM link (line 95)**

Replace this single line:

```blade
<a href="{{ route('members.index') }}" class="header-nav-item {{ request()->routeIs('members.*') ? 'active' : '' }}" wire:navigate>CRM</a>
```

With a mega dropdown matching the Operations pattern:

```blade
{{-- CRM mega dropdown --}}
<div class="nav-dropdown-wrapper">
    <button class="header-nav-item {{ request()->routeIs('members.*') ? 'active' : '' }}" type="button">
        CRM
        <svg class="caret" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
    </button>
    <div class="mega-dropdown">
        <div class="grid grid-cols-2 gap-x-8 gap-y-5">
            {{-- Column 1: People & Places --}}
            <div>
                <div class="mega-group-label">People &amp; Places</div>
                <a href="{{ route('members.index') }}" class="mega-item" wire:navigate>
                    <flux:icon.user-group class="mega-item-icon" />
                    <div class="flex flex-col gap-px">
                        <span class="mega-item-label">Members</span>
                        <span class="mega-item-desc">All contacts, companies &amp; venues</span>
                    </div>
                </a>
                <a href="{{ route('members.index', ['type' => 'organisation']) }}" class="mega-item" wire:navigate>
                    <flux:icon.building-office-2 class="mega-item-icon" />
                    <div class="flex flex-col gap-px">
                        <span class="mega-item-label">Organisations</span>
                        <span class="mega-item-desc">Companies &amp; businesses</span>
                    </div>
                </a>
                <a href="{{ route('members.index', ['type' => 'venue']) }}" class="mega-item" wire:navigate>
                    <flux:icon.map-pin class="mega-item-icon" />
                    <div class="flex flex-col gap-px">
                        <span class="mega-item-label">Venues</span>
                        <span class="mega-item-desc">Venues &amp; locations</span>
                    </div>
                </a>
                <a href="{{ route('members.index', ['type' => 'contact']) }}" class="mega-item" wire:navigate>
                    <flux:icon.user class="mega-item-icon" />
                    <div class="flex flex-col gap-px">
                        <span class="mega-item-label">Contacts</span>
                        <span class="mega-item-desc">Individual people</span>
                    </div>
                </a>
            </div>
            {{-- Column 2: Engagement --}}
            <div>
                <div class="mega-group-label">Engagement</div>
                <a href="#" class="mega-item">
                    <flux:icon.calendar-days class="mega-item-icon" />
                    <div class="flex flex-col gap-px">
                        <span class="mega-item-label">Activities</span>
                        <span class="mega-item-desc">Tasks, calls &amp; follow-ups</span>
                    </div>
                </a>
                <a href="#" class="mega-item">
                    <flux:icon.folder class="mega-item-icon" />
                    <div class="flex flex-col gap-px">
                        <span class="mega-item-label">Projects</span>
                        <span class="mega-item-desc">Project management</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
```

**Step 2: Update the mobile sidebar CRM section (lines 242-245)**

Replace the existing CRM items:

```blade
<a class="sidebar-item {{ request()->routeIs('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}" wire:navigate x-on:click="mobileNav = false"><flux:icon.user-group class="!size-[15px]" /> Members</a>
<a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.calendar-days class="!size-[15px]" /> Activities</a>
<a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.folder class="!size-[15px]" /> Projects</a>
```

With:

```blade
<a class="sidebar-item {{ request()->routeIs('members.*') && !request()->has('type') ? 'active' : '' }}" href="{{ route('members.index') }}" wire:navigate x-on:click="mobileNav = false"><flux:icon.user-group class="!size-[15px]" /> Members</a>
<a class="sidebar-item pl-10 text-[11px] {{ request()->routeIs('members.*') && request('type') === 'organisation' ? 'active' : '' }}" href="{{ route('members.index', ['type' => 'organisation']) }}" wire:navigate x-on:click="mobileNav = false">Organisations</a>
<a class="sidebar-item pl-10 text-[11px] {{ request()->routeIs('members.*') && request('type') === 'venue' ? 'active' : '' }}" href="{{ route('members.index', ['type' => 'venue']) }}" wire:navigate x-on:click="mobileNav = false">Venues</a>
<a class="sidebar-item pl-10 text-[11px] {{ request()->routeIs('members.*') && request('type') === 'contact' ? 'active' : '' }}" href="{{ route('members.index', ['type' => 'contact']) }}" wire:navigate x-on:click="mobileNav = false">Contacts</a>
<a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.calendar-days class="!size-[15px]" /> Activities</a>
<a class="sidebar-item" href="#" x-on:click="mobileNav = false"><flux:icon.folder class="!size-[15px]" /> Projects</a>
```

**Step 3: Update the desktop sidebar CRM section (lines 371-386)**

Same pattern — add Organisations, Venues, Contacts as indented sub-items under Members:

Replace:

```blade
<div class="sidebar-group-label">CRM</div>

<a class="sidebar-item {{ request()->routeIs('members.*') ? 'active' : '' }}" href="{{ route('members.index') }}" wire:navigate>
    <flux:icon.user-group class="!size-[15px]" />
    Members
</a>

<a class="sidebar-item" href="#">
    <flux:icon.calendar-days class="!size-[15px]" />
    Activities
</a>

<a class="sidebar-item" href="#">
    <flux:icon.folder class="!size-[15px]" />
    Projects
</a>
```

With:

```blade
<div class="sidebar-group-label">CRM</div>

<a class="sidebar-item {{ request()->routeIs('members.*') && !request()->has('type') ? 'active' : '' }}" href="{{ route('members.index') }}" wire:navigate>
    <flux:icon.user-group class="!size-[15px]" />
    Members
</a>
<a class="sidebar-item pl-10 text-[11px] {{ request()->routeIs('members.*') && request('type') === 'organisation' ? 'active' : '' }}" href="{{ route('members.index', ['type' => 'organisation']) }}" wire:navigate>
    Organisations
</a>
<a class="sidebar-item pl-10 text-[11px] {{ request()->routeIs('members.*') && request('type') === 'venue' ? 'active' : '' }}" href="{{ route('members.index', ['type' => 'venue']) }}" wire:navigate>
    Venues
</a>
<a class="sidebar-item pl-10 text-[11px] {{ request()->routeIs('members.*') && request('type') === 'contact' ? 'active' : '' }}" href="{{ route('members.index', ['type' => 'contact']) }}" wire:navigate>
    Contacts
</a>

<a class="sidebar-item" href="#">
    <flux:icon.calendar-days class="!size-[15px]" />
    Activities
</a>

<a class="sidebar-item" href="#">
    <flux:icon.folder class="!size-[15px]" />
    Projects
</a>
```

**Step 4: Verify visually**

Check the desktop header dropdown opens like Operations, check mobile sidebar shows indented sub-items, check active states highlight correctly.

**Step 5: Commit**

```
feat: add CRM mega dropdown with member type sub-links
```

---

### Task 3: Create DataTable Livewire component

**Files:**
- Create: `app/Livewire/Components/DataTable.php`
- Create: `resources/views/livewire/components/data-table.blade.php`

**Step 1: Create the Livewire component class**

Run: `php artisan make:livewire Components/DataTable --no-interaction`

Then replace the generated class with:

```php
<?php

namespace App\Livewire\Components;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class DataTable extends Component
{
    use WithPagination;

    // ── Config (set via mount) ──────────────────────────────
    /** @var array<int, array{key: string, label?: string, type?: string, sortable?: bool, filterable?: bool, filter_type?: string, filter_options?: array<string, string>}> */
    public array $columns = [];

    /** @var string[] */
    public array $searchableColumns = [];

    public int $perPage = 25;

    public string $emptyMessage = 'No results found.';

    public string $modelClass = '';

    /** @var string[] */
    public array $with = [];

    /** @var array<string, string> */
    public array $withCounts = [];

    /** @var string[] */
    public array $scopes = [];

    // ── Interactive state ───────────────────────────────────

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'sort')]
    public string $sortField = '';

    #[Url(as: 'dir')]
    public string $sortDirection = 'asc';

    /** @var array<string, string> */
    #[Url(as: 'filter')]
    public array $filters = [];

    /** @var int[] */
    public array $selected = [];

    public bool $selectAll = false;

    // ── Lifecycle ───────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  string[]  $searchable
     * @param  string[]  $with
     * @param  string[]  $withCounts
     * @param  array<string, string>  $scopes
     */
    public function mount(
        array $columns,
        string $model,
        array $searchable = [],
        int $perPage = 25,
        string $emptyMessage = 'No results found.',
        string $defaultSort = '',
        string $defaultDirection = 'asc',
        array $with = [],
        array $withCounts = [],
        array $scopes = [],
    ): void {
        $this->columns = $columns;
        $this->modelClass = $model;
        $this->searchableColumns = $searchable;
        $this->perPage = $perPage;
        $this->emptyMessage = $emptyMessage;
        $this->with = $with;
        $this->withCounts = $withCounts;
        $this->scopes = $scopes;

        if ($this->sortField === '' && $defaultSort !== '') {
            $this->sortField = $defaultSort;
            $this->sortDirection = $defaultDirection;
        }
    }

    // ── Actions ─────────────────────────────────────────────

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function applyFilter(string $column, string $value): void
    {
        if ($value === '') {
            unset($this->filters[$column]);
        } else {
            $this->filters[$column] = $value;
        }
        $this->resetPage();
    }

    public function clearFilter(string $column): void
    {
        unset($this->filters[$column]);
        $this->resetPage();
    }

    public function clearAllFilters(): void
    {
        $this->filters = [];
        $this->search = '';
        $this->resetPage();
    }

    public function toggleSelectAll(): void
    {
        $this->selectAll = ! $this->selectAll;

        if (! $this->selectAll) {
            $this->selected = [];
        }
        // When selectAll is true, we mark all current page items in render
    }

    public function toggleSelected(int $id): void
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));
            $this->selectAll = false;
        } else {
            $this->selected[] = $id;
        }
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    // ── Query builder ───────────────────────────────────────

    protected function buildQuery(): Builder
    {
        $model = new $this->modelClass;
        $query = $model->newQuery();

        // Eager loads
        if ($this->with) {
            $query->with($this->with);
        }
        if ($this->withCounts) {
            $query->withCount($this->withCounts);
        }

        // Scopes (key => value pairs, e.g. ['ofType' => 'organisation'])
        foreach ($this->scopes as $scope => $value) {
            if ($value !== '' && $value !== null) {
                $query->{$scope}($value);
            }
        }

        // Global search
        if ($this->search !== '' && count($this->searchableColumns) > 0) {
            $query->where(function (Builder $q) {
                foreach ($this->searchableColumns as $col) {
                    $q->orWhere($col, 'ilike', "%{$this->search}%");
                }
            });
        }

        // Column filters
        foreach ($this->filters as $column => $value) {
            if ($value === '') {
                continue;
            }
            $colDef = collect($this->columns)->firstWhere('key', $column);
            $filterType = $colDef['filter_type'] ?? 'text';

            if ($filterType === 'select') {
                $query->where($column, $value);
            } else {
                $query->where($column, 'ilike', "%{$value}%");
            }
        }

        // Sorting
        if ($this->sortField !== '') {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        return $query;
    }

    // ── Render ───────────────────────────────────────────────

    public function render(): \Illuminate\Contracts\View\View
    {
        $items = $this->buildQuery()->paginate($this->perPage);

        // Auto-select all on current page when selectAll is toggled
        if ($this->selectAll) {
            $this->selected = $items->pluck('id')->all();
        }

        return view('livewire.components.data-table', [
            'items' => $items,
        ]);
    }
}
```

**Step 2: Create the Blade template**

Create `resources/views/livewire/components/data-table.blade.php`:

```blade
<div>
    {{-- Toolbar: search + active filter count --}}
    <x-signals.toolbar class="mb-3">
        @if(count($searchableColumns) > 0)
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search..."
                class="w-64"
                icon="magnifying-glass"
            />
        @endif
        @if(count($filters) > 0)
            <button wire:click="clearAllFilters" class="s-btn s-btn-ghost s-btn-sm">
                Clear filters ({{ count($filters) }})
            </button>
        @endif
        <x-slot:right>
            {{ $toolbarRight ?? '' }}
        </x-slot:right>
    </x-signals.toolbar>

    {{-- Bulk action bar --}}
    @if(count($selected) > 0)
        <x-signals.bulk-bar :count="count($selected)">
            {{ $bulkActions ?? '' }}
            <button wire:click="clearSelection" class="s-bulk-clear">Clear</button>
        </x-signals.bulk-bar>
    @endif

    {{-- Table --}}
    <div class="s-table-wrap" wire:loading.class="opacity-50">
        <table class="s-table">
            <thead>
                <tr>
                    @foreach($columns as $col)
                        @if(($col['type'] ?? null) === 'checkbox')
                            <th class="s-col-check">
                                <input type="checkbox"
                                       wire:click="toggleSelectAll"
                                       @checked($selectAll)
                                       class="rounded border-zinc-300" />
                            </th>
                        @elseif(($col['type'] ?? null) === 'actions')
                            <th class="w-[50px]"></th>
                        @else
                            <th class="{{ ($col['sortable'] ?? false) ? 'sortable' : '' }} {{ $sortField === $col['key'] ? ($sortDirection === 'asc' ? 'sort-asc' : 'sort-desc') : '' }}">
                                <div class="flex items-center gap-1">
                                    @if($col['sortable'] ?? false)
                                        <button wire:click="sortBy('{{ $col['key'] }}')" class="flex items-center gap-1 hover:text-[var(--text-primary)]">
                                            {{ $col['label'] ?? $col['key'] }}
                                            <span class="s-sort-icon">
                                                @if($sortField === $col['key'])
                                                    @if($sortDirection === 'asc')
                                                        <svg class="w-3 h-3" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4l4 5H4z"/></svg>
                                                    @else
                                                        <svg class="w-3 h-3" viewBox="0 0 16 16" fill="currentColor"><path d="M8 12l4-5H4z"/></svg>
                                                    @endif
                                                @else
                                                    <svg class="w-3 h-3 opacity-30" viewBox="0 0 16 16" fill="currentColor"><path d="M8 4l3 4H5zM8 12l3-4H5z"/></svg>
                                                @endif
                                            </span>
                                        </button>
                                    @else
                                        {{ $col['label'] ?? $col['key'] }}
                                    @endif

                                    @if($col['filterable'] ?? false)
                                        <x-signals.popover position="bottom">
                                            <x-slot:trigger>
                                                <button class="s-toolbar-btn !w-5 !h-5 {{ isset($filters[$col['key']]) ? 'text-[var(--green)]' : '' }}">
                                                    <svg class="w-3 h-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 2h14l-5 6v5l-4 2V8z"/></svg>
                                                </button>
                                            </x-slot:trigger>
                                            <div class="flex flex-col gap-2">
                                                @if(($col['filter_type'] ?? 'text') === 'select')
                                                    <select
                                                        wire:change="applyFilter('{{ $col['key'] }}', $event.target.value)"
                                                        class="w-full border border-[var(--s-border-sub)] bg-[var(--card-bg)] px-2 py-1.5 text-xs"
                                                    >
                                                        <option value="">All</option>
                                                        @foreach($col['filter_options'] ?? [] as $value => $label)
                                                            <option value="{{ $value }}" @selected(($filters[$col['key']] ?? '') === (string) $value)>
                                                                {{ $label }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <flux:input
                                                        size="sm"
                                                        placeholder="Filter {{ strtolower($col['label'] ?? $col['key']) }}..."
                                                        wire:keydown.enter="applyFilter('{{ $col['key'] }}', $event.target.value)"
                                                        value="{{ $filters[$col['key']] ?? '' }}"
                                                    />
                                                @endif
                                                @if(isset($filters[$col['key']]))
                                                    <button wire:click="clearFilter('{{ $col['key'] }}')" class="text-[11px] text-[var(--red)] hover:underline self-start">
                                                        Clear
                                                    </button>
                                                @endif
                                            </div>
                                        </x-signals.popover>
                                    @endif
                                </div>
                            </th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr wire:key="dt-row-{{ $item->id }}" class="{{ in_array($item->id, $selected) ? 'selected' : '' }}">
                        @foreach($columns as $col)
                            @if(($col['type'] ?? null) === 'checkbox')
                                <td class="s-col-check">
                                    <input type="checkbox"
                                           wire:click="toggleSelected({{ $item->id }})"
                                           @checked(in_array($item->id, $selected))
                                           class="rounded border-zinc-300" />
                                </td>
                            @elseif(($col['type'] ?? null) === 'actions')
                                <td class="text-right">
                                    <div x-data="{ open: false }" class="relative inline-flex">
                                        <button x-on:click="open = !open" class="s-btn-ghost s-btn-xs s-btn-icon">
                                            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="currentColor">
                                                <circle cx="8" cy="3" r="1.5"/>
                                                <circle cx="8" cy="8" r="1.5"/>
                                                <circle cx="8" cy="13" r="1.5"/>
                                            </svg>
                                        </button>
                                        <x-signals.dropdown align="right" x-show="open" x-on:click.outside="open = false" x-cloak>
                                            {{ $actions($item) }}
                                        </x-signals.dropdown>
                                    </div>
                                </td>
                            @else
                                <td>{{ ${'column_' . $col['key']}($item) }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="text-center text-[var(--text-muted)] py-8">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $items->links() }}
    </div>
</div>
```

**Step 3: Run format check**

Run: `vendor/bin/pint --dirty --format agent`

**Step 4: Commit**

```
feat: add reusable DataTable Livewire component
```

---

### Task 4: Write DataTable component tests

**Files:**
- Create: `tests/Feature/Livewire/Components/DataTableTest.php`

**Step 1: Create the test file**

Run: `php artisan make:test --pest Livewire/Components/DataTableTest --no-interaction`

Write tests covering: rendering, sorting, searching, filtering, selection, pagination.

Use the `Member` model as the test subject since it already has factories.

```php
<?php

use App\Enums\MembershipType;
use App\Livewire\Components\DataTable;
use App\Models\Member;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = \App\Models\User::factory()->create();
    actingAs($this->user);
});

function memberColumns(): array
{
    return [
        ['key' => 'checkbox', 'type' => 'checkbox'],
        ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
        ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['contact' => 'Contact', 'organisation' => 'Organisation', 'venue' => 'Venue']],
        ['key' => 'is_active', 'label' => 'Status', 'sortable' => true],
        ['key' => 'actions', 'type' => 'actions'],
    ];
}

it('renders the data table with items', function () {
    Member::factory()->count(3)->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->assertStatus(200)
        ->assertViewHas('items', fn ($items) => $items->count() === 3);
});

it('sorts by column ascending and descending', function () {
    Member::factory()->create(['name' => 'Zebra Corp']);
    Member::factory()->create(['name' => 'Alpha Inc']);

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('sortBy', 'name');
    $items = $component->viewData('items');
    expect($items->first()->name)->toBe('Alpha Inc');

    $component->call('sortBy', 'name');
    $items = $component->viewData('items');
    expect($items->first()->name)->toBe('Zebra Corp');
});

it('searches across searchable columns', function () {
    Member::factory()->create(['name' => 'Acme Events']);
    Member::factory()->create(['name' => 'Beta Sound']);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->set('search', 'acme')
        ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->first()->name === 'Acme Events');
});

it('filters by select column', function () {
    Member::factory()->create(['membership_type' => MembershipType::Contact]);
    Member::factory()->create(['membership_type' => MembershipType::Organisation]);
    Member::factory()->create(['membership_type' => MembershipType::Organisation]);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'membership_type', 'organisation')
        ->assertViewHas('items', fn ($items) => $items->count() === 2);
});

it('clears a single filter', function () {
    Member::factory()->count(3)->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('applyFilter', 'membership_type', 'contact')
        ->call('clearFilter', 'membership_type')
        ->assertViewHas('items', fn ($items) => $items->count() === 3);
});

it('clears all filters and search', function () {
    Member::factory()->count(3)->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'searchable' => ['name'],
    ])
        ->set('search', 'xyz')
        ->call('applyFilter', 'membership_type', 'contact')
        ->call('clearAllFilters')
        ->assertSet('search', '')
        ->assertSet('filters', [])
        ->assertViewHas('items', fn ($items) => $items->count() === 3);
});

it('toggles individual row selection', function () {
    $member = Member::factory()->create();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ]);

    $component->call('toggleSelected', $member->id)
        ->assertSet('selected', [$member->id]);

    $component->call('toggleSelected', $member->id)
        ->assertSet('selected', []);
});

it('toggles select all on current page', function () {
    Member::factory()->count(3)->create();

    $component = Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'perPage' => 25,
    ]);

    $component->call('toggleSelectAll')
        ->assertSet('selectAll', true);

    $selectedCount = count($component->get('selected'));
    expect($selectedCount)->toBe(3);
});

it('clears selection', function () {
    $member = Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
    ])
        ->call('toggleSelected', $member->id)
        ->call('clearSelection')
        ->assertSet('selected', [])
        ->assertSet('selectAll', false);
});

it('paginates results', function () {
    Member::factory()->count(30)->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'perPage' => 10,
    ])
        ->assertViewHas('items', fn ($items) => $items->count() === 10 && $items->total() === 30);
});

it('applies default sort', function () {
    Member::factory()->create(['name' => 'Zebra']);
    Member::factory()->create(['name' => 'Alpha']);

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'defaultSort' => 'name',
        'defaultDirection' => 'asc',
    ])
        ->assertViewHas('items', fn ($items) => $items->first()->name === 'Alpha');
});

it('applies eager loading via with parameter', function () {
    Member::factory()->create();

    Livewire::test(DataTable::class, [
        'columns' => memberColumns(),
        'model' => Member::class,
        'with' => ['emails', 'phones'],
    ])
        ->assertStatus(200);
});
```

**Step 2: Run the tests**

Run: `php artisan test --compact tests/Feature/Livewire/Components/DataTableTest.php`

Expected: All tests pass.

**Step 3: Commit**

```
test: add DataTable component tests
```

---

### Task 5: Wire DataTable into members index page

**Files:**
- Modify: `resources/views/livewire/members/index.blade.php`

**Step 1: Rewrite members index to use DataTable**

The members index page currently has its own search, filter chips, and table. Replace the table and search with a `<livewire:components.data-table>` invocation, keeping the filter chips above.

Replace the entire file with:

```blade
<?php

use App\Enums\MembershipType;
use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Url(as: 'type')]
    public string $typeFilter = '';

    public function mount(): void
    {
        // Read from query string on initial load
        $type = request()->query('type', '');
        if ($type !== '' && MembershipType::tryFrom($type)) {
            $this->typeFilter = $type;
        }
    }

    public function setTypeFilter(string $type): void
    {
        $this->typeFilter = $type;
    }

    public function deleteMember(int $memberId): void
    {
        $member = Member::findOrFail($memberId);
        $member->delete();
    }

    public function deleteSelected(array $ids): void
    {
        Member::whereIn('id', $ids)->delete();
    }

    public function with(): array
    {
        return [
            'membershipTypes' => MembershipType::cases(),
            'columns' => [
                ['key' => 'checkbox', 'type' => 'checkbox'],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'filterable' => true, 'filter_type' => 'text'],
                ['key' => 'membership_type', 'label' => 'Type', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => collect(MembershipType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()],
                ['key' => 'is_active', 'label' => 'Status', 'sortable' => true, 'filterable' => true, 'filter_type' => 'select', 'filter_options' => ['1' => 'Active', '0' => 'Inactive']],
                ['key' => 'actions', 'type' => 'actions'],
            ],
            'scopes' => $this->typeFilter !== '' ? ['ofType' => MembershipType::from($this->typeFilter)] : [],
        ];
    }
}; ?>

<section class="w-full">
    <x-signals.page-header title="Members">
        <x-slot:actions>
            <flux:button variant="primary" href="{{ route('members.create') }}" wire:navigate>
                Add Member
            </flux:button>
        </x-slot:actions>
    </x-signals.page-header>

    <div class="flex-1 p-8 max-md:p-5 max-sm:p-3">
        {{-- Type filter chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-1">
            <button wire:click="setTypeFilter('')"
                    class="s-chip {{ $typeFilter === '' ? 's-chip-active' : '' }}">All</button>
            @foreach($membershipTypes as $type)
                <button wire:click="setTypeFilter('{{ $type->value }}')"
                        class="s-chip {{ $typeFilter === $type->value ? 's-chip-active' : '' }}">
                    {{ $type->label() }}
                </button>
            @endforeach
        </div>

        {{-- Data table --}}
        <livewire:components.data-table
            :columns="$columns"
            model="{{ \App\Models\Member::class }}"
            :searchable="['name']"
            :with="['emails', 'phones']"
            :with-counts="['addresses', 'emails', 'phones', 'links']"
            :scopes="$scopes"
            default-sort="name"
            empty-message="No members found."
            :key="'members-table-' . $typeFilter"
        >
            {{-- Custom column renderers --}}
            <x-slot:column_name="['item' => $item]">
                <a href="{{ route('members.show', $item) }}" wire:navigate class="s-cell-link font-medium">
                    {{ $item->name }}
                </a>
            </x-slot:column_name>

            <x-slot:column_membership_type="['item' => $item]">
                <span class="s-badge s-badge-blue">{{ $item->membership_type->label() }}</span>
            </x-slot:column_membership_type>

            <x-slot:column_is_active="['item' => $item]">
                @if($item->is_active)
                    <span class="s-badge s-badge-green">Active</span>
                @else
                    <span class="s-badge s-badge-zinc">Inactive</span>
                @endif
            </x-slot:column_is_active>

            {{-- Row actions menu --}}
            <x-slot:actions="['item' => $item]">
                <a href="{{ route('members.show', $item) }}" wire:navigate class="s-dropdown-item">
                    <flux:icon.eye class="!size-3.5" /> View
                </a>
                <a href="{{ route('members.edit', $item) }}" wire:navigate class="s-dropdown-item">
                    <flux:icon.pencil-square class="!size-3.5" /> Edit
                </a>
                <div class="s-dropdown-sep"></div>
                <button wire:click="$parent.deleteMember({{ $item->id }})"
                        wire:confirm="Are you sure you want to delete this member?"
                        class="s-dropdown-item s-dropdown-item-danger">
                    <flux:icon.trash class="!size-3.5" /> Delete
                </button>
            </x-slot:actions>

            {{-- Bulk actions --}}
            <x-slot:bulkActions>
                <button wire:click="$parent.deleteSelected($wire.selected)"
                        wire:confirm="Are you sure you want to delete the selected members?"
                        class="s-bulk-btn s-bulk-btn-danger">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 4h12M5.33 4V2.67a1.33 1.33 0 011.34-1.34h2.66a1.33 1.33 0 011.34 1.34V4m2 0v9.33a1.33 1.33 0 01-1.34 1.34H4.67a1.33 1.33 0 01-1.34-1.34V4h9.34z"/></svg>
                    Delete
                </button>
                <button class="s-bulk-btn" disabled>
                    Export
                </button>
            </x-slot:bulkActions>
        </livewire:components.data-table>
    </div>
</section>
```

**Important note on slot-based rendering:** The DataTable template uses `${'column_' . $col['key']}($item)` to invoke named slots. This is the Livewire/Blade pattern for dynamic slot invocation. However, this exact approach may need adjustment — an alternative is to use `@aware` or pass render closures. During implementation, test the slot rendering and adjust if needed. The simplest fallback is to keep column rendering in the DataTable blade directly but make it overridable via slots.

**Step 2: Run existing members tests to ensure nothing breaks**

Run: `php artisan test --compact tests/Feature/Livewire/Members/`

**Step 3: Run format check**

Run: `vendor/bin/pint --dirty --format agent`

**Step 4: Commit**

```
feat: wire DataTable into members index with type filter chips
```

---

### Task 6: Add CSS for new data table elements

**Files:**
- Modify: `resources/css/components.css` (only if needed)

**Step 1: Check if new CSS is needed**

The existing `s-table`, `s-bulk-bar`, `s-dropdown`, `s-popover`, `s-toolbar`, `s-chip` classes should cover all UI needs. Check during implementation if any small additions are needed (e.g. for the dropdown-item-danger variant).

Check existing CSS for `s-dropdown-item-danger`:

If missing, add after `.s-dropdown-item:hover`:

```css
.s-dropdown-item-danger { color: var(--red); }
.s-dropdown-item-danger:hover { background: var(--s-red-bg); }
```

**Step 2: Commit (only if CSS was added)**

```
style: add dropdown danger variant for row action menus
```

---

### Task 7: Update members tests for new page structure

**Files:**
- Modify: `tests/Feature/Livewire/Members/` (existing tests)

**Step 1: Update any tests that assert against the old table structure**

Tests may need to account for the DataTable child component. Livewire tests for the index page should verify:
- The page renders with the DataTable component
- Type filter chips work (setting `typeFilter` property)
- URL-based type filtering works (visiting `/members?type=organisation`)
- Delete action works via `$parent.deleteMember()`

**Step 2: Run all tests**

Run: `php artisan test --compact tests/Feature/Livewire/Members/`

**Step 3: Commit**

```
test: update members index tests for DataTable integration
```

---

### Task 8: Final quality checks

**Step 1: Run full test suite for affected areas**

Run: `php artisan test --compact tests/Feature/Livewire/`

**Step 2: Run format check**

Run: `vendor/bin/pint --dirty --format agent`

**Step 3: Run static analysis**

Run: `vendor/bin/phpstan analyse`

**Step 4: Fix any issues and re-run**

**Step 5: Commit any fixes**

```
chore: fix formatting and static analysis issues
```

---

## Implementation Notes

### Slot-Based Column Rendering

The DataTable component needs to render custom column content defined by the parent page. Livewire 4 supports named slots with parameters. The key challenge is making dynamic slot names work (`column_{key}`). If this proves problematic, alternatives:

1. **Render callbacks via public properties** — pass closures (not serializable in Livewire)
2. **Blade `@includeWhen` with partials** — the parent provides partial blade paths per column
3. **Simple approach: keep rendering in DataTable blade** — use `@switch($col['key'])` blocks. Less reusable but simpler. This is the recommended fallback.

The most pragmatic approach may be option 3 for v1, then refactor to slots when the pattern stabilises across more entities.

### Scopes Integration

The DataTable accepts a `scopes` array like `['ofType' => 'organisation']`. This maps directly to Eloquent local scopes on the model. The parent page is responsible for constructing this array based on its filter state, and passes it to DataTable. When scopes change, the parent must use `:key` to re-mount the DataTable.

### URL State

Sort, search, and filters are synced to the URL via `#[Url]` attributes on the DataTable component. The parent's `typeFilter` is also URL-synced. This means the full table state is bookmarkable and shareable.
