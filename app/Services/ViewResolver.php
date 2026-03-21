<?php

namespace App\Services;

use App\Models\CustomView;
use App\Models\User;
use App\Models\UserViewPreference;
use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Builder;

class ViewResolver
{
    /**
     * Resolve the active view for an entity type.
     *
     * Resolution order: explicit viewId → user preference → system default.
     */
    public function resolve(string $entityType, ?int $viewId, ?User $user): ?CustomView
    {
        // 1. Explicit view ID
        if ($viewId !== null) {
            $view = CustomView::query()
                ->forEntity($entityType)
                ->find($viewId);

            if ($view instanceof CustomView) {
                return $view;
            }
        }

        // 2. User preference
        if ($user !== null) {
            $preference = UserViewPreference::query()
                ->where('user_id', $user->id)
                ->where('entity_type', $entityType)
                ->first();

            if ($preference !== null) {
                $view = CustomView::find($preference->custom_view_id);

                if ($view instanceof CustomView) {
                    return $view;
                }
            }
        }

        // 3. System default
        return CustomView::query()
            ->forEntity($entityType)
            ->systemDefaults()
            ->first();
    }

    /**
     * Apply a view's filters to a query, merging with explicit request params.
     *
     * Each filter has a `logic` property (and, or, nand, nor) that describes
     * how it connects to the previous filter. The first filter's logic is ignored.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $explicitParams
     * @return Builder<TModel>
     */
    public function applyFilters(Builder $query, CustomView $view, array $explicitParams = []): Builder
    {
        /** @var array<int, array<string, mixed>> $viewFilters */
        $viewFilters = $view->filters ?? [];
        $ransack = app(RansackFilter::class);

        // Apply view filters with per-filter logic
        $validFilters = [];
        foreach ($viewFilters as $filter) {
            $field = $filter['field'] ?? null;
            $predicate = $filter['predicate'] ?? 'eq';
            $value = $filter['value'] ?? null;
            $logic = $filter['logic'] ?? 'and';

            if ($field === null || $value === null) {
                continue;
            }

            $key = "{$field}_{$predicate}";

            // Skip if explicitly overridden
            if (isset($explicitParams[$key])) {
                continue;
            }

            $validFilters[] = [
                'key' => $key,
                'value' => $value,
                'logic' => $logic,
                'field' => $field,
            ];
        }

        if (! empty($validFilters)) {
            $query->where(function (Builder $outer) use ($ransack, $validFilters) {
                foreach ($validFilters as $i => $filter) {
                    $allowedFields = [$filter['field']];
                    $params = [$filter['key'] => $filter['value']];

                    if ($i === 0) {
                        // First filter always applied as a where
                        $ransack->apply($outer, $params, $allowedFields);
                    } else {
                        $logic = $filter['logic'];
                        $applyFilter = function (Builder $q) use ($ransack, $params, $allowedFields): void {
                            $ransack->apply($q, $params, $allowedFields);
                        };

                        match ($logic) {
                            'or' => $outer->orWhere($applyFilter),
                            'nand' => $outer->whereNot($applyFilter),
                            'nor' => $outer->orWhereNot($applyFilter),
                            default => $outer->where($applyFilter), // 'and'
                        };
                    }
                }
            });
        }

        // Explicit params are always applied as AND conditions
        if (! empty($explicitParams)) {
            $explicitFields = $this->extractFieldNames(array_keys($explicitParams));
            $ransack->apply($query, $explicitParams, $explicitFields);
        }

        return $query;
    }

    /**
     * Apply a view's sort settings to a query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applySort(Builder $query, CustomView $view): Builder
    {
        if ($view->sort_column !== null) {
            // Validate column name contains only safe identifier characters
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $view->sort_column)) {
                return $query;
            }

            $direction = in_array($view->sort_direction, ['asc', 'desc'], true)
                ? $view->sort_direction
                : 'asc';
            $query->orderBy($view->sort_column, $direction);
        }

        return $query;
    }

    /**
     * Extract field names from Ransack-style keys by stripping predicate suffixes.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function extractFieldNames(array $keys): array
    {
        $fields = array_map(function (string $key): string {
            return preg_replace('/_(?:eq|not_eq|lt|lteq|gt|gteq|cont|not_cont|start|end|present|blank|null|not_null|in|not_in|true|false)$/', '', $key);
        }, $keys);

        return array_values(array_unique($fields));
    }
}
