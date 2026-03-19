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
        public ?bool $bookable = null,
        public ?int $location_type = null,
        public ?int $day_cost = null,
        public ?int $hour_cost = null,
        public ?int $distance_cost = null,
        public ?int $flat_rate_cost = null,
        public ?int $lawful_basis_type_id = null,
        public ?int $sale_tax_class_id = null,
        public ?int $purchase_tax_class_id = null,
        /** @var list<string>|null */
        public ?array $tag_list = null,
        public ?string $mapping_id = null,
        // Organisation membership fields
        public ?string $account_number = null,
        public ?string $tax_number = null,
        public ?bool $is_cash = null,
        public ?bool $is_on_stop = null,
        public ?int $rating = null,
        public ?int $owned_by = null,
        public ?int $price_category_id = null,
        public ?int $discount_category_id = null,
        public ?int $invoice_term_id = null,
        public ?int $invoice_term_length = null,
        // Contact membership fields
        public ?string $title = null,
        public ?string $department = null,
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
            'bookable' => ['sometimes', 'boolean'],
            'location_type' => ['sometimes', 'integer', 'in:0,1'],
            'day_cost' => ['sometimes', 'integer', 'min:0'],
            'hour_cost' => ['sometimes', 'integer', 'min:0'],
            'distance_cost' => ['sometimes', 'integer', 'min:0'],
            'flat_rate_cost' => ['sometimes', 'integer', 'min:0'],
            'lawful_basis_type_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'sale_tax_class_id' => ['sometimes', 'nullable', 'integer', 'exists:organisation_tax_classes,id'],
            'purchase_tax_class_id' => ['sometimes', 'nullable', 'integer', 'exists:organisation_tax_classes,id'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string'],
            'mapping_id' => ['sometimes', 'nullable', 'string'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_cash' => ['sometimes', 'boolean'],
            'is_on_stop' => ['sometimes', 'boolean'],
            'rating' => ['sometimes', 'integer', 'min:0', 'max:5'],
            'owned_by' => ['sometimes', 'nullable', 'integer', 'exists:members,id'],
            'price_category_id' => ['sometimes', 'nullable', 'integer'],
            'discount_category_id' => ['sometimes', 'nullable', 'integer'],
            'invoice_term_id' => ['sometimes', 'nullable', 'integer', 'exists:list_values,id'],
            'invoice_term_length' => ['sometimes', 'integer', 'min:0'],
            'title' => ['sometimes', 'nullable', 'string', 'max:50'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'custom_fields' => ['sometimes', 'array'],
        ];
    }
}
