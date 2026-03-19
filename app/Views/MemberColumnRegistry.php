<?php

namespace App\Views;

class MemberColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'members';
    }

    public function modelClass(): string
    {
        return \App\Models\Member::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('name')->label('Name')->sortable()->filterable(),
            Column::make('membership_type')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('email')->label('Email')->sortable()->filterable(),
            Column::make('phone')->label('Phone')->filterable(),
            Column::make('is_active')->label('Active')->sortable()->filterable()->type('boolean'),
            Column::make('description')->label('Description'),
            Column::make('tags')->label('Tags')->filterable()->type('tags'),
            Column::make('account_number')->label('Account #')->sortable()->filterable(),
            Column::make('tax_number')->label('Tax Number')->filterable(),
            Column::make('is_bookable')->label('Bookable')->filterable()->type('boolean'),
            Column::make('rating')->label('Rating')->sortable()->filterable(),
            Column::make('cost_per_hour')->label('Cost/Hour')->sortable()->type('money'),
            Column::make('cost_per_day')->label('Cost/Day')->sortable()->type('money'),
            Column::make('cost_per_distance')->label('Cost/Distance')->sortable()->type('money'),
            Column::make('charge_per_hour')->label('Charge/Hour')->sortable()->type('money'),
            Column::make('charge_per_day')->label('Charge/Day')->sortable()->type('money'),
            Column::make('charge_per_distance')->label('Charge/Distance')->sortable()->type('money'),
            Column::make('city')->label('City')->sortable()->filterable(),
            Column::make('country')->label('Country')->sortable()->filterable(),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['name', 'membership_type', 'email', 'phone', 'is_active', 'created_at'];
    }
}
