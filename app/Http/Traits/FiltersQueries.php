<?php

namespace App\Http\Traits;

use App\Services\Api\RansackFilter;
use Illuminate\Database\Eloquent\Builder;
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

        return app(RansackFilter::class)->apply($query, $filters, $allowed);
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
     * Paginate a query using the request's `page` and `per_page` parameters.
     */
    protected function paginateQuery(Builder $query, Request $request): LengthAwarePaginator
    {
        $perPage = max(min((int) $request->input('per_page', 20), 100), 1);
        $page = max((int) $request->input('page', 1), 1);

        return $query->paginate(perPage: $perPage, page: $page);
    }
}
