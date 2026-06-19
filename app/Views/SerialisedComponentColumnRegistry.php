<?php

namespace App\Views;

use App\Models\SerialisedComponent;

class SerialisedComponentColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'serialised_components';
    }

    public function modelClass(): string
    {
        return SerialisedComponent::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('product_id')->label('Kit Product')->filterable()->type('relation'),
            Column::make('component_product_id')->label('Component Product')->filterable()->type('relation'),
            Column::make('quantity')->label('Quantity')->sortable()->filterable()->type('number'),
            Column::make('binding')->label('Binding')->sortable()->filterable()->type('enum'),
            Column::make('sort_order')->label('Sort Order')->sortable()->type('number'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['component_product_id', 'quantity', 'binding', 'sort_order'];
    }
}
