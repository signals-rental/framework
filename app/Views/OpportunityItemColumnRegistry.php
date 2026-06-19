<?php

namespace App\Views;

use App\Models\OpportunityItem;

class OpportunityItemColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'opportunity_items';
    }

    public function modelClass(): string
    {
        return OpportunityItem::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('opportunity_id')->label('Opportunity')->filterable()->type('relation'),
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('item_type')->label('Item Type')->filterable()->type('enum'),
            Column::make('quantity')->label('Quantity')->sortable()->filterable()->type('number'),
            Column::make('unit_price')->label('Unit Price')->sortable()->type('money'),
            Column::make('charge_period')->label('Charge Period')->filterable()->type('enum'),
            Column::make('total')->label('Total')->sortable()->type('money'),
            Column::make('transaction_type')->label('Transaction Type')->filterable()->type('enum'),
            Column::make('starts_at')->label('Starts')->sortable()->filterable()->type('datetime'),
            Column::make('ends_at')->label('Ends')->sortable()->filterable()->type('datetime'),
            Column::make('is_optional')->label('Optional')->sortable()->filterable()->type('boolean'),
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
        return ['name', 'quantity', 'unit_price', 'total', 'starts_at', 'ends_at', 'created_at'];
    }
}
