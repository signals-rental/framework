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
     * @param  list<string>  $allowedExplicitFields  Controller whitelist for explicit `q` params
     * @param  array<string, list<string>>  $allowedRelationFilters  Map of relation => allowed columns
     * @return Builder<TModel>
     */
    public function applyFilters(Builder $query, CustomView $view, array $explicitParams = [], array $allowedExplicitFields = [], array $allowedRelationFilters = []): Builder
    {
        // Only honour explicit `q` params whose field is in the controller's
        // whitelist (scalar columns or whitelisted relation columns). Without this,
        // the explicit-param path below would filter on any caller-supplied field
        // name, bypassing $allowedFilters — causing 500s on non-existent columns
        // and arbitrary filtering on unexposed columns.
        $explicitParams = $this->whitelistParams($explicitParams, $allowedExplicitFields, $allowedRelationFilters);

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

        // Explicit params (already whitelisted above) are applied as AND conditions.
        // Pass the custom-field module (derived from the query's model) so that
        // explicit `cf.*` filters are evaluated, mirroring FiltersQueries::applyFilters.
        if (! empty($explicitParams)) {
            $ransack->apply($query, $explicitParams, $allowedExplicitFields, $allowedRelationFilters, $this->resolveCustomFieldModule($query));
        }

        return $query;
    }

    /**
     * Derive the custom-field module type from the query's underlying model.
     *
     * Mirrors how the rest of the application resolves the module via
     * HasCustomFields::customFieldModuleType(). Returns null when the model
     * does not support custom fields.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function resolveCustomFieldModule(Builder $query): ?string
    {
        $model = $query->getModel();

        if (method_exists($model, 'customFieldModuleType')) {
            return $model->customFieldModuleType();
        }

        return null;
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
     * Keep only the explicit `field_predicate` params whose field is whitelisted —
     * either a scalar column in $allowedFields, or a `relation.column` whose
     * relation+column appear in the $allowedRelationFilters map.
     *
     * @param  array<string, mixed>  $params
     * @param  list<string>  $allowedFields
     * @param  array<string, list<string>>  $allowedRelationFilters
     * @return array<string, mixed>
     */
    private function whitelistParams(array $params, array $allowedFields, array $allowedRelationFilters = []): array
    {
        if ($params === []) {
            return [];
        }

        $allowed = [];

        foreach ($params as $key => $value) {
            // Custom-field filters (cf.*) pass through unchanged; they are validated
            // and applied by RansackFilter against the resolved custom-field module.
            if (str_starts_with((string) $key, 'cf.')) {
                $allowed[$key] = $value;

                continue;
            }

            $field = $this->stripPredicate((string) $key);

            if (str_contains($field, '.')) {
                [$relation, $column] = explode('.', $field, 2);
                $columns = $allowedRelationFilters[$relation] ?? null;

                if (is_array($columns) && in_array($column, $columns, true)) {
                    $allowed[$key] = $value;
                }

                continue;
            }

            if (in_array($field, $allowedFields, true)) {
                $allowed[$key] = $value;
            }
        }

        return $allowed;
    }

    /**
     * Strip a trailing Ransack predicate suffix from a filter key to get the field.
     *
     * Predicates are matched longest-first so `status_not_cont` yields `status`,
     * not `status_not`.
     */
    private function stripPredicate(string $key): string
    {
        return preg_replace(
            '/_(?:not_cont|not_null|not_eq|not_in|matches|present|blank|false|start|lteq|gteq|cont|null|true|end|lt|gt|eq|in)$/',
            '',
            $key
        ) ?? $key;
    }
}
