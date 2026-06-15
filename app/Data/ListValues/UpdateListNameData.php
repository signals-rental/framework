<?php

namespace App\Data\ListValues;

use App\Models\ListName;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateListNameData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_hierarchical = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('list_names', 'name')->ignore(self::currentListNameId()),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_hierarchical' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The id of the list name being updated, resolved from the bound route
     * model so the unique rule ignores the current record on rename.
     */
    private static function currentListNameId(): ?int
    {
        $bound = request()->route('list_name');

        if ($bound instanceof ListName) {
            return $bound->id;
        }

        return is_numeric($bound) ? (int) $bound : null;
    }
}
