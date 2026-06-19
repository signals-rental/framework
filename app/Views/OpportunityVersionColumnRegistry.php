<?php

namespace App\Views;

use App\Models\OpportunityVersion;

class OpportunityVersionColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'opportunity_versions';
    }

    public function modelClass(): string
    {
        return OpportunityVersion::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('version_number')->label('Version')->sortable()->filterable(),
            Column::make('label')->label('Label')->sortable()->filterable(),
            Column::make('version_type')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('status')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('is_active')->label('Active')->sortable()->filterable()->type('boolean'),
            Column::make('opportunity_id')->label('Opportunity')->filterable()->type('relation'),
            Column::make('charge_total')->label('Charge Total')->sortable()->type('money'),
            Column::make('sent_at')->label('Sent')->sortable()->filterable()->type('datetime'),
            Column::make('accepted_at')->label('Accepted')->sortable()->filterable()->type('datetime'),
            Column::make('created_at')->label('Created')->sortable()->filterable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['version_number', 'label', 'version_type', 'status', 'is_active', 'charge_total', 'created_at'];
    }
}
