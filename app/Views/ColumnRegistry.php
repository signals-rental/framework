<?php

namespace App\Views;

use App\Services\SchemaRegistry;

abstract class ColumnRegistry
{
    /** @var array<string, Column> */
    private array $columns = [];

    private bool $booted = false;

    abstract public function entityType(): string;

    /**
     * The model class this registry provides columns for.
     *
     * Used to merge custom fields from SchemaRegistry into the column list.
     */
    abstract public function modelClass(): string;

    /**
     * Define the columns for this entity type.
     *
     * @return list<Column>
     */
    abstract protected function columns(): array;

    /**
     * Get all registered columns keyed by column key.
     *
     * @return array<string, Column>
     */
    public function allColumns(): array
    {
        $this->boot();

        return $this->columns;
    }

    /**
     * Get a specific column by key.
     */
    public function get(string $key): ?Column
    {
        $this->boot();

        return $this->columns[$key] ?? null;
    }

    /**
     * Get the default columns for new views.
     *
     * @return list<string>
     */
    public function defaultColumns(): array
    {
        return array_keys($this->allColumns());
    }

    /**
     * Validate that all given column keys are registered.
     *
     * @param  list<string>  $keys
     * @return list<string> Invalid keys
     */
    public function validateColumns(array $keys): array
    {
        $this->boot();

        return array_values(array_filter($keys, fn (string $key): bool => ! isset($this->columns[$key])));
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->columns() as $column) {
            $this->columns[$column->key] = $column;
        }

        $this->mergeCustomFields();

        $this->booted = true;
    }

    /**
     * Merge custom fields from SchemaRegistry into the column list.
     *
     * Custom fields are prefixed with `cf.` to match Ransack custom field query syntax.
     */
    private function mergeCustomFields(): void
    {
        $schema = app(SchemaRegistry::class)->resolve($this->modelClass());

        foreach ($schema as $field) {
            if ($field->source !== 'custom') {
                continue;
            }

            $key = "cf.{$field->name}";

            $column = Column::make($key)
                ->label($field->label ?? str($field->name)->replace('_', ' ')->title()->toString())
                ->type($this->mapSchemaType($field->type));

            if ($field->filterable) {
                $column->filterable();
            }

            if ($field->sortable) {
                $column->sortable();
            }

            $this->columns[$key] = $column;
        }
    }

    /**
     * Map SchemaRegistry field types to Column types.
     */
    private function mapSchemaType(string $schemaType): string
    {
        return match ($schemaType) {
            'boolean' => 'boolean',
            'date', 'datetime' => 'datetime',
            'currency' => 'money',
            'enum' => 'enum',
            default => 'string',
        };
    }
}
