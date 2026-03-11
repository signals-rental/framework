<?php

namespace App\Data\Members;

use App\Enums\MembershipType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreateMemberData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required]
        public MembershipType $membership_type,
        public bool $is_active = true,
        public ?string $description = null,
        public ?string $locale = null,
        public ?string $default_currency_code = null,
        public ?int $organisation_tax_class_id = null,
        /** @var list<string>|null */
        public ?array $tag_list = null,
        /** @var array<string, mixed> */
        public array $custom_fields = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'membership_type' => ['required', new Enum(MembershipType::class)],
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
