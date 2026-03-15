<?php

namespace App\Services;

use App\ValueObjects\FieldDefinition;

/**
 * Fluent builder for declaring a model's full field schema.
 *
 * Provides type-shortcut methods that create FieldBuilder instances,
 * which are stored internally. Call build() to produce the final
 * array of FieldDefinition objects keyed by field name.
 */
class SchemaBuilder
{
    /** @var array<int, FieldBuilder> */
    private array $builders = [];

    public function string(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'string'));
    }

    public function text(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'text'));
    }

    public function integer(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'integer'));
    }

    public function decimal(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'decimal'));
    }

    public function boolean(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'boolean'));
    }

    public function date(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'date'));
    }

    public function datetime(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'datetime'));
    }

    public function currency(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'currency'));
    }

    public function enum(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'enum'));
    }

    public function json(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'json'));
    }

    public function relation(string $name): FieldBuilder
    {
        return $this->addBuilder(new FieldBuilder($name, 'relation'));
    }

    public function computed(string $name, string $type = 'string'): FieldBuilder
    {
        $builder = new FieldBuilder($name, $type);
        $builder->source('computed');

        return $this->addBuilder($builder);
    }

    /**
     * Build all registered field definitions.
     *
     * @return array<string, FieldDefinition>
     */
    public function build(): array
    {
        $definitions = [];

        foreach ($this->builders as $builder) {
            $definition = $builder->build();
            $definitions[$definition->name] = $definition;
        }

        return $definitions;
    }

    private function addBuilder(FieldBuilder $builder): FieldBuilder
    {
        $this->builders[] = $builder;

        return $builder;
    }
}
