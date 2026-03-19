<?php

namespace App\Actions\Views;

use App\Data\Views\CustomViewData;
use App\Data\Views\UpdateCustomViewData;
use App\Models\CustomView;
use App\Services\ColumnRegistryResolver;
use Illuminate\Support\Facades\Gate;

class UpdateCustomView
{
    public function __invoke(CustomView $view, UpdateCustomViewData $data): CustomViewData
    {
        Gate::authorize('update', $view);

        if ($data->columns !== null || $data->sort_column !== null) {
            app(ColumnRegistryResolver::class)->validateColumns(
                $view->entity_type,
                $data->columns ?? $view->columns,
                $data->sort_column,
            );
        }

        $attributes = array_filter([
            'name' => $data->name,
            'visibility' => $data->visibility,
            'columns' => $data->columns,
            'filters' => $data->filters,
            'sort_column' => $data->sort_column,
            'sort_direction' => $data->sort_direction,
            'per_page' => $data->per_page,
        ], fn (mixed $value): bool => $value !== null);

        $view->update($attributes);

        if ($data->role_ids !== null) {
            $view->roles()->sync($data->role_ids);
        }

        return CustomViewData::fromModel($view->fresh());
    }
}
