<?php

namespace App\Data\Members;

use Spatie\LaravelData\Data;

class UpdateMemberData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $membership_type = null,
        public ?bool $is_active = null,
        public ?string $description = null,
        public ?string $locale = null,
        public ?string $default_currency_code = null,
        public ?int $organisation_tax_class_id = null,
        /** @var list<string>|null */
        public ?array $tag_list = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'membership_type' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:10'],
            'default_currency_code' => ['sometimes', 'nullable', 'string', 'max:3'],
            'organisation_tax_class_id' => ['sometimes', 'nullable', 'integer', 'exists:organisation_tax_classes,id'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
