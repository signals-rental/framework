<?php

namespace App\Http\Traits;

use App\Services\Api\RansackFilter;
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
     * @param  list<string>|null  $allowedFields
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
     * @param  list<string>|null  $allowedFields
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
     */
    protected function paginateQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = max(min((int) $request->input('per_page', 20), 100), 1);
        $page = max((int) $request->input('page', 1), 1);

        return $query->paginate(perPage: $perPage, page: $page);
    }
}
