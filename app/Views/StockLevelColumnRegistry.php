<?php

namespace App\Views;

use App\Models\StockLevel;

class StockLevelColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'stock_levels';
    }

    public function modelClass(): string
    {
        return StockLevel::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('item_name')->label('Item Name')->sortable()->filterable(),
            Column::make('asset_number')->label('Asset Number')->sortable()->filterable(),
            Column::make('serial_number')->label('Serial Number')->sortable()->filterable(),
            Column::make('barcode')->label('Barcode')->filterable(),
            Column::make('store')->label('Store')->filterable(),
            Column::make('product')->label('Product')->filterable(),
            Column::make('stock_type')->label('Stock Type')->filterable()->type('enum'),
            Column::make('stock_category')->label('Stock Category')->filterable()->type('enum'),
            // Display-only: derived from the parent product's stock_method via the
            // product relation, so it is neither directly sortable nor filterable.
            Column::make('stock_method')->label('Method')->type('enum'),
            Column::make('quantity_held')->label('Qty Held')->sortable(),
            Column::make('quantity_allocated')->label('Qty Allocated')->sortable(),
            Column::make('quantity_unavailable')->label('Qty Unavailable')->sortable(),
            Column::make('location')->label('Location')->filterable()->sortable(),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['item_name', 'asset_number', 'serial_number', 'stock_method', 'store', 'quantity_held', 'quantity_allocated', 'created_at'];
    }
}
