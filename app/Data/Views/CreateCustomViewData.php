<?php

namespace App\Data\Views;

use Spatie\LaravelData\Data;

class CreateCustomViewData extends Data
{
    /**
     * @param  list<string>  $columns
     * @param  list<array{field: string, predicate: string, value: mixed}>  $filters
     * @param  list<int>  $role_ids
     */
    public function __construct(
        public string $name,
        public string $entity_type,
        public string $visibility = 'personal',
        public array $columns = [],
        public array $filters = [],
        public ?string $sort_column = null,
        public string $sort_direction = 'asc',
        public int $per_page = 20,
        public array $role_ids = [],
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', 'max:100'],
            'visibility' => ['sometimes', 'in:personal,shared'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'filters' => ['sometimes', 'array'],
            'filters.*.field' => ['required_with:filters', 'string'],
            'filters.*.predicate' => ['required_with:filters', 'string'],
            'filters.*.value' => ['required_with:filters'],
            'filters.*.logic' => ['sometimes', 'in:and,or,nand,nor'],
            'sort_column' => ['nullable', 'string', 'max:100'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
