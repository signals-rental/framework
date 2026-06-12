<?php

namespace App\Views;

use App\Models\ProductGroup;

class ProductGroupColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'product_groups';
    }

    public function modelClass(): string
    {
        return ProductGroup::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('description')->label('Description')->filterable(),
            Column::make('parent_id')->label('Parent Group')->sortable()->filterable(),
            Column::make('products_count')->label('Products')->sortable(),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['name', 'description', 'parent_id', 'products_count', 'created_at'];
    }
}
