<?php

namespace App\Views;

use App\Models\ShortageResolution;

class ShortageResolutionColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'shortage_resolutions';
    }

    public function modelClass(): string
    {
        return ShortageResolution::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('resolver_key')->label('Resolver')->sortable()->filterable(),
            Column::make('resolution_type')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('quantity_resolved')->label('Quantity Resolved')->sortable(),
            Column::make('cost')->label('Cost')->sortable()->type('money'),
            Column::make('resolved_by')->label('Resolved By')->filterable()->type('relation'),
            Column::make('confirmed_by')->label('Confirmed By')->filterable()->type('relation'),
            Column::make('confirmed_at')->label('Confirmed')->sortable()->filterable()->type('datetime'),
            Column::make('fulfilled_at')->label('Fulfilled')->sortable()->filterable()->type('datetime'),
            Column::make('cancelled_at')->label('Cancelled')->sortable()->filterable()->type('datetime'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['resolver_key', 'resolution_type', 'status', 'quantity_resolved', 'cost', 'confirmed_at', 'created_at'];
    }
}
