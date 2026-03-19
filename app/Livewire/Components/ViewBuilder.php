<?php

namespace App\Livewire\Components;

use App\Actions\Views\CreateCustomView;
use App\Actions\Views\UpdateCustomView;
use App\Data\Views\CreateCustomViewData;
use App\Data\Views\UpdateCustomViewData;
use App\Models\CustomView;
use App\Views\ColumnRegistry;
use App\Views\MemberColumnRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class ViewBuilder extends Component
{
    public bool $showModal = false;

    public ?int $editingViewId = null;

    #[Locked]
    public string $entityType = 'members';

    // Form properties
    public string $name = '';

    public string $visibility = 'personal';

    /** @var list<string> */
    public array $selectedColumns = [];

    /** @var list<array{field: string, predicate: string, value: string, logic: string}> */
    public array $filters = [];

    public ?string $sortColumn = null;

    public string $sortDirection = 'asc';

    public int $perPage = 20;

    /** @var list<int> */
    public array $roleIds = [];

    #[On('open-view-builder')]
    public function open(?int $viewId = null): void
    {
        $this->editingViewId = $viewId;

        if ($viewId !== null) {
            $view = CustomView::findOrFail($viewId);
            $this->name = $view->name;
            $this->visibility = $view->visibility;
            $this->selectedColumns = $view->columns;
            $this->filters = array_map(function (array $f): array {
                return [
                    'field' => $f['field'] ?? '',
                    'predicate' => $f['predicate'] ?? 'eq',
                    'value' => (string) ($f['value'] ?? ''),
                    'logic' => $f['logic'] ?? 'and',
                ];
            }, $view->filters);
            $this->sortColumn = $view->sort_column;
            $this->sortDirection = $view->sort_direction;
            $this->perPage = $view->per_page;
            $this->roleIds = $view->roles->pluck('id')->map(fn ($id): int => (int) $id)->all();
        } else {
            $this->resetForm();
        }

        $this->showModal = true;
    }

    public function addColumn(string $key): void
    {
        if (! in_array($key, $this->selectedColumns, true)) {
            $this->selectedColumns[] = $key;
        }
    }

    public function removeColumn(int $index): void
    {
        array_splice($this->selectedColumns, $index, 1);
    }

    public function moveUp(int $index): void
    {
        if ($index > 0) {
            [$this->selectedColumns[$index - 1], $this->selectedColumns[$index]] =
                [$this->selectedColumns[$index], $this->selectedColumns[$index - 1]];
        }
    }

    public function moveDown(int $index): void
    {
        if ($index < count($this->selectedColumns) - 1) {
            [$this->selectedColumns[$index], $this->selectedColumns[$index + 1]] =
                [$this->selectedColumns[$index + 1], $this->selectedColumns[$index]];
        }
    }

    public function addFilter(): void
    {
        $this->filters[] = ['field' => '', 'predicate' => 'eq', 'value' => '', 'logic' => 'and'];
    }

    public function removeFilter(int $index): void
    {
        array_splice($this->filters, $index, 1);
    }

    public function save(): void
    {
        if ($this->editingViewId !== null) {
            $dto = UpdateCustomViewData::from([
                'name' => $this->name,
                'visibility' => $this->visibility,
                'columns' => $this->selectedColumns,
                'filters' => array_values(array_filter($this->filters, fn (array $f): bool => ! empty($f['field']))),
                'sort_column' => $this->sortColumn,
                'sort_direction' => $this->sortDirection,
                'per_page' => $this->perPage,
                'role_ids' => $this->roleIds,
            ]);
            $view = CustomView::findOrFail($this->editingViewId);
            (new UpdateCustomView)($view, $dto);
        } else {
            $dto = CreateCustomViewData::from([
                'name' => $this->name,
                'entity_type' => $this->entityType,
                'visibility' => $this->visibility,
                'columns' => $this->selectedColumns,
                'filters' => array_values(array_filter($this->filters, fn (array $f): bool => ! empty($f['field']))),
                'sort_column' => $this->sortColumn,
                'sort_direction' => $this->sortDirection,
                'per_page' => $this->perPage,
                'role_ids' => $this->roleIds,
            ]);
            (new CreateCustomView)($dto);
        }

        $this->showModal = false;
        $this->dispatch('view-saved');
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    /**
     * Get available columns from the registry (minus already selected).
     *
     * @return array<string, \App\Views\Column>
     */
    public function getAvailableColumnsProperty(): array
    {
        $registry = $this->getRegistry();
        $all = $registry->allColumns();

        return array_filter($all, fn ($col): bool => ! in_array($col->key, $this->selectedColumns, true));
    }

    /**
     * Get the column labels for selected columns.
     *
     * @return list<array{key: string, label: string}>
     */
    public function getSelectedColumnDetailsProperty(): array
    {
        $registry = $this->getRegistry();
        $details = [];

        foreach ($this->selectedColumns as $key) {
            $col = $registry->get($key);
            $details[] = ['key' => $key, 'label' => $col ? $col->label : $key];
        }

        return $details;
    }

    /**
     * Get filterable fields from the registry.
     *
     * @return list<array{key: string, label: string}>
     */
    public function getFilterableFieldsProperty(): array
    {
        $registry = $this->getRegistry();
        $fields = [];

        foreach ($registry->allColumns() as $col) {
            if ($col->filterable) {
                $fields[] = ['key' => $col->key, 'label' => $col->label];
            }
        }

        return $fields;
    }

    /**
     * Get sortable fields from the registry.
     *
     * @return list<array{key: string, label: string}>
     */
    public function getSortableFieldsProperty(): array
    {
        $registry = $this->getRegistry();
        $fields = [];

        foreach ($registry->allColumns() as $col) {
            if ($col->sortable) {
                $fields[] = ['key' => $col->key, 'label' => $col->label];
            }
        }

        return $fields;
    }

    private function getRegistry(): ColumnRegistry
    {
        // For now, only members are supported. This will be extended with a registry lookup.
        return new MemberColumnRegistry;
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->visibility = 'personal';
        $this->selectedColumns = $this->getRegistry()->defaultColumns();
        $this->filters = [];
        $this->sortColumn = 'name';
        $this->sortDirection = 'asc';
        $this->perPage = 20;
        $this->roleIds = [];
    }

    public function render(): View
    {
        return view('livewire.components.view-builder');
    }
}
