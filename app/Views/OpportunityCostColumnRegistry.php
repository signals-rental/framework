<?php

namespace App\Views;

use App\Models\OpportunityCost;

class OpportunityCostColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'opportunity_costs';
    }

    public function modelClass(): string
    {
        return OpportunityCost::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('opportunity_id')->label('Opportunity')->filterable()->type('relation'),
            Column::make('description')->label('Description')->sortable()->filterable(),
            Column::make('cost_type')->label('Cost Type')->sortable()->filterable()->type('enum'),
            Column::make('transaction_type')->label('Transaction Type')->filterable()->type('enum'),
            Column::make('amount')->label('Amount')->sortable()->type('money'),
            Column::make('quantity')->label('Quantity')->sortable()->filterable()->type('number'),
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
        return ['description', 'cost_type', 'amount', 'quantity', 'is_optional', 'created_at'];
    }
}
