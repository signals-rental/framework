<?php

namespace App\ValueObjects;

/**
 * Immutable value object representing a single field's schema definition.
 *
 * Used by the Field Registry to describe core model columns, computed fields,
 * and custom fields with uniform metadata for filtering, sorting, export, etc.
 */
class FieldDefinition
{
    /**
     * @param  string  $name  Field identifier (column name or custom field name)
     * @param  string  $type  Data type: string, integer, decimal, boolean, date, datetime, currency, enum, json, relation, text, computed
     * @param  string  $source  Origin: 'core', 'computed', or 'custom'
     * @param  string|null  $model  Fully-qualified model class this field belongs to
     * @param  string|null  $plugin  Plugin identifier if field is plugin-contributed
     * @param  bool  $filterable  Whether field supports Ransack-style filtering
     * @param  bool  $sortable  Whether field supports sort ordering
     * @param  bool  $searchable  Whether field is included in full-text search
     * @param  bool  $groupable  Whether field can be used as a grouping dimension
     * @param  bool  $aggregatable  Whether field supports aggregate functions
     * @param  bool  $exportable  Whether field is included in exports
     * @param  bool  $importable  Whether field is accepted during imports
     * @param  string|null  $label  Human-readable display label
     * @param  string|null  $description  Help text or description
     * @param  string|null  $group  Logical grouping for UI organisation
     * @param  string|null  $format  Display format hint (e.g. 'date', 'currency')
     * @param  string|null  $alignment  Column alignment: 'left', 'center', or 'right'
     * @param  int|null  $widthHint  Suggested column width in pixels
     * @param  array<int, mixed>  $rules  Laravel validation rules
     * @param  bool  $required  Whether field is required
     * @param  bool  $nullable  Whether field accepts null values
     * @param  string|null  $relationName  Eloquent relationship method name
     * @param  string|null  $relationType  Relationship type: belongsTo, hasMany, belongsToMany, morphMany
     * @param  string|null  $relatedModel  Fully-qualified related model class
     * @param  string|null  $relatedField  Display field on the related model
     * @param  string|null  $crmsFieldName  Equivalent field name in Current RMS
     * @param  string|null  $crmsTransform  Transformation identifier for CRMS compatibility
     * @param  array<int, string>  $aggregateFunctions  Supported aggregate functions: sum, avg, min, max, count
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $source = 'core',
        public readonly ?string $model = null,
        public readonly ?string $plugin = null,
        public readonly bool $filterable = true,
        public readonly bool $sortable = true,
        public readonly bool $searchable = false,
        public readonly bool $groupable = false,
        public readonly bool $aggregatable = false,
        public readonly bool $exportable = true,
        public readonly bool $importable = true,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
        public readonly ?string $group = null,
        public readonly ?string $format = null,
        public readonly ?string $alignment = null,
        public readonly ?int $widthHint = null,
        public readonly array $rules = [],
        public readonly bool $required = false,
        public readonly bool $nullable = true,
        public readonly ?string $relationName = null,
        public readonly ?string $relationType = null,
        public readonly ?string $relatedModel = null,
        public readonly ?string $relatedField = null,
        public readonly ?string $crmsFieldName = null,
        public readonly ?string $crmsTransform = null,
        public readonly array $aggregateFunctions = [],
    ) {}

    /**
     * Convert all properties to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'source' => $this->source,
            'model' => $this->model,
            'plugin' => $this->plugin,
            'filterable' => $this->filterable,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'groupable' => $this->groupable,
            'aggregatable' => $this->aggregatable,
            'exportable' => $this->exportable,
            'importable' => $this->importable,
            'label' => $this->label,
            'description' => $this->description,
            'group' => $this->group,
            'format' => $this->format,
            'alignment' => $this->alignment,
            'widthHint' => $this->widthHint,
            'rules' => $this->rules,
            'required' => $this->required,
            'nullable' => $this->nullable,
            'relationName' => $this->relationName,
            'relationType' => $this->relationType,
            'relatedModel' => $this->relatedModel,
            'relatedField' => $this->relatedField,
            'crmsFieldName' => $this->crmsFieldName,
            'crmsTransform' => $this->crmsTransform,
            'aggregateFunctions' => $this->aggregateFunctions,
        ];
    }
}
