<?php

namespace App\Http\Traits;

use App\Models\CustomView;
use App\Services\Api\RansackFilter;
use App\Services\ViewResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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

        $allowed = $allowedFields ?? $this->allowedFilters ?? [];
        $allowedRelations = $this->allowedRelationFilters ?? [];
        $customFieldModule = $this->customFieldModule ?? null;

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
        $sort = $request->input('sort');

        if (! $sort) {
            return $query;
        }

        $allowed = $allowedFields ?? $this->allowedSorts ?? [];

        return app(RansackFilter::class)->applySort($query, $sort, $allowed);
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
            $query = $viewResolver->applyFilters($query, $view, $explicitFilters);

            if (! $request->filled('sort')) {
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyIncludes(Builder $query, Request $request, ?Model $model = null): Builder
    {
        $requested = array_filter(explode(',', $request->input('include', '')));
        $allowed = $this->allowedIncludes ?? [];
        $defaults = $this->defaultIncludes ?? [];

        $eagerLoad = array_intersect(array_unique(array_merge($defaults, $requested)), $allowed);

        if ($model) {
            $model->load($eagerLoad);
        }

        if (! empty($eagerLoad)) {
            $query->with($eagerLoad);
        }

        return $query;
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
}
