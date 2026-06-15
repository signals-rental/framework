<?php

namespace App\Views;

use App\Models\ProductRate;

class ProductRateColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'product_rates';
    }

    public function modelClass(): string
    {
        return ProductRate::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('product_id')->label('Product')->sortable()->filterable(),
            Column::make('rate_definition_id')->label('Rate Definition')->sortable()->filterable(),
            Column::make('store_id')->label('Store')->sortable()->filterable(),
            Column::make('transaction_type')->label('Transaction Type')->sortable()->filterable()->type('enum'),
            Column::make('price')->label('Price')->sortable()->type('money'),
            Column::make('currency')->label('Currency')->filterable(),
            Column::make('valid_from')->label('Valid From')->sortable()->filterable()->type('datetime'),
            Column::make('valid_to')->label('Valid To')->sortable()->filterable()->type('datetime'),
            Column::make('priority')->label('Priority')->sortable()->filterable(),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['rate_definition_id', 'transaction_type', 'price', 'currency', 'priority', 'created_at'];
    }
}
