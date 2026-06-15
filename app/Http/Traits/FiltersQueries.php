<?php

namespace App\Http\Traits;

use App\Models\CustomView;
use App\Services\Api\RansackFilter;
use App\Services\ViewResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

trait FiltersQueries
{
    /**
     * Apply Ransack-compatible filters from the request's `q` parameter.
     *
     * Controllers using this trait should define:
     * protected array $allowedFilters = ['name', 'email', ...];
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>|null  $allowedFields
     * @return Builder<TModel>
     */
    protected function applyFilters(Builder $query, Request $request, ?array $allowedFields = null): Builder
    {
        $filters = $request->input('q', []);

        if (! is_array($filters) || empty($filters)) {
            return $query;
        }

        $filters = $this->translateFilterAliases($filters);
        $allowed = $allowedFields ?? $this->allowedFilters;
        $allowedRelations = $this->allowedRelationFilters;
        $customFieldModule = $this->customFieldModule;

        return app(RansackFilter::class)->apply($query, $filters, $allowed, $allowedRelations, $customFieldModule);
    }

    /**
     * Apply sorting from the request's `sort` parameter.
     *
     * Controllers using this trait should define:
     * protected array $allowedSorts = ['name', 'created_at', ...];
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>|null  $allowedFields
     * @return Builder<TModel>
     */
    protected function applySort(Builder $query, Request $request, ?array $allowedFields = null): Builder
    {
        $sort = $this->resolveSortParam($request);

        if ($sort === null) {
            return $query;
        }

        $sort = $this->translateSortAlias($sort);
        $allowed = $allowedFields ?? $this->allowedSorts;

        return app(RansackFilter::class)->applySort($query, $sort, $allowed);
    }

    /**
     * Resolve the requested sort into the canonical `field` / `-field` form.
     *
     * Accepts either the dedicated `sort` parameter (`name`, `-name`) or the
     * Ransack-style `q[s]` parameter (`name`, `name asc`, `name desc`). Returns
     * null when no sort was requested.
     */
    protected function resolveSortParam(Request $request): ?string
    {
        $sort = $request->input('sort');

        if (is_string($sort) && $sort !== '') {
            return $sort;
        }

        $ransackSort = $request->input('q.s');

        // Ransack also accepts an array form (q[s][]=name desc); take the first.
        if (is_array($ransackSort)) {
            $ransackSort = $ransackSort[0] ?? null;
        }

        if (! is_string($ransackSort) || trim($ransackSort) === '') {
            return null;
        }

        $parts = preg_split('/\s+/', trim($ransackSort)) ?: [];
        $field = $parts[0] ?? '';

        if ($field === '') {
            return null;
        }

        $direction = strtolower($parts[1] ?? 'asc');

        return ($direction === 'desc' ? '-' : '').$field;
    }

    /**
     * Whether the request carries an explicit sort (either `sort` or `q[s]`).
     */
    protected function hasExplicitSort(Request $request): bool
    {
        return $this->resolveSortParam($request) !== null;
    }

    /**
     * Apply view-based or request-based filtering and sorting.
     *
     * If a `view_id` is present, resolves the custom view and applies its filters/sort.
     * Explicit request filters merge with (and override) view filters.
     * Falls back to standard request-based filtering if no view is specified.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{query: Builder<TModel>, view: CustomView|null}
     */
    protected function applyViewOrFilters(Builder $query, Request $request, string $entityType): array
    {
        $viewId = $request->filled('view_id') ? (int) $request->input('view_id') : null;
        $viewResolver = app(ViewResolver::class);
        $view = $viewResolver->resolve($entityType, $viewId, $request->user());

        if ($viewId !== null && $view === null) {
            abort(404, 'Custom view not found.');
        }

        if ($view !== null) {
            $explicitFilters = $request->input('q', []);
            if (! is_array($explicitFilters)) {
                $explicitFilters = [];
            }
            $explicitFilters = $this->translateFilterAliases($explicitFilters);
            $query = $viewResolver->applyFilters($query, $view, $explicitFilters, $this->allowedFilters, $this->allowedRelationFilters);

            if (! $this->hasExplicitSort($request)) {
                $query = $viewResolver->applySort($query, $view);
            } else {
                $query = $this->applySort($query, $request);
            }
        } else {
            $query = $this->applyFilters($query, $request);
            $query = $this->applySort($query, $request);
        }

        return ['query' => $query, 'view' => $view];
    }

    /**
     * Apply eager loading for ?include= relationships, merged with defaults.
     *
     * Controllers using this trait should define:
     * protected array $allowedIncludes = ['addresses', 'emails', ...];
     * protected array $defaultIncludes = ['customFieldValues'];
     *
     * Requested include names are accepted either as the relation method name
     * (e.g. `stockLevels`) or as their response-facing snake_case alias
     * (e.g. `stock_levels`), so callers can request includes using the same
     * names they receive in responses. Names not matching an allowed relation
     * are silently dropped.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyIncludes(Builder $query, Request $request, ?Model $model = null): Builder
    {
        $requested = array_filter(explode(',', $request->input('include', '')));
        $defaults = $this->defaultIncludes;

        $resolved = [];

        foreach (array_unique(array_merge($defaults, $requested)) as $name) {
            $relation = $this->resolveIncludeRelation((string) $name);

            if ($relation !== null) {
                $resolved[$relation] = $relation;
            }
        }

        $eagerLoad = array_values($resolved);

        if ($model) {
            $model->load($eagerLoad);
        }

        if (! empty($eagerLoad)) {
            $query->with($eagerLoad);
        }

        return $query;
    }

    /**
     * Resolve a requested include name to its whitelisted relation method name.
     *
     * Matches the exact relation path (e.g. `accessories.accessoryProduct`) or
     * the response-facing snake_case alias of each allowed include
     * (e.g. `stock_levels` -> `stockLevels`). Returns null when the name does
     * not correspond to any allowed include.
     */
    protected function resolveIncludeRelation(string $name): ?string
    {
        $allowed = $this->allowedIncludes;

        if (in_array($name, $allowed, true)) {
            return $name;
        }

        foreach ($allowed as $relation) {
            if ($this->snakeIncludePath($relation) === $name) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * Convert a (possibly dotted) relation path to its snake_case alias form,
     * preserving the dot segment separators (e.g. `participants.member`).
     */
    protected function snakeIncludePath(string $relation): string
    {
        return implode('.', array_map(
            static fn (string $segment): string => Str::snake($segment),
            explode('.', $relation),
        ));
    }

    /**
     * Paginate a query using the request's `page` and `per_page` parameters.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = max(min((int) $request->input('per_page', 20), 100), 1);
        $page = max((int) $request->input('page', 1), 1);

        return $query->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Rewrite request filter keys from response-facing aliases to real columns.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function translateFilterAliases(array $filters): array
    {
        if ($this->filterAliases === []) {
            return $filters;
        }

        $translated = [];

        foreach ($filters as $key => $value) {
            $newKey = (string) $key;

            // Leave custom-field filters (cf.*) untouched.
            if (! str_starts_with($newKey, 'cf.')) {
                foreach ($this->filterAliases as $alias => $column) {
                    if ($newKey === $alias) {
                        $newKey = $column;

                        break;
                    }

                    // Only rewrite `{alias}_{predicate}` keys — never a longer real
                    // column that merely starts with the alias (e.g. the `preset`
                    // alias must not mangle the real `preset_slug` column key).
                    if (str_starts_with($newKey, $alias.'_')
                        && in_array(substr($newKey, strlen($alias) + 1), RansackFilter::predicates(), true)) {
                        $newKey = $column.substr($newKey, strlen($alias));

                        break;
                    }
                }
            }

            $translated[$newKey] = $value;
        }

        return $translated;
    }

    /**
     * Rewrite a sort field from a response-facing alias to its real column.
     */
    protected function translateSortAlias(string $sort): string
    {
        if ($this->filterAliases === []) {
            return $sort;
        }

        $descending = str_starts_with($sort, '-');
        $field = $descending ? substr($sort, 1) : $sort;

        if (isset($this->filterAliases[$field])) {
            $field = $this->filterAliases[$field];
        }

        return ($descending ? '-' : '').$field;
    }
}
