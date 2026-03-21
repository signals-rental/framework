<?php

namespace App\Livewire\Components;

use App\Models\CustomView;
use App\Models\UserViewPreference;
use App\Services\Api\RansackFilter;
use App\Services\ViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property array<int, array<string, mixed>> $displayColumns
 * @property list<array{key: string, label: string, visible: bool}> $toggleableColumns
 */
class DataTable extends Component
{
    use WithPagination;

    /** @var array<int, array<string, mixed>> */
    #[Locked]
    public array $columns = [];

    #[Locked]
    public string $model = '';

    /** @var array<int, string> */
    #[Locked]
    public array $searchable = [];

    #[Url(as: 'per_page')]
    public int $perPage = 12;

    /** @var array<int, int> */
    #[Locked]
    public array $perPageOptions = [12, 24, 48];

    #[Locked]
    public string $emptyMessage = 'No records found.';

    #[Locked]
    public string $defaultSort = '';

    #[Locked]
    public string $defaultDirection = 'asc';

    /** @var array<int, string> */
    #[Locked]
    public array $with = [];

    /** @var array<int, string> */
    #[Locked]
    public array $withCounts = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $scopes = [];

    #[Locked]
    public string $actionsView = '';

    #[Locked]
    public string $bulkActionsView = '';

    #[Locked]
    public string $toolbarView = '';

    /** @var array<int, string> */
    #[Locked]
    public array $refreshEvents = [];

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDirection = 'asc';

    /** @var array<string, string> */
    #[Url]
    public array $filters = [];

    /** @var array<int, int> */
    public array $selected = [];

    public bool $selectAll = false;

    public ?int $lastSelectedId = null;

    #[Url(as: 'view')]
    public ?int $viewId = null;

    #[Locked]
    public ?string $entityType = null;

    /** @var list<string> Column keys to display (empty = show all) */
    public array $visibleColumnKeys = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $availableViews = [];

    public ?string $activeViewName = null;

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, string>  $searchable
     * @param  array<int, string>  $with
     * @param  array<int, string>  $withCounts
     * @param  array<string, mixed>  $scopes
     * @param  array<int, string>  $refreshEvents
     */
    public function mount(
        array $columns = [],
        string $model = '',
        array $searchable = [],
        int $perPage = 12,
        string $emptyMessage = 'No records found.',
        string $defaultSort = '',
        string $defaultDirection = 'asc',
        array $with = [],
        array $withCounts = [],
        array $scopes = [],
        array $refreshEvents = [],
        ?string $entityType = null,
    ): void {
        if ($model === '' || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException('DataTable requires a valid Eloquent model class.');
        }

        $this->columns = $columns;
        $this->model = $model;
        $this->searchable = $searchable;
        $this->emptyMessage = $emptyMessage;
        $this->defaultSort = $defaultSort;
        $this->defaultDirection = $defaultDirection;
        $this->with = $with;
        $this->withCounts = $withCounts;
        $this->scopes = $scopes;
        $this->refreshEvents = $refreshEvents;

        // Validate URL-bound perPage against allowed options
        if (! in_array($this->perPage, $this->perPageOptions, true)) {
            $this->perPage = $perPage;
        }

        if ($this->sortField === '' && $this->defaultSort !== '') {
            $this->sortField = $this->defaultSort;
            $this->sortDirection = $this->defaultDirection;
        }

        $this->entityType = $entityType;

        if ($this->entityType !== null) {
            $this->loadAvailableViews();
            $this->applyActiveView();
        }
    }

    /**
     * Register configurable event listeners for refresh.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = [];

        foreach ($this->refreshEvents as $event) {
            $listeners[$event] = 'refresh';
        }

        return $listeners;
    }

    public function sortBy(string $field): void
    {
        if (! $this->isSortableColumn($field)) {
            return;
        }

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
        if (! $this->isFilterableColumn($column)) {
            return;
        }

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
    }

    public function toggleSelected(int $id): void
    {
        if (in_array($id, $this->selected)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));
            $this->lastSelectedId = null;
        } else {
            $this->selected[] = $id;
            $this->lastSelectedId = $id;
        }

        $this->selectAll = false;
    }

    /**
     * Select all rows between the last selected row and the given row.
     *
     * @param  int  $id  The shift-clicked row ID
     * @param  array<int, int>  $pageIds  All row IDs on the current page, in display order
     */
    public function shiftSelect(int $id, array $pageIds): void
    {
        if ($this->lastSelectedId === null) {
            $this->toggleSelected($id);

            return;
        }

        $lastIndex = array_search($this->lastSelectedId, $pageIds);
        $currentIndex = array_search($id, $pageIds);

        if ($lastIndex === false || $currentIndex === false) {
            $this->toggleSelected($id);

            return;
        }

        $start = min($lastIndex, $currentIndex);
        $end = max($lastIndex, $currentIndex);

        $rangeIds = array_slice($pageIds, $start, $end - $start + 1);

        foreach ($rangeIds as $rangeId) {
            if (! in_array($rangeId, $this->selected)) {
                $this->selected[] = $rangeId;
            }
        }

        $this->lastSelectedId = $id;
        $this->selectAll = false;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->lastSelectedId = null;
    }

    public function refresh(): void
    {
        $this->selected = [];
        $this->selectAll = false;
        $this->lastSelectedId = null;
    }

    public function setPerPage(int $perPage): void
    {
        if (! in_array($perPage, $this->perPageOptions)) {
            return;
        }

        $this->perPage = $perPage;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr($this->search, 0, 200);
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function buildQuery(): Builder
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $modelClass = $this->model;

        /** @var Builder<\Illuminate\Database\Eloquent\Model> $query */
        $query = $modelClass::query();

        // Eager load relationships
        if (count($this->with) > 0) {
            $query->with($this->with);
        }

        if (count($this->withCounts) > 0) {
            $query->withCount($this->withCounts);
        }

        // Apply scopes (validated against model)
        foreach ($this->scopes as $scope => $value) {
            if (! method_exists($modelClass, 'scope'.ucfirst($scope))) {
                throw new \InvalidArgumentException("DataTable: scope '{$scope}' does not exist on model {$this->model}.");
            }

            if ($value === true) {
                $query->{$scope}();
            } else {
                $query->{$scope}($value);
            }
        }

        // Apply custom view filters
        if ($this->viewId !== null && $this->entityType !== null) {
            $activeView = CustomView::find($this->viewId);
            if ($activeView instanceof CustomView) {
                app(ViewResolver::class)->applyFilters($query, $activeView);
            }
        }

        // Apply global search (capped at 200 chars, wildcards escaped)
        $search = mb_substr($this->search, 0, 200);
        if ($search !== '' && count($this->searchable) > 0) {
            $searchable = $this->searchable;
            $escapedSearch = RansackFilter::escapeLike($search);

            $query->where(function (Builder $q) use ($escapedSearch, $searchable): void {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'ilike', "%{$escapedSearch}%");
                }
            });
        }

        // Apply column filters (only for defined filterable columns)
        $filterableColumns = $this->getFilterableColumnKeys();

        foreach ($this->filters as $column => $value) {
            if ($value === '' || ! in_array($column, $filterableColumns, true)) {
                continue;
            }

            $colDef = $this->getColumnDefinition($column);
            $filterType = $colDef['filter_type'] ?? 'text';

            if ($filterType === 'select') {
                $query->where($column, '=', $value);
            } else {
                $query->where($column, 'ilike', '%'.RansackFilter::escapeLike($value).'%');
            }
        }

        // Apply sorting (only for defined sortable columns)
        if ($this->sortField !== '' && $this->isSortableColumn($this->sortField)) {
            $direction = in_array(strtolower($this->sortDirection), ['asc', 'desc'], true)
                ? $this->sortDirection
                : 'asc';
            $query->orderBy($this->sortField, $direction);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getColumnDefinition(string $key): array
    {
        foreach ($this->columns as $col) {
            if (($col['key'] ?? '') === $key) {
                return $col;
            }
        }

        return [];
    }

    protected function isSortableColumn(string $field): bool
    {
        foreach ($this->columns as $col) {
            if (($col['key'] ?? '') === $field && ($col['sortable'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    protected function isFilterableColumn(string $column): bool
    {
        return in_array($column, $this->getFilterableColumnKeys(), true);
    }

    /**
     * @return array<int, string>
     */
    protected function getFilterableColumnKeys(): array
    {
        return collect($this->columns)
            ->filter(fn (array $col): bool => $col['filterable'] ?? false)
            ->map(fn (array $col): string => $col['key'] ?? '')
            ->values()
            ->all();
    }

    /**
     * Toggle a column's visibility.
     */
    public function toggleColumn(string $key): void
    {
        // If no explicit column selection yet, start from current display
        if (empty($this->visibleColumnKeys)) {
            $this->visibleColumnKeys = collect($this->columns)
                ->reject(fn (array $c): bool => in_array($c['type'] ?? null, ['checkbox', 'actions']) || ($c['key'] ?? '') === 'avatar')
                ->pluck('key')
                ->all();
        }

        if (in_array($key, $this->visibleColumnKeys, true)) {
            $this->visibleColumnKeys = array_values(array_filter(
                $this->visibleColumnKeys,
                fn (string $k): bool => $k !== $key,
            ));
        } else {
            $this->visibleColumnKeys[] = $key;
        }
    }

    /**
     * Get all toggleable columns with their visibility state.
     *
     * @return list<array{key: string, label: string, visible: bool}>
     */
    public function getToggleableColumnsProperty(): array
    {
        $registry = $this->getColumnRegistry();
        $visibleKeys = $this->visibleColumnKeys;

        // If no explicit selection, all non-structural columns are visible
        if (empty($visibleKeys)) {
            $visibleKeys = collect($this->columns)
                ->reject(fn (array $c): bool => in_array($c['type'] ?? null, ['checkbox', 'actions']) || ($c['key'] ?? '') === 'avatar')
                ->pluck('key')
                ->all();
        }

        // Use registry if available, otherwise fall back to static columns
        if ($registry !== null) {
            $result = [];
            foreach ($registry->allColumns() as $col) {
                $result[] = [
                    'key' => $col->key,
                    'label' => $col->label,
                    'visible' => in_array($col->key, $visibleKeys, true),
                ];
            }

            return $result;
        }

        return collect($this->columns)
            ->reject(fn (array $c): bool => in_array($c['type'] ?? null, ['checkbox', 'actions']) || ($c['key'] ?? '') === 'avatar')
            ->map(fn (array $c): array => [
                'key' => $c['key'] ?? '',
                'label' => $c['label'] ?? $c['key'] ?? '',
                'visible' => in_array($c['key'] ?? '', $visibleKeys, true),
            ])
            ->values()
            ->all();
    }

    public function switchView(int $viewId): void
    {
        $this->viewId = $viewId;
        $this->resetPage();
        $this->applyActiveView();
    }

    public function clearView(): void
    {
        $this->viewId = null;
        $this->activeViewName = null;
        $this->resetPage();
    }

    #[On('view-saved')]
    public function onViewSaved(): void
    {
        $this->loadAvailableViews();
        $this->applyActiveView();
    }

    public function setDefaultView(): void
    {
        if ($this->viewId === null || $this->entityType === null) {
            return;
        }

        UserViewPreference::updateOrCreate(
            ['user_id' => auth()->id(), 'entity_type' => $this->entityType],
            ['custom_view_id' => $this->viewId],
        );
    }

    private function loadAvailableViews(): void
    {
        $user = auth()->user();
        if (! $user instanceof \App\Models\User || $this->entityType === null) {
            return;
        }

        $views = CustomView::query()
            ->forEntity($this->entityType)
            ->visibleTo($user)
            ->orderBy('visibility')
            ->orderBy('name')
            ->get();

        $this->availableViews = $views->groupBy('visibility')->map(function ($group) {
            return $group->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'is_default' => $v->is_default])->values()->all();
        })->all();
    }

    private function applyActiveView(): void
    {
        if ($this->entityType === null) {
            return;
        }

        $user = auth()->user();
        $resolver = app(ViewResolver::class);
        $view = $resolver->resolve($this->entityType, $this->viewId, $user instanceof \App\Models\User ? $user : null);

        if ($view !== null) {
            $this->viewId = $view->id;
            $this->activeViewName = $view->name;

            // Apply view columns
            if (! empty($view->columns)) {
                $this->visibleColumnKeys = $view->columns;
            }

            // Apply view sort
            if ($view->sort_column) {
                $this->sortField = $view->sort_column;
                $this->sortDirection = $view->sort_direction ?? 'asc';
            }

            // Apply view per_page
            if ($view->per_page && in_array($view->per_page, $this->perPageOptions, true)) {
                $this->perPage = $view->per_page;
            }
        } else {
            $this->visibleColumnKeys = [];
        }
    }

    /**
     * Get the columns to display, filtered by the active view's column selection.
     *
     * Always includes checkbox and actions columns. When no view columns are set,
     * shows all columns. For view columns not in the static definitions, generates
     * basic column definitions from the ColumnRegistry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDisplayColumnsProperty(): array
    {
        if (empty($this->visibleColumnKeys)) {
            return $this->columns;
        }

        // Build a map of existing column definitions by key
        $existingByKey = [];
        $structural = []; // checkbox, actions, avatar
        foreach ($this->columns as $col) {
            $type = $col['type'] ?? null;
            $key = $col['key'] ?? '';

            if ($type === 'checkbox' || $type === 'actions') {
                $structural[$type] = $col;
            } elseif ($key === 'avatar') {
                $structural['avatar'] = $col;
            } else {
                $existingByKey[$key] = $col;
            }
        }

        // Build display columns in the view's order
        $result = [];

        // Checkbox first
        if (isset($structural['checkbox'])) {
            $result[] = $structural['checkbox'];
        }

        // Avatar if name is in view
        if (isset($structural['avatar']) && in_array('name', $this->visibleColumnKeys, true)) {
            $result[] = $structural['avatar'];
        }

        // Get column registry for generating missing definitions
        $registry = $this->entityType !== null ? $this->getColumnRegistry() : null;

        // Add columns in view order
        foreach ($this->visibleColumnKeys as $key) {
            if (isset($existingByKey[$key])) {
                $result[] = $existingByKey[$key];
            } elseif ($registry !== null) {
                // Generate a basic column definition from the registry
                $regCol = $registry->get($key);
                if ($regCol !== null) {
                    $result[] = [
                        'key' => $regCol->key,
                        'label' => $regCol->label,
                        'sortable' => $regCol->sortable,
                        'filterable' => $regCol->filterable,
                    ];
                }
            }
        }

        // Actions last
        if (isset($structural['actions'])) {
            $result[] = $structural['actions'];
        }

        return $result;
    }

    /**
     * Get the column registry for the current entity type.
     */
    private function getColumnRegistry(): ?\App\Views\ColumnRegistry
    {
        return match ($this->entityType) {
            'members' => new \App\Views\MemberColumnRegistry,
            'products' => new \App\Views\ProductColumnRegistry,
            'stock_levels' => new \App\Views\StockLevelColumnRegistry,
            'activities' => new \App\Views\ActivityColumnRegistry,
            default => null,
        };
    }

    public function render(): View
    {
        $query = $this->buildQuery();

        $items = $query->paginate($this->perPage);

        // If selectAll is toggled, select all IDs on the current page
        if ($this->selectAll) {
            $this->selected = collect($items->items())
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $activeFilterCount = count(array_filter($this->filters, fn (?string $v): bool => $v !== '' && $v !== null));

        return view('livewire.components.data-table', [
            'items' => $items,
            'activeFilterCount' => $activeFilterCount,
            'totalCount' => $items->total(),
            'displayColumns' => $this->displayColumns,
        ]);
    }
}
