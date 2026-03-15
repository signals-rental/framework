<?php

namespace App\Services;

use App\ValueObjects\FieldDefinition;

/**
 * Fluent builder for constructing individual FieldDefinition value objects.
 *
 * Each setter method returns $this for chaining. Call build() to produce
 * the immutable FieldDefinition.
 */
class FieldBuilder
{
    private string $source = 'core';

    private ?string $model = null;

    private ?string $plugin = null;

    private bool $filterable = true;

    private bool $sortable = true;

    private bool $searchable = false;

    private bool $groupable = false;

    private bool $aggregatable = false;

    private bool $exportable = true;

    private bool $importable = true;

    private ?string $label = null;

    private ?string $description = null;

    private ?string $group = null;

    private ?string $format = null;

    private ?string $alignment = null;

    private ?int $widthHint = null;

    /** @var array<int, mixed> */
    private array $rules = [];

    private bool $required = false;

    private bool $nullable = true;

    private ?string $relationName = null;

    private ?string $relationType = null;

    private ?string $relatedModel = null;

    private ?string $relatedField = null;

    private ?string $crmsFieldName = null;

    private ?string $crmsTransform = null;

    /** @var array<int, string> */
    private array $aggregateFunctions = [];

    public function __construct(
        private readonly string $name,
        private readonly string $type,
    ) {}

    public function filterable(bool $v = true): static
    {
        $this->filterable = $v;

        return $this;
    }

    public function sortable(bool $v = true): static
    {
        $this->sortable = $v;

        return $this;
    }

    public function searchable(bool $v = true): static
    {
        $this->searchable = $v;

        return $this;
    }

    public function groupable(bool $v = true): static
    {
        $this->groupable = $v;

        return $this;
    }

    /**
     * Mark this field as aggregatable with the given functions.
     *
     * @param  string  ...$functions  Aggregate function names: sum, avg, min, max, count
     */
    public function aggregatable(string ...$functions): static
    {
        $this->aggregatable = true;
        $this->aggregateFunctions = array_values($functions);

        return $this;
    }

    public function exportable(bool $v = true): static
    {
        $this->exportable = $v;

        return $this;
    }

    public function importable(bool $v = true): static
    {
        $this->importable = $v;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $desc): static
    {
        $this->description = $desc;

        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function alignment(string $align): static
    {
        $this->alignment = $align;

        return $this;
    }

    public function widthHint(int $width): static
    {
        $this->widthHint = $width;

        return $this;
    }

    public function required(bool $v = true): static
    {
        $this->required = $v;

        return $this;
    }

    public function nullable(bool $v = true): static
    {
        $this->nullable = $v;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Configure relationship metadata for this field.
     */
    public function relation(string $name, string $type, string $model, ?string $field = null): static
    {
        $this->relationName = $name;
        $this->relationType = $type;
        $this->relatedModel = $model;
        $this->relatedField = $field;

        return $this;
    }

    /**
     * Set CRMS compatibility mapping.
     */
    public function crms(?string $fieldName, ?string $transform = null): static
    {
        $this->crmsFieldName = $fieldName;
        $this->crmsTransform = $transform;

        return $this;
    }

    public function source(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function plugin(string $plugin): static
    {
        $this->plugin = $plugin;

        return $this;
    }

    /**
     * Build and return the immutable FieldDefinition.
     */
    public function build(): FieldDefinition
    {
        return new FieldDefinition(
            name: $this->name,
            type: $this->type,
            source: $this->source,
            model: $this->model,
            plugin: $this->plugin,
            filterable: $this->filterable,
            sortable: $this->sortable,
            searchable: $this->searchable,
            groupable: $this->groupable,
            aggregatable: $this->aggregatable,
            exportable: $this->exportable,
            importable: $this->importable,
            label: $this->label,
            description: $this->description,
            group: $this->group,
            format: $this->format,
            alignment: $this->alignment,
            widthHint: $this->widthHint,
            rules: $this->rules,
            required: $this->required,
            nullable: $this->nullable,
            relationName: $this->relationName,
            relationType: $this->relationType,
            relatedModel: $this->relatedModel,
            relatedField: $this->relatedField,
            crmsFieldName: $this->crmsFieldName,
            crmsTransform: $this->crmsTransform,
            aggregateFunctions: $this->aggregateFunctions,
        );
    }
}
