<?php

namespace App\Views;

use App\Models\Opportunity;

class OpportunityColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'opportunities';
    }

    public function modelClass(): string
    {
        return Opportunity::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('subject')->label('Subject')->sortable()->filterable(),
            Column::make('number')->label('Number')->sortable()->filterable(),
            Column::make('reference')->label('Reference')->sortable()->filterable(),
            Column::make('state')->label('State')->sortable()->filterable()->type('enum'),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('member_id')->label('Member')->filterable()->type('relation'),
            Column::make('venue_id')->label('Venue')->filterable()->type('relation'),
            Column::make('store_id')->label('Store')->filterable()->type('relation'),
            Column::make('owned_by')->label('Owner')->filterable()->type('relation'),
            Column::make('starts_at')->label('Starts')->sortable()->filterable()->type('datetime'),
            Column::make('ends_at')->label('Ends')->sortable()->filterable()->type('datetime'),
            Column::make('charge_total')->label('Charge Total')->sortable()->type('money'),
            Column::make('invoiced')->label('Invoiced')->sortable()->filterable()->type('boolean'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['subject', 'reference', 'state', 'status', 'starts_at', 'ends_at', 'charge_total', 'created_at'];
    }
}
