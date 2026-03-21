<?php

namespace App\Views;

class ProductColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'products';
    }

    public function modelClass(): string
    {
        return \App\Models\Product::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('product_type')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('product_group')->label('Group')->filterable(),
            Column::make('sku')->label('SKU')->sortable()->filterable(),
            Column::make('barcode')->label('Barcode')->filterable(),
            Column::make('is_active')->label('Active')->sortable()->filterable()->type('boolean'),
            Column::make('weight')->label('Weight')->sortable(),
            Column::make('replacement_charge')->label('Replacement Charge')->sortable()->type('money'),
            Column::make('stock_method')->label('Stock Method')->sortable()->filterable()->type('enum'),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['name', 'product_type', 'product_group', 'sku', 'is_active', 'created_at'];
    }
}
