<?php

namespace App\Views;

class ActivityColumnRegistry extends ColumnRegistry
{
    public function entityType(): string
    {
        return 'activities';
    }

    public function modelClass(): string
    {
        return \App\Models\Activity::class;
    }

    /**
     * @return list<Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('subject')->label('Subject')->sortable()->filterable(),
            Column::make('type_id')->label('Type')->sortable()->filterable()->type('enum'),
            Column::make('status_id')->label('Status')->sortable()->filterable()->type('enum'),
            Column::make('priority')->label('Priority')->sortable()->filterable()->type('enum'),
            Column::make('regarding')->label('Regarding')->filterable(),
            Column::make('owner')->label('Owner')->filterable(),
            Column::make('starts_at')->label('Starts At')->sortable()->type('datetime'),
            Column::make('ends_at')->label('Ends At')->sortable()->type('datetime'),
            Column::make('completed')->label('Completed')->sortable()->filterable()->type('boolean'),
            Column::make('created_at')->label('Created')->sortable()->type('datetime'),
            Column::make('updated_at')->label('Updated')->sortable()->type('datetime'),
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return ['subject', 'type_id', 'regarding', 'owner', 'starts_at', 'status_id', 'created_at'];
    }
}
